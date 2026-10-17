<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassTask;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassTaskWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class GrossanlassTaskWorkflowServiceTest extends TestCase
{
    private function service(): GrossanlassTaskWorkflowService
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new GrossanlassTaskWorkflowService($em);
    }

    private function user(string $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    public function testPermissionMatrix(): void
    {
        self::assertSame(['edit' => true, 'check' => true, 'report' => true, 'unblock' => true, 'approve' => true], GrossanlassTaskWorkflowService::permissions(true, false, false));
        self::assertSame(['edit' => false, 'check' => true, 'report' => true, 'unblock' => false, 'approve' => false], GrossanlassTaskWorkflowService::permissions(false, true, false));
        self::assertSame(['edit' => false, 'check' => true, 'report' => false, 'unblock' => false, 'approve' => false], GrossanlassTaskWorkflowService::permissions(false, false, true));
        self::assertSame(['edit' => false, 'check' => false, 'report' => false, 'unblock' => false, 'approve' => false], GrossanlassTaskWorkflowService::permissions(false, false, false));
    }

    public function testBlockNeedsReasonAndIsNeverReleasedAutomatically(): void
    {
        $service = $this->service();
        $task = (new DepartmentGrossanlassTask())->setId("gt0000000001");

        try {
            $service->block($task, $this->user('u1'), '  ');
            self::fail('Begründung muss Pflicht sein');
        } catch (\InvalidArgumentException) {
        }

        $service->block($task, $this->user('u1'), 'Material fehlt');
        self::assertTrue($task->isBlocked());
        self::assertSame('Material fehlt', GrossanlassTaskWorkflowService::serializeBlock($task)['reason']);

        // Statuswechsel gibt nicht frei.
        $this->expectException(\InvalidArgumentException::class);
        $service->assertStatusChangeAllowed($task, DepartmentGrossanlassTask::STATUS_PROGRESS, false);
    }

    public function testBlockedTaskCanOnlyBeUnblockedExplicitly(): void
    {
        $service = $this->service();
        $task = (new DepartmentGrossanlassTask())->setId("gt0000000001");
        $service->block($task, $this->user('u1'), 'Problem');
        $service->assertStatusChangeAllowed($task, DepartmentGrossanlassTask::STATUS_OPEN, false);
        $service->assertStatusChangeAllowed($task, DepartmentGrossanlassTask::STATUS_OPEN, false); // unverändert erlaubt

        $service->unblock($task);
        self::assertFalse($task->isBlocked());
        self::assertNull(GrossanlassTaskWorkflowService::serializeBlock($task));
        $service->assertStatusChangeAllowed($task, DepartmentGrossanlassTask::STATUS_PROGRESS, false);
    }

    public function testDoneTaskCannotBeBlockedNorBlockedTwice(): void
    {
        $service = $this->service();
        $done = (new DepartmentGrossanlassTask())->setId("gt0000000002")->setStatus(DepartmentGrossanlassTask::STATUS_DONE);
        try {
            $service->block($done, $this->user('u1'), 'x');
            self::fail('erledigte Aufgabe darf nicht blockiert werden');
        } catch (\InvalidArgumentException) {
        }

        $task = (new DepartmentGrossanlassTask())->setId("gt0000000001");
        $service->block($task, $this->user('u1'), 'a');
        $this->expectException(\InvalidArgumentException::class);
        $service->block($task, $this->user('u1'), 'b');
    }

    public function testDoneAndReviewCannotBeSetDirectly(): void
    {
        $service = $this->service();
        $task = (new DepartmentGrossanlassTask())->setId("gt0000000001");
        foreach ([DepartmentGrossanlassTask::STATUS_DONE, DepartmentGrossanlassTask::STATUS_REVIEW] as $status) {
            try {
                $service->assertStatusChangeAllowed($task, $status, true);
                self::fail('Direkter Wechsel auf ' . $status . ' muss abgelehnt werden');
            } catch (\InvalidArgumentException) {
                self::assertSame(DepartmentGrossanlassTask::STATUS_OPEN, $task->getStatus());
            }
        }
    }

    public function testReportAcceptReturnFlow(): void
    {
        $service = $this->service();
        $task = (new DepartmentGrossanlassTask())->setId("gt0000000001");

        // Fertigmeldung auch ohne Checkliste, Foto oder Kommentar; noch kein Abschluss.
        $event = $service->reportDone($task, $this->user('u1'), null);
        self::assertSame(DepartmentGrossanlassTask::STATUS_REVIEW, $task->getStatus());
        self::assertSame('reported', $event->getType());
        self::assertNull($event->getNote());

        // Rückgabe braucht eine Begründung und geht zurück auf «in Arbeit».
        try {
            $service->returnForRework($task, $this->user('u2'), ' ');
            self::fail('Begründung muss Pflicht sein');
        } catch (\InvalidArgumentException) {
        }
        $returned = $service->returnForRework($task, $this->user('u2'), 'Kante fehlt');
        self::assertSame(DepartmentGrossanlassTask::STATUS_PROGRESS, $task->getStatus());
        self::assertSame('Kante fehlt', $returned->getNote());

        // Abnahme erst nach erneuter Fertigmeldung; Melder und Abnehmer dürfen dieselbe Person sein.
        try {
            $service->accept($task, $this->user('u1'));
            self::fail('Abnahme nur für fertig gemeldete Aufgaben');
        } catch (\InvalidArgumentException) {
        }
        $service->reportDone($task, $this->user('u1'), 'fertig');
        $accepted = $service->accept($task, $this->user('u1'));
        self::assertSame(DepartmentGrossanlassTask::STATUS_DONE, $task->getStatus());
        self::assertSame('accepted', $accepted->getType());
    }

    public function testBlockedTaskCannotBeReportedDone(): void
    {
        $service = $this->service();
        $task = (new DepartmentGrossanlassTask())->setId("gt0000000001");
        $service->block($task, $this->user('u1'), 'Problem');
        $this->expectException(\InvalidArgumentException::class);
        $service->reportDone($task, $this->user('u1'), null);
    }

    public function testReviewedOrDoneTaskCanOnlyBeReopenedByApprover(): void
    {
        $service = $this->service();
        $task = (new DepartmentGrossanlassTask())->setId("gt0000000001");
        $service->reportDone($task, $this->user('u1'), null);
        $service->assertStatusChangeAllowed($task, DepartmentGrossanlassTask::STATUS_PROGRESS, true);
        $this->expectException(\RuntimeException::class);
        $service->assertStatusChangeAllowed($task, DepartmentGrossanlassTask::STATUS_PROGRESS, false);
    }
}
