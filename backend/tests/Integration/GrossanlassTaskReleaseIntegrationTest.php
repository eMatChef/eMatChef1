<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassBauprojektService;
use App\Service\Grossanlass\GrossanlassUebersichtService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Freigaberegel der operativen Übersicht gegen die echte Datenbank: Helfer erhalten Arbeiten und Fahraufträge erst nach
 * der Freigabe, Planungsrollen sehen weiterhin alle Status. Läuft nur mit EMATCHEF_TEST_DB_URL; jeder Test endet mit Rollback.
 */
final class GrossanlassTaskReleaseIntegrationTest extends TestCase
{
    private const D = 'deptsa000001';
    private const HELPER = 'ushsa000000u';
    private const PLANNER = 'ushsa00000mw';

    /** @param callable(GrossanlassBauprojektService, GrossanlassUebersichtService, EntityManagerInterface, Department): void $test */
    private function withServices(callable $test): void
    {
        $url = GrossanlassStaffingIntegrationTest::url();
        FreshKernel::run(function ($kernel) use ($test): void {
            $c = $kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $c->get('doctrine')->getManager();
            $conn = $em->getConnection();
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
            try {
                GrossanlassStaffingIntegrationTest::seed($conn);
                $em->clear();
                $department = $em->find(Department::class, self::D);
                $test($c->get(GrossanlassBauprojektService::class), $c->get(GrossanlassUebersichtService::class), $em, $department);
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassBauprojektService::class, GrossanlassUebersichtService::class]);
    }

    private static function taskIds(array $rows): array
    {
        return array_map(static fn (array $row) => trim((string) $row['id']), $rows);
    }

    public function testHelperSeesWorkOnlyAfterRelease(): void
    {
        $this->withServices(function (GrossanlassBauprojektService $tasks, $uebersicht, EntityManagerInterface $em, Department $department): void {
            $conn = $em->getConnection();
            $helper = $em->find(User::class, self::HELPER);
            $planner = $em->find(User::class, self::PLANNER);
            $conn->executeStatement("UPDATE department_grossanlass_task SET assignee_user_id = ? WHERE id = 'gtsa00000001'", [self::HELPER]);

            foreach ([null, 'planned', 'quoted', 'aborted'] as $status) {
                $conn->executeStatement("UPDATE \"group\" SET build_status = ? WHERE id = 'grpsa0000001'", [$status]);
                $em->clear();
                $department = $em->find(Department::class, self::D);
                self::assertSame([], $tasks->listAllTasks($department, $em->find(User::class, self::HELPER)), 'Helfer, Status ' . var_export($status, true));
                $planned = $tasks->listAllTasks($department, $em->find(User::class, self::PLANNER));
                self::assertSame(['gtsa00000001'], self::taskIds($planned), 'Planung, Status ' . var_export($status, true));
                self::assertFalse($planned[0]['released']);
            }

            foreach (['build', 'use', 'teardown'] as $status) {
                $conn->executeStatement("UPDATE \"group\" SET build_status = ? WHERE id = 'grpsa0000001'", [$status]);
                $em->clear();
                $department = $em->find(Department::class, self::D);
                $rows = $tasks->listAllTasks($department, $em->find(User::class, self::HELPER));
                self::assertSame(['gtsa00000001'], self::taskIds($rows), 'Helfer, Status ' . $status);
                self::assertTrue($rows[0]['released']);
            }
        });
    }

    public function testReleasedTaskIsListedWithoutHelperAndMarkedMineAfterStaffing(): void
    {
        $this->withServices(function (GrossanlassBauprojektService $tasks, $uebersicht, EntityManagerInterface $em, Department $department): void {
            $conn = $em->getConnection();
            $conn->executeStatement("UPDATE \"group\" SET build_status = 'build' WHERE id = 'grpsa0000001'");
            $conn->executeStatement("UPDATE department_grossanlass_task SET assignee_user_id = NULL WHERE id = 'gtsa00000001'");
            $em->clear();
            $department = $em->find(Department::class, self::D);

            // Freigegeben, aber niemand zugeteilt: für Helfer und Planung sichtbar, nicht unter «Meine Einsätze».
            foreach ([self::HELPER, self::PLANNER] as $userId) {
                $rows = $tasks->listAllTasks($department, $em->find(User::class, $userId));
                self::assertSame(['gtsa00000001'], self::taskIds($rows), $userId);
                self::assertTrue($rows[0]['released']);
                self::assertFalse($rows[0]['assigned_to_me'], $userId);
            }

            // Helferzuteilung über den bestehenden Helferpool: Aufgabe erscheint für den eingeteilten Benutzer als «mein» Einsatz.
            $conn->executeStatement("INSERT INTO department_grossanlass_helper (id,department_id,first_name,last_name,user_id,created_at,updated_at) VALUES ('hesa00000001',?, 'Hans','Helfer',?,now(),now())", [self::D, self::HELPER]);
            $conn->executeStatement("INSERT INTO department_grossanlass_staffing_need (id,department_id,task_id,created_at,updated_at,starts_at,ends_at) VALUES ('nesa00000001',?, 'gtsa00000001',now(),now(),'2027-06-01 08:00:00','2027-06-01 12:00:00')", [self::D]);
            $conn->executeStatement("INSERT INTO department_grossanlass_staffing_assignment (id,department_id,need_id,helper_id,starts_at,ends_at,created_at,updated_at) VALUES ('asa000000001',?, 'nesa00000001','hesa00000001','2027-06-01 08:00:00','2027-06-01 12:00:00',now(),now())", [self::D]);
            $em->clear();
            $department = $em->find(Department::class, self::D);

            self::assertTrue($tasks->listAllTasks($department, $em->find(User::class, self::HELPER))[0]['assigned_to_me']);
            self::assertFalse($tasks->listAllTasks($department, $em->find(User::class, self::PLANNER))[0]['assigned_to_me']);

            // Freigabe und Zuteilung sind unabhängig: Ohne Freigabe bleibt die Zuteilung bestehen, die Aufgabe aber für den Helfer verborgen.
            $conn->executeStatement("UPDATE \"group\" SET build_status = 'planned' WHERE id = 'grpsa0000001'");
            $em->clear();
            $department = $em->find(Department::class, self::D);
            self::assertSame([], $tasks->listAllTasks($department, $em->find(User::class, self::HELPER)));
        });
    }

    public function testHelperCannotReachUnreleasedTaskThroughGroupEndpoints(): void
    {
        $this->withServices(function (GrossanlassBauprojektService $tasks, $uebersicht, EntityManagerInterface $em, Department $department): void {
            $conn = $em->getConnection();
            $conn->executeStatement("UPDATE \"group\" SET build_status = 'planned', grossanlass_kind = 'auftrag' WHERE id = 'grpsa0000001'");
            $conn->executeStatement("UPDATE department_grossanlass_task SET assignee_user_id = ? WHERE id = 'gtsa00000001'", [self::HELPER]);
            $em->clear();
            $department = $em->find(Department::class, self::D);
            $group = $em->find(\App\Entity\Group::class, 'grpsa0000001');
            $helper = $em->find(User::class, self::HELPER);

            try {
                $tasks->taskDependencies($department, $helper, $group, 'gtsa00000001');
                self::fail('Unfreigegebene Aufgabe darf für Helfer nicht abrufbar sein');
            } catch (\Throwable $e) {
                self::assertStringNotContainsString('gtsa00000001', $e->getMessage());
            }
            self::assertSame(['gtsa00000001'], self::taskIds($tasks->taskDependencies($department, $em->find(User::class, self::PLANNER), $group, 'gtsa00000001') ? $tasks->listTasks($department, $em->find(User::class, self::PLANNER), $group) : []));
        });
    }

    public function testHelperSeesTripsOnlyWhenReleased(): void
    {
        $this->withServices(function ($tasks, GrossanlassUebersichtService $uebersicht, EntityManagerInterface $em, Department $department): void {
            $conn = $em->getConnection();
            $insert = "INSERT INTO department_grossanlass_einsatz (id,department_id,kind,qty,starts_at,ends_at,status,who,chauffeur_user_id,delivery,trip_released_at,created_at,updated_at)
                       VALUES (?,?, 'einsatz',1,'2027-06-01 08:00:00','2027-06-01 10:00:00',?, 'Transport',?, 'trip',?,now(),now())";
            $conn->executeStatement($insert, ['etsa00000001', self::D, 'planned', self::HELPER, null]);
            $conn->executeStatement($insert, ['etsa00000002', self::D, 'pending_approval', self::HELPER, '2027-05-31 12:00:00']);
            $conn->executeStatement($insert, ['etsa00000003', self::D, 'planned', self::HELPER, '2027-05-31 12:00:00']);
            $conn->executeStatement($insert, ['etsa00000004', self::D, 'issued', self::HELPER, null]);
            $em->clear();
            $department = $em->find(Department::class, self::D);

            $ids = static fn (array $rows) => array_map(static fn (array $row) => trim((string) $row['id']), $rows);
            $mine = $uebersicht->myEinsaetze($department, $em->find(User::class, self::HELPER));
            $released = $ids($mine['fahrauftraege']);
            sort($released);
            self::assertSame(['etsa00000003', 'etsa00000004'], $released);

            $all = $uebersicht->overview($department, $em->find(User::class, self::PLANNER));
            self::assertCount(4, array_filter($all['einsaetze'], static fn (array $row) => str_starts_with(trim((string) $row['id']), 'etsa')));
        });
    }
}
