<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\DepartmentGrossanlassTaskStep;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use App\Service\Grossanlass\GrossanlassTaskWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Fachliche Ereigniszeiten (Verlauf einer Aufgabe) folgen der BusinessClock des Departments; created_at/updated_at der
 * Zeilen bleiben technische Erfassungszeit.
 */
final class GrossanlassBusinessTimeEventsTest extends TestCase
{
    private const WALL = '2027-05-19 14:30:00';

    private function service(?int $offset): GrossanlassTaskWorkflowService
    {
        $department = (new Department())->setId('d1')->setDemoMode(true)->setDemoClockOffsetSeconds($offset);
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('find')->willReturn($department);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');

        return new GrossanlassTaskWorkflowService($em, new BusinessClock($em, $kernel));
    }

    private function task(): DepartmentGrossanlassTask
    {
        $task = (new DepartmentGrossanlassTask())->setId('gt0000000001');
        $task->setDepartment((new Department())->setId('d1'));

        return $task;
    }

    private function user(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('u1');

        return $user;
    }

    public function testReportedEventCarriesTheBusinessTime(): void
    {
        $offset = (new \DateTimeImmutable(self::WALL))->getTimestamp() - time();

        $event = $this->service($offset)->reportDone($this->task(), $this->user(), null);

        self::assertEqualsWithDelta((new \DateTimeImmutable(self::WALL))->getTimestamp(), $event->getCreatedAt()->getTimestamp(), 3);
    }

    public function testBlockTimestampCarriesTheBusinessTime(): void
    {
        $offset = (new \DateTimeImmutable(self::WALL))->getTimestamp() - time();
        $task = $this->task();

        $this->service($offset)->block($task, $this->user(), 'Material fehlt');

        self::assertEqualsWithDelta((new \DateTimeImmutable(self::WALL))->getTimestamp(), $task->getBlockedAt()->getTimestamp(), 3);
    }

    public function testChecklistTickCarriesTheBusinessTimeAndNeverResetsStatus(): void
    {
        $offset = (new \DateTimeImmutable(self::WALL))->getTimestamp() - time();
        $task = $this->task()->setStatus(DepartmentGrossanlassTask::STATUS_PROGRESS);
        $step = new DepartmentGrossanlassTaskStep('gs0000000001', $task->getId());
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('find')->willReturn($step);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $department = (new Department())->setId('d1')->setDemoMode(true)->setDemoClockOffsetSeconds($offset);
        $deptRepo = $this->createMock(ObjectRepository::class);
        $deptRepo->method('find')->willReturn($department);
        $clockEm = $this->createMock(EntityManagerInterface::class);
        $clockEm->method('getRepository')->willReturn($deptRepo);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');
        $service = new GrossanlassTaskWorkflowService($em, new BusinessClock($clockEm, $kernel));

        $service->updateStep($task, 'gs0000000001', ['done' => true], $this->user(), GrossanlassTaskWorkflowService::permissions(true, false, false));

        self::assertTrue($step->isDone());
        self::assertEqualsWithDelta((new \DateTimeImmutable(self::WALL))->getTimestamp(), $step->getDoneAt()->getTimestamp(), 3);
        self::assertSame(DepartmentGrossanlassTask::STATUS_PROGRESS, $task->getStatus());
    }

    public function testWithoutClockTheRecordingTimeIsUsed(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $event = (new GrossanlassTaskWorkflowService($em))->reportDone($this->task(), $this->user(), null);

        self::assertEqualsWithDelta(time(), $event->getCreatedAt()->getTimestamp(), 3);
    }
}
