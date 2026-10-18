<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassBauprojektService;
use App\Service\Grossanlass\GrossanlassHelperPoolService;
use App\Service\Grossanlass\GrossanlassStaffingAssignmentService;
use App\Service\Grossanlass\GrossanlassStaffingConflict;
use App\Service\Grossanlass\GrossanlassStaffingCoverageService;
use App\Service\Grossanlass\GrossanlassStaffingLocks;
use App\Service\Grossanlass\GrossanlassStaffingNeedService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Helferpool V1 gegen die echte Datenbank: Helfer, Verfügbarkeit, Personalbedarf, Zuteilung, Konflikte, zeitabhängige Deckung.
 * Läuft nur mit EMATCHEF_TEST_DB_URL gegen eine isolierte Datenbank (Name `val_*` oder `*_test`); jeder Test endet mit Rollback,
 * ausser dem Sperr-Test, der committete Zeilen braucht und sie selbst wieder entfernt.
 */
final class GrossanlassStaffingIntegrationTest extends TestCase
{
    private const D = 'deptsa000001';
    private const OTHER = 'deptsa000002';
    private const DAY = '2027-06-01';

    public static function url(): string
    {
        $url = getenv('EMATCHEF_TEST_DB_URL') ?: '';
        if ($url === '') {
            self::markTestSkipped('EMATCHEF_TEST_DB_URL nicht gesetzt (Integrationstest gegen isolierte DB).');
        }
        $dbName = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        if (!preg_match('/^val_|_test$/', $dbName) || $dbName === 'mvdb') {
            self::fail('Unsicherer Datenbankname: ' . $dbName);
        }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $url;
        (new Dotenv())->usePutenv(false)->loadEnv(dirname(__DIR__, 2) . '/.env', 'APP_ENV', 'test', ['test']);
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $url;

        return $url;
    }

    /** @param callable(Ctx): void $test */
    private function withDb(callable $test, bool $rollback = true): void
    {
        $url = self::url();
        FreshKernel::run(function ($kernel) use ($test, $rollback): void {
            $c = $kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $c->get('doctrine')->getManager();
            $conn = $em->getConnection();
            $conn->setNestTransactionsWithSavepoints(true);
            if ($rollback) {
                $conn->beginTransaction();
            }
            try {
                self::seed($conn);
                $em->clear();
                $test(new Ctx(
                    $em,
                    $conn,
                    $em->find(Department::class, self::D),
                    $em->find(Department::class, self::OTHER),
                    $em->find(User::class, 'ushsa00000hv'),
                    $em->find(User::class, 'ushsa0000sub'),
                    $em->find(User::class, 'ushsa00000mw'),
                    $c->get(GrossanlassHelperPoolService::class),
                    $c->get(GrossanlassStaffingAssignmentService::class),
                    $c->get(GrossanlassStaffingCoverageService::class),
                    $c->get(GrossanlassStaffingNeedService::class),
                    $c->get(GrossanlassBauprojektService::class),
                    $c->get(GrossanlassStaffingLocks::class),
                ));
            } finally {
                if ($rollback) {
                    $conn->rollBack();
                } else {
                    $conn->executeStatement('DELETE FROM department WHERE id IN (?, ?)', [self::D, self::OTHER]);
                    $conn->executeStatement('DELETE FROM "user" WHERE id LIKE ?', ['ushsa%']);
                    $conn->executeStatement('DELETE FROM profile WHERE id LIKE ?', ['pshsa%']);
                    $conn->executeStatement("DELETE FROM organisation WHERE id = 'orgsa0000001'");
                }
            }
        }, $url, [
            GrossanlassHelperPoolService::class, GrossanlassStaffingAssignmentService::class, GrossanlassStaffingCoverageService::class,
            GrossanlassStaffingNeedService::class, GrossanlassBauprojektService::class, GrossanlassStaffingLocks::class,
        ]);
    }

