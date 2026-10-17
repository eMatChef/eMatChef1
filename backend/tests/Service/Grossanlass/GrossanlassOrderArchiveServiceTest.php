<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassOrderArchiveLog;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassDependencyService;
use App\Service\Grossanlass\GrossanlassOrderAcceptanceService;
use App\Service\Grossanlass\GrossanlassOrderArchiveService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class GrossanlassOrderArchiveServiceTest extends TestCase
{
    /**
     * @param list<array{group_id: string, reason: string}> $history Zeilen der Verlaufs-Abfrage
     */
    private function service(array $history = [], string|false $lastAcceptanceType = false): GrossanlassOrderArchiveService
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($history);
        $connection->method('fetchOne')->willReturn($lastAcceptanceType);
        $connection->method('fetchFirstColumn')->willReturn([]);
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findBy')->willReturn([]);
        $repository->method('findOneBy')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('getRepository')->willReturn($repository);
        $acceptance = new GrossanlassOrderAcceptanceService($em);

        return new GrossanlassOrderArchiveService($em, $acceptance, new GrossanlassDependencyService($em, $acceptance));
    }

    private function group(string $kind = Group::GROSSANLASS_KIND_AUFTRAG): Group
    {
        $group = new Group();
        $group->setId('grp000000001');
        $group->setName('Bar West');
        $group->setGrossanlassKind($kind);
        $department = $this->createMock(Department::class);
        $department->method('getId')->willReturn('dep000000001');
        $group->setDepartment($department);

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

    public function testPhysicalDeleteIsAllowedOnlyWithoutWorkOrProtocol(): void
    {
        $this->service([])->assertPhysicallyDeletable($this->group());

        foreach (['work', 'checklist', 'blocked', 'task_events', 'order_events', 'rework', 'dependencies', 'archive'] as $reason) {
            try {
                $this->service([['group_id' => 'grp000000001', 'reason' => $reason]])->assertPhysicallyDeletable($this->group());
                self::fail('Löschen muss verboten sein wegen: ' . $reason);
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('archivieren', $e->getMessage());
                self::assertStringContainsString('Bar West', $e->getMessage());
            }
        }
        // andere Bauaufträge mit Verlauf betreffen diesen nicht
        $this->service([['group_id' => 'grp000000999', 'reason' => 'work']])->assertPhysicallyDeletable($this->group());
    }

    public function testStructureGroupsAreNotSubjectToTheOrderRule(): void
    {
        $this->service([['group_id' => 'grp000000001', 'reason' => 'work']])->assertPhysicallyDeletable($this->group(Group::GROSSANLASS_KIND_RESSORT));
        self::addToAssertionCount(1);
    }

    public function testArchiveNeedsReasonAndAnOrder(): void
    {
        try {
            $this->service()->archive($this->department(), $this->group(), $this->user(), '  ');
            self::fail('Begründung ist Pflicht');
        } catch (\InvalidArgumentException) {
            self::assertFalse($this->group()->isArchived());
        }
        try {
            $this->service()->archive($this->department(), $this->group(Group::GROSSANLASS_KIND_BEREICH), $this->user(), 'x');
            self::fail('Strukturgruppen werden nicht archiviert');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }

    public function testArchiveKeepsDataAndLogsPersonTimeAndReason(): void
    {
        $group = $this->group();
        $log = $this->service()->archive($this->department(), $group, $this->user(), 'Auftrag abgebrochen');

        self::assertTrue($group->isArchived());
        self::assertSame('Auftrag abgebrochen', $group->getArchiveReason());
        self::assertSame('u0000000001', $group->getArchivedByUserId());
        self::assertNotNull($group->getArchivedAt());
        self::assertSame(DepartmentGrossanlassOrderArchiveLog::ARCHIVED, $log->getType());
        self::assertSame('Auftrag abgebrochen', $log->getNote());

        // ein zweites Mal archivieren ist nicht möglich; archivierte Bauaufträge sind gesperrt
        try {
            $this->service()->archive($this->department(), $group, $this->user(), 'nochmal');
            self::fail('bereits archiviert');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->assertNotArchived($group);
    }

    public function testReportedOrderCannotBeArchived(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service([], 'reported')->archive($this->department(), $this->group(), $this->user(), 'x');
    }

    public function testRestoreClearsStateButKeepsAProtocol(): void
    {
        $group = $this->group();
        $this->service()->archive($this->department(), $group, $this->user(), 'Grund');
        $log = $this->service()->restore($this->department(), $group, $this->user(), 'Doch nötig');

        self::assertFalse($group->isArchived());
        self::assertNull($group->getArchiveReason());
        self::assertSame(DepartmentGrossanlassOrderArchiveLog::RESTORED, $log->getType());
        self::assertSame('Doch nötig', $log->getNote());

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->restore($this->department(), $group, $this->user(), null);
    }
}
