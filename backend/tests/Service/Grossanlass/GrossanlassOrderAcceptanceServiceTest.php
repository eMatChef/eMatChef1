<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassOrderEvent;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassOrderAcceptanceService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class GrossanlassOrderAcceptanceServiceTest extends TestCase
{
    /**
     * @param string|false $lastType     letztes Ereignis (false = keines)
     * @param list<string> $taskStatuses
     */
    private function service(string|false $lastType, array $taskStatuses = []): GrossanlassOrderAcceptanceService
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static fn (string $sql) => str_contains($sql, 'MAX(seq)') ? 0 : $lastType);
        $connection->method('fetchFirstColumn')->willReturn($taskStatuses);
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('getRepository')->willReturn($repository);

        return new GrossanlassOrderAcceptanceService($em);
    }

    private function group(string $kind): Group
    {
        $group = $this->createMock(Group::class);
        $group->method('getGrossanlassKind')->willReturn($kind);
        $group->method('getId')->willReturn('grp000000001');

        return $group;
    }

    private function user(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('u0000000001');

        return $user;
    }

    private function department(): Department
    {
        $department = $this->createMock(Department::class);
        $department->method('getId')->willReturn('dep000000001');

        return $department;
    }

    public function testStatusIsDerivedFromTheLastEvent(): void
    {
        self::assertSame('open', GrossanlassOrderAcceptanceService::statusFromLastType(null));
        self::assertSame('review', GrossanlassOrderAcceptanceService::statusFromLastType(DepartmentGrossanlassOrderEvent::REPORTED));
        self::assertSame('accepted', GrossanlassOrderAcceptanceService::statusFromLastType(DepartmentGrossanlassOrderEvent::ACCEPTED));
        foreach ([DepartmentGrossanlassOrderEvent::RETURNED, DepartmentGrossanlassOrderEvent::WITHDRAWN, DepartmentGrossanlassOrderEvent::REOPENED] as $type) {
            self::assertSame('open', GrossanlassOrderAcceptanceService::statusFromLastType($type));
        }
    }

    public function testOnlyRealOrdersNotStructureGroups(): void
    {
        self::assertTrue(GrossanlassOrderAcceptanceService::isOrderGroup($this->group(Group::GROSSANLASS_KIND_TEILBEREICH)));
        self::assertTrue(GrossanlassOrderAcceptanceService::isOrderGroup($this->group(Group::GROSSANLASS_KIND_AUFTRAG)));
        self::assertFalse(GrossanlassOrderAcceptanceService::isOrderGroup($this->group(Group::GROSSANLASS_KIND_RESSORT)));
        self::assertFalse(GrossanlassOrderAcceptanceService::isOrderGroup($this->group(Group::GROSSANLASS_KIND_BEREICH)));

        $this->expectException(\InvalidArgumentException::class);
        $this->service(false, ['done'])->report($this->department(), $this->group(Group::GROSSANLASS_KIND_RESSORT), $this->user());
    }

    public function testEligibleOnlyWhenAllTasksAreDone(): void
    {
        self::assertFalse(GrossanlassOrderAcceptanceService::eligibleFor([]), 'ohne Aufgaben keine Gesamtabnahme');
        self::assertFalse(GrossanlassOrderAcceptanceService::eligibleFor(['done', 'review']));
        self::assertFalse(GrossanlassOrderAcceptanceService::eligibleFor(['done', 'progress']));
        self::assertTrue(GrossanlassOrderAcceptanceService::eligibleFor(['done', 'done']));
    }

    public function testReportIsRefusedWhileTasksAreOpen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service(false, ['done', 'progress'])->report($this->department(), $this->group(Group::GROSSANLASS_KIND_AUFTRAG), $this->user());
    }

    public function testReportAcceptAndNoAutomaticAcceptance(): void
    {
        $order = $this->group(Group::GROSSANLASS_KIND_AUFTRAG);
        $reported = $this->service(false, ['done', 'done'])->report($this->department(), $order, $this->user());
        self::assertSame('reported', $reported->getType());
        self::assertSame(1, $reported->getSeq());

        // Abnahme nur aus dem Zustand «gemeldet»; nie automatisch aus «offen».
        try {
            $this->service(false, ['done'])->accept($this->department(), $order, $this->user());
            self::fail('Keine Abnahme ohne Meldung');
        } catch (\InvalidArgumentException) {
        }
        $accepted = $this->service('reported', ['done', 'done'])->accept($this->department(), $order, $this->user());
        self::assertSame('accepted', $accepted->getType());
    }

    public function testAcceptIsRefusedIfATaskWasReopenedMeanwhile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service('reported', ['done', 'progress'])->accept($this->department(), $this->group(Group::GROSSANLASS_KIND_AUFTRAG), $this->user());
    }

    public function testReturnAndReopenNeedAReasonAndTheRightState(): void
    {
        $order = $this->group(Group::GROSSANLASS_KIND_AUFTRAG);
        try {
            $this->service('reported', ['done'])->returnOrder($this->department(), $order, $this->user(), '  ');
            self::fail('Begründung Pflicht');
        } catch (\InvalidArgumentException) {
        }
        $returned = $this->service('reported', ['done'])->returnOrder($this->department(), $order, $this->user(), 'Kante fehlt');
        self::assertSame('returned', $returned->getType());
        self::assertSame('Kante fehlt', $returned->getNote());

        try {
            $this->service(false, ['done'])->reopen($this->department(), $order, $this->user(), 'x');
            self::fail('Widerruf nur bei abgenommenem Bauauftrag');
        } catch (\InvalidArgumentException) {
        }
        $reopened = $this->service('accepted', ['done'])->reopen($this->department(), $order, $this->user(), 'Nachmessung nötig');
        self::assertSame('reopened', $reopened->getType());
    }

    public function testTaskChangesAreGuardedWhileReportedOrAccepted(): void
    {
        $this->service(false)->assertTasksMutable('grp000000001');
        foreach (['reported', 'accepted'] as $type) {
            try {
                $this->service($type)->assertTasksMutable('grp000000001');
                self::fail('Änderung muss gesperrt sein bei ' . $type);
            } catch (\InvalidArgumentException) {
            }
        }
        $this->service('returned')->assertTasksMutable('grp000000001');
        $this->service('reopened')->assertTasksMutable('grp000000001');
        self::addToAssertionCount(3);
    }

    public function testDeleteIsRefusedForReportedOrAcceptedOrders(): void
    {
        $this->service(false)->assertDeletable(['grp000000001']);
        $this->expectException(\RuntimeException::class);
        $this->service('accepted')->assertDeletable(['grp000000001']);
    }

    private function serviceWithEvent(?DepartmentGrossanlassOrderEvent $event): GrossanlassOrderAcceptanceService
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('find')->willReturn($event);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new GrossanlassOrderAcceptanceService($em);
    }

    private function event(string $type, string $groupId = 'grp000000001'): DepartmentGrossanlassOrderEvent
    {
        $group = $this->createMock(Group::class);
        $group->method('getId')->willReturn($groupId);

        return new DepartmentGrossanlassOrderEvent('gz0000000001', $this->department(), $group, 1, $type, 'u0000000001', 'Kante fehlt');
    }

    public function testReworkMayOnlyLinkToARejectionOfThisOrder(): void
    {
        $order = $this->group(Group::GROSSANLASS_KIND_AUFTRAG);
        $linked = $this->serviceWithEvent($this->event(DepartmentGrossanlassOrderEvent::RETURNED))->assertReworkEvent($order, 'gz0000000001');
        self::assertSame('Kante fehlt', $linked->getNote());

        foreach ([
            'andere Art Ereignis' => $this->event(DepartmentGrossanlassOrderEvent::REPORTED),
            'anderer Bauauftrag' => $this->event(DepartmentGrossanlassOrderEvent::RETURNED, 'grp000000999'),
            'unbekannt' => null,
        ] as $label => $event) {
            try {
                $this->serviceWithEvent($event)->assertReworkEvent($order, 'gz0000000001');
                self::fail('muss abgelehnt werden: ' . $label);
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