    public static function seed(Connection $conn): void
    {
        $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('orgsa0000001','Org',now(),now())");
        foreach ([self::D, self::OTHER] as $id) {
            $conn->executeStatement('INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES (?,?,?,now(),now(),false,true)', [$id, 'orgsa0000001', 'GA ' . $id]);
        }
        foreach (['hv' => 'ushsa00000hv', 'subhv' => 'ushsa0000sub', 'mw' => 'ushsa00000mw', 'dc' => 'ushsa00000dc', 'u' => 'ushsa000000u', '' => 'ushsa000nomb'] as $role => $uid) {
            $pid = 'pshsa' . substr($uid, 5);
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES (?,?, 'T','T','T','[\"ROLE_USER\"]',now(),now())", [$pid, "$uid@example.org"]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES (?,?, 'active','pw',true,now(),now())", [$uid, $pid]);
            if ($role !== '') { // '' = Benutzer ohne Mitgliedschaft im Grossanlass
                $conn->executeStatement('INSERT INTO membership (user_id,department_id,role,is_primary) VALUES (?,?,?,false)', [$uid, self::D, $role]);
            }
        }
        $conn->executeStatement("INSERT INTO \"group\" (id,department_id,name,sort_order,created_at,updated_at,window_start,window_end) VALUES ('grpsa0000001',?,'Bühne',0,now(),now(),'2027-05-31','2027-06-02')", [self::D]);
        $conn->executeStatement("INSERT INTO department_grossanlass_task (id,department_id,group_id,title,sort_order,created_at) VALUES ('gtsa00000001',?,'grpsa0000001','Aufbau',0,now())", [self::D]);
    }

    private static function at(string $hm, string $day = self::DAY): string
    {
        return $day . 'T' . $hm . ':00';
    }

    /** Helfer mit Verfügbarkeit (Default 07:00–18:00). */
    private static function newHelper(Ctx $x, string $name, bool $available = true, string $from = '07:00', string $to = '18:00'): string
    {
        $id = (string) $x->pool->create($x->dept, $x->hv, ['first_name' => $name, 'last_name' => 'Test'])['id'];
        if ($available) {
            $x->pool->addAvailability($x->dept, $x->hv, $id, self::at($from), self::at($to));
        }

        return $id;
    }

    private static function need(Ctx $x, int $required, string $from = '08:00', string $to = '12:00', string $label = ''): string
    {
        $task = $x->em->find(DepartmentGrossanlassTask::class, 'gtsa00000001');
        $group = $x->em->find(Group::class, 'grpsa0000001');

        return $x->needs->create($x->dept, $task, $group, ['required_count' => $required, 'label' => $label, 'starts_at' => self::at($from), 'ends_at' => self::at($to)])->getId();
    }

    private static function conflict(callable $fn, string $kind): GrossanlassStaffingConflict
    {
        try {
            $fn();
        } catch (GrossanlassStaffingConflict $e) {
            self::assertSame($kind, $e->getKind());
            self::assertSame('staffing_conflict', $e->toPayload()['code']);

            return $e;
        }
        self::fail("Erwartet: Konflikt «$kind»");
    }

    /** GA-Einstellungsreihe mit beiden Helferpool-Schaltern (Standard true). */
    public static function seedConfig(Connection $conn, bool $mwAllowed, bool $okAllowed): void
    {
        $conn->executeStatement(sprintf('INSERT INTO department_grossanlass_config (department_id, planned_event_start, staffing_mw_allowed, staffing_ok_allowed) VALUES (?, now(), %s, %s)', $mwAllowed ? 'TRUE' : 'FALSE', $okAllowed ? 'TRUE' : 'FALSE'), [self::D]);
    }

    public function testMwAllowedByDefaultAndDeniedWhenSwitchedOff(): void
    {
        $this->withDb(function (Ctx $x): void {
            $x->pool->create($x->dept, $x->mw, ['first_name' => 'Standard']);
            self::assertCount(1, $x->pool->list($x->dept, $x->mw));
        });
        $this->withDb(function (Ctx $x): void {
            self::seedConfig($x->conn, false, true);
            $x->em->clear();
            $dept = $x->em->find(Department::class, self::D);
            $mw = $x->em->find(User::class, 'ushsa00000mw');
            $hv = $x->em->find(User::class, 'ushsa00000hv');
            try {
                $x->pool->list($dept, $mw);
                self::fail('mw darf mit deaktiviertem Schalter nicht verwalten');
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
            $x->pool->create($dept, $hv, ['first_name' => 'Hv']);
            self::assertCount(1, $x->pool->list($dept, $hv));
        });
    }

    public function testOnlyHvAndSubhvManagePoolAssignmentsAndOverrides(): void
    {
        $this->withDb(function (Ctx $x): void {
            self::seedConfig($x->conn, false, false);
            $x->em->clear();
            $x->dept = $x->em->find(Department::class, self::D);
            $x->mw = $x->em->find(User::class, 'ushsa00000mw');
            foreach ([fn () => $x->pool->create($x->dept, $x->mw, ['first_name' => 'A']), fn () => $x->pool->list($x->dept, $x->mw),
                fn () => $x->assignments->assign($x->dept, $x->mw, ['need_id' => 'x', 'helper_id' => 'y']), fn () => $x->coverage->conflicts($x->dept, $x->mw)] as $call) {
                try {
                    $call();
                    self::fail('mw darf den Helferpool bei deaktivierten Schaltern nicht verwalten');
                } catch (\RuntimeException) {
                    self::assertTrue(true);
                }
            }
            $x->pool->create($x->dept, $x->hv, ['first_name' => 'Hv']);
            $x->pool->create($x->dept, $x->subhv, ['first_name' => 'Sub']);
            self::assertCount(2, $x->pool->list($x->dept, $x->hv));
        });
    }

    public function testHelperNeedsNoLoginIsDepartmentLocalAndArchivable(): void
    {
        $this->withDb(function (Ctx $x): void {
            $id = (string) $x->pool->create($x->dept, $x->hv, ['first_name' => 'Anna', 'last_name' => 'Muster', 'email' => 'Anna@Example.org', 'origin_department_id' => self::OTHER])['id'];
            $row = $x->pool->get($x->dept, $x->hv, $id);
            self::assertSame('anna@example.org', $row['email']);
            self::assertFalse($row['has_login']);
            self::assertSame(self::OTHER, $row['origin_department_id']);
            $x->pool->archive($x->dept, $x->hv, $id);
            self::assertCount(0, $x->pool->list($x->dept, $x->hv));
            self::assertCount(1, $x->pool->list($x->dept, $x->hv, ['status' => 'archived']));
            try {
                $x->pool->get($x->other, $x->hv, $id);
                self::fail('Helfer eines anderen Grossanlasses dürfen nicht lesbar sein');
            } catch (\Throwable $e) {
                self::assertTrue(true);
            }
            $this->expectException(\InvalidArgumentException::class);
            $x->pool->create($x->dept, $x->hv, ['first_name' => '', 'last_name' => '']);
        });
    }

    public function testAccountsWithHelperRoleAppearInPoolWithoutDuplicates(): void
    {
        $this->withDb(function (Ctx $x): void {
            $ext = (string) $x->pool->create($x->dept, $x->hv, ['first_name' => 'Extern', 'last_name' => 'Ohne Konto'])['id'];
            $pre = (string) $x->pool->create($x->dept, $x->hv, ['first_name' => 'Vor', 'last_name' => 'Handen', 'email' => 'ushsa000000u@example.org'])['id'];
            // Helferrolle als Flag zusätzlich zur Rolle (mw) und als Flag an einem Mitglied mit Rolle u
            $x->conn->executeStatement("UPDATE membership SET is_helper = TRUE WHERE department_id = ? AND user_id IN ('ushsa00000mw','ushsa000000u')", [self::D]);
            $x->em->clear();

            $rows = $x->pool->list($x->dept, $x->hv);
            self::assertCount(3, $rows);
            $byId = array_column($rows, null, 'id');
            self::assertFalse($byId[$ext]['has_login']);
            self::assertTrue($byId[$pre]['has_login'], 'vorhandener Datensatz wird verknüpft statt dupliziert');
            self::assertCount(1, array_filter($rows, static fn (array $r) => $r['has_login'] && $r['id'] !== $pre), 'mw neu angelegt');
            self::assertCount(3, $x->pool->list($x->dept, $x->hv), 'wiederholter Abgleich erzeugt keine Duplikate');

            // Rolle entfernt: archiviert, Verfügbarkeit/Historie bleiben, keine neue Zuteilung
            $mwId = (string) array_values(array_filter($rows, static fn (array $r) => $r['has_login'] && $r['id'] !== $pre))[0]['id'];
            $x->pool->addAvailability($x->dept, $x->hv, $mwId, self::at('07:00'), self::at('18:00'));
            $x->conn->executeStatement("UPDATE membership SET is_helper = FALSE WHERE department_id = ? AND user_id = 'ushsa00000mw'", [self::D]);
            $x->em->clear();
            self::assertCount(2, $x->pool->list($x->dept, $x->hv));
            $archived = $x->pool->list($x->dept, $x->hv, ['status' => 'archived']);
            self::assertSame([$mwId], array_column($archived, 'id'));
            self::assertCount(1, $x->pool->listAvailability($x->dept, $x->hv, $mwId));
            self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => self::need($x, 1), 'helper_id' => $mwId, 'starts_at' => self::at('08:00'), 'ends_at' => self::at('10:00')]), 'helper_archived');
        });
    }

    public function testAvailabilityIntervalsMayTouchButNotOverlap(): void
    {
        $this->withDb(function (Ctx $x): void {
            $h = self::newHelper($x, 'Av', false);
            $x->pool->addAvailability($x->dept, $x->hv, $h, self::at('08:00'), self::at('10:00'));
            $x->pool->addAvailability($x->dept, $x->hv, $h, self::at('10:00'), self::at('12:00'));
            $x->pool->addAvailability($x->dept, $x->hv, $h, self::at('14:00'), self::at('16:00'));
            self::assertCount(3, $x->pool->listAvailability($x->dept, $x->hv, $h));
            $this->expectException(\InvalidArgumentException::class);
            $x->pool->addAvailability($x->dept, $x->hv, $h, self::at('09:00'), self::at('11:00'));
        });
    }

    public function testPartTimeAssignmentsGiveTimeDependentCoverageWithoutNames(): void
    {
        $this->withDb(function (Ctx $x): void {
            $need = self::need($x, 1, '08:00', '12:00');
            $a = self::newHelper($x, 'Alpha');
            $b = self::newHelper($x, 'Bravo');

            $r1 = $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $a, 'starts_at' => self::at('08:00'), 'ends_at' => self::at('10:00')]);
            self::assertSame([], $r1['warnings']);
            $ov = $x->coverage->overview($x->dept);
            self::assertSame(0.5, $ov['needs'][0]['coverage']['fill_ratio']);
            self::assertSame('10:00', substr($ov['needs'][0]['coverage']['gaps'][0]['from'], 11, 5));

            $x->assignments->assign($x->dept, $x->subhv, ['need_id' => $need, 'helper_id' => $b, 'starts_at' => self::at('10:00'), 'ends_at' => self::at('12:00')]);
            $ov = $x->coverage->overview($x->dept);
            self::assertSame(1.0, $ov['needs'][0]['coverage']['fill_ratio']);
            self::assertSame([], $ov['needs'][0]['coverage']['gaps']);
            self::assertSame(2, $ov['needs'][0]['assigned_count']);
            self::assertSame(1.0, $ov['groups'][0]['fill_ratio']);
            self::assertStringNotContainsString('Alpha', json_encode($ov));
            self::assertStringNotContainsString('Bravo', json_encode($ov));

            $detail = $x->coverage->needDetail($x->dept, $x->hv, $need);
            self::assertSame(['Alpha Test', 'Bravo Test'], array_column($detail['assignments'], 'helper_name'));
            self::assertSame([], $x->coverage->conflicts($x->dept, $x->hv));
        });
    }

    public function testAssignmentNeedsAvailabilityAndThatIsNotOverridable(): void
    {
        $this->withDb(function (Ctx $x): void {
            $need = self::need($x, 1);
            $none = self::newHelper($x, 'Ohne', false);
            self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $none, 'override_reason' => 'egal']), GrossanlassStaffingConflict::KIND_NO_AVAILABILITY);
            $partial = self::newHelper($x, 'Teil', true, '08:00', '10:00');
            self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $partial]), GrossanlassStaffingConflict::KIND_NO_AVAILABILITY);
            $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $partial, 'starts_at' => self::at('08:00'), 'ends_at' => self::at('10:00')]);
        });
    }

    public function testDoubleBookingIsBlockedUnlessJustifiedAndIsLogged(): void
    {
        $this->withDb(function (Ctx $x): void {
            $n1 = self::need($x, 1, '08:00', '10:00');
            $n2 = self::need($x, 1, '09:00', '11:00');
            $h = self::newHelper($x, 'Doppelt');
            $first = $x->assignments->assign($x->dept, $x->hv, ['need_id' => $n1, 'helper_id' => $h])['assignment'];

            $e = self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => $n2, 'helper_id' => $h]), GrossanlassStaffingConflict::KIND_DOUBLE_BOOKING);
            self::assertSame($first['id'], $e->toPayload()['conflict']['conflicts'][0]['assignment_id']);
            // direkt anschliessend ist keine Überschneidung
            $n3 = self::need($x, 1, '10:00', '12:00');
            $x->assignments->assign($x->dept, $x->hv, ['need_id' => $n3, 'helper_id' => $h]);

            self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => $n2, 'helper_id' => $h, 'override_reason' => '   ']), GrossanlassStaffingConflict::KIND_DOUBLE_BOOKING);
            $res = $x->assignments->assign($x->dept, $x->subhv, ['need_id' => $n2, 'helper_id' => $h, 'starts_at' => self::at('09:00'), 'ends_at' => self::at('10:00'), 'override_reason' => 'Helfer pendelt zwischen Bühne und Bar']);
            self::assertTrue($res['assignment']['has_override']);
            self::assertSame('double_booking_override', $res['warnings'][0]['kind']);
            $log = $x->conn->fetchAllAssociative("SELECT action, reason, user_id FROM department_grossanlass_staffing_log WHERE assignment_id = ? ORDER BY action", [$res['assignment']['id']]);
            self::assertSame(['assigned', 'override'], array_column($log, 'action'));
            self::assertSame('Helfer pendelt zwischen Bühne und Bar', $log[1]['reason']);
            self::assertSame('ushsa0000sub', trim($log[1]['user_id']));
            $conflicts = $x->coverage->conflicts($x->dept, $x->hv);
            self::assertSame('double_booking', $conflicts[0]['kind']);
            self::assertTrue($conflicts[0]['excused']);
        });
    }

    public function testOverbookingWarnsButDoesNotBlockAndCancelFreesHelper(): void
    {
        $this->withDb(function (Ctx $x): void {
            $need = self::need($x, 1, '08:00', '10:00');
            $a = self::newHelper($x, 'A');
            $b = self::newHelper($x, 'B');
            $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $a]);
            $res = $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $b]);
            self::assertSame('overbooked', $res['warnings'][0]['kind']);
            self::assertTrue($x->coverage->overview($x->dept)['needs'][0]['coverage']['overbooked']);
            self::assertSame('overbooked', array_values(array_filter($x->coverage->conflicts($x->dept, $x->hv), fn ($c) => $c['kind'] === 'overbooked'))[0]['kind']);

            $other = self::need($x, 1, '08:00', '10:00');
            self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => $other, 'helper_id' => $b]), GrossanlassStaffingConflict::KIND_DOUBLE_BOOKING);
            $x->assignments->cancel($x->dept, $x->hv, $res['assignment']['id']);
            $x->assignments->cancel($x->dept, $x->hv, $res['assignment']['id']); // wiederholt: wirkungslos
            $x->assignments->assign($x->dept, $x->hv, ['need_id' => $other, 'helper_id' => $b]);
            self::assertSame(1, (int) $x->conn->fetchOne("SELECT count(*) FROM department_grossanlass_staffing_log WHERE assignment_id = ? AND action = 'cancelled'", [$res['assignment']['id']]));
        });
    }

    public function testUndatedNeedAndOutsideWindowAreRejected(): void
    {
        $this->withDb(function (Ctx $x): void {
            $task = $x->em->find(DepartmentGrossanlassTask::class, 'gtsa00000001');
            $group = $x->em->find(Group::class, 'grpsa0000001');
            $undated = $x->needs->create($x->dept, $task, $group, ['required_count' => 2, 'starts_at' => null, 'ends_at' => null])->getId();
            $h = self::newHelper($x, 'U');
            self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => $undated, 'helper_id' => $h]), GrossanlassStaffingConflict::KIND_NEED_UNDATED);
            self::assertFalse($x->coverage->overview($x->dept)['needs'][0]['dated']);
            self::assertSame('need_undated', $x->coverage->conflicts($x->dept, $x->hv)[0]['kind']);

            $need = self::need($x, 1, '08:00', '10:00');
            self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $h, 'starts_at' => self::at('07:00'), 'ends_at' => self::at('09:00')]), GrossanlassStaffingConflict::KIND_OUTSIDE_NEED_WINDOW);
        });
    }

    public function testArchivedHelperAndForeignDepartmentCannotBeAssigned(): void
    {
        $this->withDb(function (Ctx $x): void {
            $need = self::need($x, 1);
            $h = self::newHelper($x, 'Arch');
            $x->pool->archive($x->dept, $x->hv, $h);
            self::conflict(fn () => $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $h]), GrossanlassStaffingConflict::KIND_HELPER_ARCHIVED);

            $foreign = (string) $x->conn->fetchOne("SELECT 'ghsa00000001'");
            $x->conn->executeStatement("INSERT INTO department_grossanlass_helper (id,department_id,first_name,last_name,created_at,updated_at) VALUES (?,?, 'F','F',now(),now())", [$foreign, self::OTHER]);
            $this->expectException(\InvalidArgumentException::class);
            $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $foreign]);
        });
    }

    public function testAvailabilityCannotBeRemovedUnderAnAssignment(): void
    {
        $this->withDb(function (Ctx $x): void {
            $need = self::need($x, 1, '08:00', '10:00');
            $h = self::newHelper($x, 'Av');
            $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $h]);
            $av = $x->pool->listAvailability($x->dept, $x->hv, $h)[0]['id'];
            self::conflict(fn () => $x->pool->removeAvailability($x->dept, $x->hv, $av), GrossanlassStaffingConflict::KIND_IN_USE);
            self::conflict(fn () => $x->pool->updateAvailability($x->dept, $x->hv, $av, self::at('09:00'), self::at('12:00')), GrossanlassStaffingConflict::KIND_IN_USE);
            $x->pool->updateAvailability($x->dept, $x->hv, $av, self::at('07:30'), self::at('12:00'));
            self::assertCount(1, $x->pool->listAvailability($x->dept, $x->hv, $h));
        });
    }

    public function testNeedWithAssignmentsCannotBeDeletedOrMovedAway(): void
    {
        $this->withDb(function (Ctx $x): void {
            $need = self::need($x, 1, '08:00', '12:00');
            $h = self::newHelper($x, 'N');
            $x->assignments->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $h, 'starts_at' => self::at('09:00'), 'ends_at' => self::at('11:00')]);
            $row = $x->needs->find($x->dept, $need);
            self::conflict(fn () => $x->needs->delete($row), GrossanlassStaffingConflict::KIND_IN_USE);
            self::conflict(fn () => $x->needs->update($row, ['starts_at' => self::at('10:00'), 'ends_at' => self::at('12:00')]), GrossanlassStaffingConflict::KIND_OUTSIDE_NEED_WINDOW);
            self::conflict(fn () => $x->needs->update($row, ['starts_at' => null, 'ends_at' => null]), GrossanlassStaffingConflict::KIND_OUTSIDE_NEED_WINDOW);
        });
    }

    public function testTaskHelperNeedBundleKeepsFunctionsWithoutDoubleCounting(): void
    {
        $this->withDb(function (Ctx $x): void {
            $group = $x->em->find(Group::class, 'grpsa0000001');
            $task = $x->bauprojekt->createTask($x->dept, $x->mw, $group, [
                'title' => 'Zelt', 'starts_at' => self::at('08:00'), 'duration_minutes' => 120,
                'helper_need' => ['total' => 5, 'functions' => [['label' => 'Elektriker', 'count' => 2], ['label' => 'Aufbau', 'count' => 1]]],
            ]);
            self::assertSame(5, $task['helper_need']['total']);
            self::assertSame(2, $task['helper_need']['unspecified']);
            self::assertSame(['Elektriker', 'Aufbau'], array_column($task['helper_need']['functions'], 'label'));
            self::assertSame(5, (int) $x->conn->fetchOne('SELECT SUM(required_count) FROM department_grossanlass_staffing_need WHERE task_id = ?', [$task['id']]));

            // Funktionen werden aus dem Gesamtbedarf herausgenommen, nicht addiert
            $task = $x->bauprojekt->updateTask($x->dept, $x->mw, $group, $task['id'], ['helper_need' => ['total' => 4, 'functions' => [['label' => 'Elektriker', 'count' => 4]]]]);
            self::assertSame(4, $task['helper_need']['total']);
            self::assertSame(0, $task['helper_need']['unspecified']);
            self::assertSame(4, (int) $x->conn->fetchOne('SELECT SUM(required_count) FROM department_grossanlass_staffing_need WHERE task_id = ?', [$task['id']]));
            self::assertSame(4, $x->coverage->overview($x->dept)['totals']['required_total']);

            // Verschieben der Aufgabe zieht das Fenster des Bedarfs nach
            $x->bauprojekt->updateTask($x->dept, $x->mw, $group, $task['id'], ['starts_at' => self::at('13:00'), 'duration_minutes' => 60]);
            $window = $x->conn->fetchAssociative('SELECT starts_at, ends_at FROM department_grossanlass_staffing_need WHERE task_id = ?', [$task['id']]);
            self::assertSame('13:00', (new \DateTime($window['starts_at']))->format('H:i'));
            self::assertSame('14:00', (new \DateTime($window['ends_at']))->format('H:i'));
        });
    }

    public function testBauprojektNeedUsesHiddenDefaultTaskCreatedOnce(): void
    {
        $this->withDb(function (Ctx $x): void {
            $group = $x->em->find(Group::class, 'grpsa0000001');
            $x->bauprojekt->saveHelperNeed($x->dept, $x->mw, $group, ['total' => 6, 'functions' => [['label' => 'Koch', 'count' => 2]]]);
            $x->bauprojekt->createStaffingNeed($x->dept, $x->mw, $group, ['required_count' => 3, 'starts_at' => self::at('06:00'), 'ends_at' => self::at('07:00')]);
            self::assertSame(1, (int) $x->conn->fetchOne("SELECT count(*) FROM department_grossanlass_task WHERE group_id = 'grpsa0000001' AND is_default"));
            self::assertSame(['Aufbau'], array_column($x->bauprojekt->listTasks($x->dept, $x->mw, $group), 'title'), 'Standardaufgabe bleibt in der Aufgabenübersicht verborgen');
            $helperNeed = $x->bauprojekt->getHelperNeed($x->dept, $x->mw, $group);
            self::assertSame(6, $helperNeed['total']);
            self::assertSame(4, $helperNeed['unspecified']);
            self::assertSame(9, $helperNeed['from_tasks']['peak'], 'Bauprojekt-Bedarf (6) und Zusatzbedarf (3) überlappen zeitlich');
            $needs = $x->bauprojekt->listStaffingNeeds($x->dept, $x->mw, $group);
            self::assertCount(3, $needs);
            self::assertTrue($needs[0]['is_default_task']);
            // Gruppenfenster 31.5.–2.6. (inklusive) → intern bis 3.6. 00:00, exklusives Ende
            $x->needs->ensureDefaultTask($x->dept, $group);
            self::assertSame(1, (int) $x->conn->fetchOne("SELECT count(*) FROM department_grossanlass_task WHERE group_id = 'grpsa0000001' AND is_default"));
            self::assertSame('2027-06-03 00:00:00', $x->conn->fetchOne("SELECT max(ends_at) FROM department_grossanlass_staffing_need WHERE label = 'Koch'"));
        });
    }

    public function testHelperRowIsLockedWhileAssigning(): void
    {
        $this->withDb(function (Ctx $x): void {
            $h = self::newHelper($x, 'Lock');
            $u = parse_url((string) getenv('EMATCHEF_TEST_DB_URL'));
            $second = DriverManager::getConnection([
                'driver' => 'pdo_pgsql', 'host' => $u['host'], 'port' => $u['port'] ?? 5432, 'user' => $u['user'],
                'password' => $u['pass'] ?? '', 'dbname' => ltrim($u['path'], '/'),
            ]);
            $x->locks->transactional(function () use ($x, $h, $second): void {
                $x->locks->lockHelper(self::D, $h);
                $second->beginTransaction();
                try {
                    $second->fetchOne('SELECT id FROM department_grossanlass_helper WHERE id = ? FOR UPDATE NOWAIT', [$h]);
                    self::fail('Der Helfer muss für andere Transaktionen gesperrt sein');
                } catch (DriverException $e) {
                    self::assertSame('55P03', $e->getSQLState(), 'lock_not_available');
                } finally {
                    $second->rollBack();
                }
            });
            self::assertTrue(true);
        }, false);
    }
}

/** @internal Testkontext. */
final class Ctx
{
    public function __construct(
        public EntityManagerInterface $em,
        public Connection $conn,
        public Department $dept,
        public Department $other,
        public User $hv,
        public User $subhv,
        public User $mw,
        public GrossanlassHelperPoolService $pool,
        public GrossanlassStaffingAssignmentService $assignments,
        public GrossanlassStaffingCoverageService $coverage,
        public GrossanlassStaffingNeedService $needs,
        public GrossanlassBauprojektService $bauprojekt,
        public GrossanlassStaffingLocks $locks,
    ) {
    }
}
