<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassDependency;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassDependencyService;
use App\Service\Grossanlass\GrossanlassOrderAcceptanceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class GrossanlassDependencyServiceTest extends TestCase
{
    private function dependency(string $id, string $successor, string $predecessor): DepartmentGrossanlassDependency
    {
        $department = $this->createMock(Department::class);
        $department->method('getId')->willReturn('dep000000001');

        return new DepartmentGrossanlassDependency($id, $department, 'task', $successor, $predecessor, 'u0000000001');
    }

    private function user(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('u0000000001');

        return $user;
    }

    /** @param list<DepartmentGrossanlassDependency> $rows */
    private function service(array $rows): GrossanlassDependencyService
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findBy')->willReturn($rows);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new GrossanlassDependencyService($em, new GrossanlassOrderAcceptanceService($em));
    }

    public function testCycleDetectionFollowsPredecessorsTransitively(): void
    {
        // c wartet auf b, b wartet auf a. a auf c warten zu lassen wäre ein Zyklus.
        $service = $this->service([
            $this->dependency('gy0000000001', 'ccccccccccc3', 'bbbbbbbbbbb2'),
            $this->dependency('gy0000000002', 'bbbbbbbbbbb2', 'aaaaaaaaaaa1'),
        ]);
        $reaches = new \ReflectionMethod($service, 'reaches');

        self::assertTrue($reaches->invoke($service, 'dep000000001', 'task', 'ccccccccccc3', 'aaaaaaaaaaa1'), 'a→b→c ist erreichbar');
        self::assertFalse($reaches->invoke($service, 'dep000000001', 'task', 'aaaaaaaaaaa1', 'ccccccccccc3'), 'umgekehrt nicht');
    }

    public function testReleaseNeedsReasonAndHappensOnlyOnce(): void
    {
        $service = $this->service([]);
        $dependency = $this->dependency('gy0000000001', 'bbbbbbbbbbb2', 'aaaaaaaaaaa1');

        try {
            $service->release($dependency, $this->user(), '  ');
            self::fail('Begründung ist Pflicht');
        } catch (\InvalidArgumentException) {
            self::assertFalse($dependency->isReleased());
        }

        $service->release($dependency, $this->user(), 'Material ist schon da');
        self::assertTrue($dependency->isReleased());
        self::assertSame('Material ist schon da', $dependency->getReleaseReason());
        self::assertNotNull($dependency->getReleasedAt());

        $this->expectException(\InvalidArgumentException::class);
        $service->release($dependency, $this->user(), 'nochmal');
    }

    public function testRemovalKeepsTheRowAsProtocol(): void
    {
        $service = $this->service([]);
        $dependency = $this->dependency('gy0000000001', 'bbbbbbbbbbb2', 'aaaaaaaaaaa1');
        $service->remove($dependency, $this->user());

        self::assertTrue($dependency->isRemoved());
        self::assertSame('u0000000001', $dependency->getRemovedByUserId());
    }

    public function testPredecessorDetailsAreHiddenWithoutViewRight(): void
    {
        $service = $this->service([]);
        $dependency = $this->dependency('gy0000000001', 'bbbbbbbbbbb2', 'aaaaaaaaaaa1');

        $hidden = $service->serialize([$dependency], static fn () => false)[0];
        self::assertFalse($hidden['predecessor_visible']);
        self::assertNull($hidden['predecessor_id']);
        self::assertNull($hidden['predecessor_title']);
        self::assertNull($hidden['predecessor_complete']);
        self::assertArrayNotHasKey('predecessor_group', $hidden);

        $visible = $service->serialize([$dependency], static fn () => true)[0];
        self::assertTrue($visible['predecessor_visible']);
        self::assertSame('aaaaaaaaaaa1', $visible['predecessor_id']);
    }

    private function simultaneous(string $id, string $successor, string $predecessor): DepartmentGrossanlassDependency
    {
        return $this->dependency($id, $successor, $predecessor)->setInitialMode(DepartmentGrossanlassDependency::MODE_SIMULTANEOUS);
    }

    public function testSimultaneousNeverBlocksButSequentialDoes(): void
    {
        $service = $this->service([]);
        $simultaneous = $this->simultaneous('gy0000000001', 'bbbbbbbbbbb2', 'aaaaaaaaaaa1');
        $sequential = $this->dependency('gy0000000002', 'bbbbbbbbbbb2', 'ccccccccccc3');

        self::assertSame([], $service->openOf([$simultaneous]));
        self::assertSame([$sequential], $service->openOf([$simultaneous, $sequential]));
    }

    public function testSimultaneousEdgesAreIgnoredByCycleDetection(): void
    {
        // a und b «gleichzeitig»: b darf trotzdem nacheinander auf a warten (kein Wartezyklus).
        $service = $this->service([$this->simultaneous('gy0000000002', 'aaaaaaaaaaa1', 'bbbbbbbbbbb2')]);
        $reaches = new \ReflectionMethod($service, 'reaches');

        self::assertFalse($reaches->invoke($service, 'dep000000001', 'task', 'bbbbbbbbbbb2', 'aaaaaaaaaaa1'));
        self::assertFalse($reaches->invoke($service, 'dep000000001', 'task', 'aaaaaaaaaaa1', 'bbbbbbbbbbb2'));
    }

    public function testChangingToSequentialIsRejectedIfItWouldCreateACycle(): void
    {
        // b wartet auf a; a↔b gleichzeitig soll nicht nachträglich «a wartet auf b» werden.
        $service = $this->service([$this->dependency('gy0000000001', 'bbbbbbbbbbb2', 'aaaaaaaaaaa1')]);
        $link = $this->simultaneous('gy0000000002', 'aaaaaaaaaaa1', 'bbbbbbbbbbb2');

        try {
            $service->changeMode($link, $this->user(), DepartmentGrossanlassDependency::MODE_SEQUENTIAL);
            self::fail('Zyklus muss abgelehnt werden');
        } catch (\InvalidArgumentException) {
            self::assertTrue($link->isSimultaneous());
        }
    }

    public function testChangeModeIsProtocolledAndSimultaneousCannotBeReleased(): void
    {
        $service = $this->service([]);
        $link = $this->dependency('gy0000000001', 'bbbbbbbbbbb2', 'aaaaaaaaaaa1');
        $service->changeMode($link, $this->user(), DepartmentGrossanlassDependency::MODE_SIMULTANEOUS);

        self::assertTrue($link->isSimultaneous());
        self::assertSame('u0000000001', $link->getModeChangedByUserId());
        self::assertNotNull($link->getModeChangedAt());
        $this->expectException(\InvalidArgumentException::class);
        $service->release($link, $this->user(), 'unnötig');
    }

    /** @return array{0: string, 1: ?array<string, mixed>} */
    private function scheduleConflict(?string $ownStart, ?int $ownMinutes, ?string $partnerStart, ?int $partnerMinutes): array
    {
        $task = function (?string $start, ?int $minutes): DepartmentGrossanlassTask {
            $t = $this->createMock(DepartmentGrossanlassTask::class);
            $t->method('getStartsAt')->willReturn($start !== null ? new \DateTime($start) : null);
            $t->method('getDurationMinutes')->willReturn($minutes);

            return $t;
        };
        $tasks = ['own' => $task($ownStart, $ownMinutes), 'partner' => $task($partnerStart, $partnerMinutes)];
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('find')->willReturnCallback(static fn (string $id) => $tasks[$id] ?? null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $service = new GrossanlassDependencyService($em, new GrossanlassOrderAcceptanceService($em));
        $result = (new \ReflectionMethod($service, 'scheduleOf'))->invoke($service, 'own', 'partner');

        return [$result['conflict'], $result];
    }

    public function testSimultaneousScheduleConflicts(): void
    {
        self::assertSame('none', $this->scheduleConflict('2027-06-10 08:00', 120, '2027-06-10 09:00', 60)[0], 'überlappend');
        self::assertSame('no_overlap', $this->scheduleConflict('2027-06-10 08:00', 60, '2027-06-10 10:00', 60)[0], 'getrennte Zeitfenster');
        self::assertSame('unscheduled', $this->scheduleConflict('2027-06-10 08:00', 60, null, null)[0], 'Startzeit fehlt');
        self::assertSame(120, $this->scheduleConflict('2027-06-10 08:00', 60, '2027-06-10 10:00', 60)[1]['start_diff_minutes']);
    }
}
