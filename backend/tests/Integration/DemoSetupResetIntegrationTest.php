<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Reset\DemoResetService;
use App\Service\Demo\Reset\DemoSetupResetter;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use App\Service\DevEnvironmentService;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Tests\Wiring\FreshKernel;
use App\Util\DemoAccounts;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Demo-Reset «Grossanlass Einrichtung» gegen eine ISOLIERTE Test-Datenbank (EMATCHEF_TEST_DB_URL; Name `val_*` oder
 * `*_test`, nie `mvdb`). Jeder Test läuft in einer äusseren Transaktion, die zurückgerollt wird.
 */
final class DemoSetupResetIntegrationTest extends TestCase
{
    private const SETUP = DemoScenarioKey::GROSSANLASS_SETUP;

    /** @param callable(object): void $test */
    private function withDb(callable $test): void
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

        FreshKernel::run(function ($kernel) use ($test): void {
            $c = $kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $c->get('doctrine')->getManager();
            $conn = $em->getConnection();
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
            try {
                $registry = $c->get(DemoScenarioRegistry::class);
                $runner = $c->get(DemoScenarioRunner::class);
                foreach (DemoScenarioKey::all() as $key) {
                    $em->clear();
                    $runner->sync($registry->get($key));
                }
                $em->clear();
                $ctx = new class($em, $conn, $kernel, $c->get(DemoResetService::class), $c->get(DemoSetupResetter::class)) {
                    public function __construct(
                        public EntityManagerInterface $em,
                        public Connection $conn,
                        public object $kernel,
                        public DemoResetService $service,
                        public DemoSetupResetter $resetter,
                    ) {
                    }

                    public function deptId(string $key): string
                    {
                        return (string) $this->conn->fetchOne('SELECT id FROM department WHERE demo_scenario_key = ?', [$key]);
                    }

                    public function userId(string $account): string
                    {
                        return (string) $this->conn->fetchOne('SELECT u.id FROM "user" u JOIN profile p ON p.id = u.profile_id WHERE p.email = ?', [DemoAccounts::email($account)]);
                    }

                    public function user(string $account): User
                    {
                        $this->em->clear();
                        $u = $this->em->find(User::class, $this->userId($account));
                        TestCase::assertInstanceOf(User::class, $u);

                        return $u;
                    }
                };
                $test($ctx);
            } finally {
                $conn->rollBack();
            }
        }, $url, [DemoResetService::class, DemoScenarioRunner::class, DemoScenarioRegistry::class, DemoSetupResetter::class, DemoEnvironmentGuard::class, DevEnvironmentService::class, GrossanlassAccessService::class, AdminCapabilityChecker::class]);
    }

    /** Legt typische Einrichtungsänderungen im Setup-Department an. */
    private function mutate(object $c, string $setup): void
    {
        $conn = $c->conn;
        $mw = $c->userId('ga-mw');
        $conn->executeStatement("INSERT INTO \"group\" (id, department_id, name, sort_order, created_at, updated_at) VALUES ('rsttg0000001', ?, 'Zelte', 0, now(), now())", [$setup]);
        $conn->executeStatement("INSERT INTO \"group\" (id, department_id, name, parent_id, sort_order, created_at, updated_at) VALUES ('rsttg0000002', ?, 'Zeltbau', 'rsttg0000001', 0, now(), now())", [$setup]);
        $conn->executeStatement("INSERT INTO group_membership (user_id, group_id, role, created_at) VALUES (?, 'rsttg0000001', 'leader', now())", [$mw]);
        $conn->executeStatement("INSERT INTO address (id, department_id, type, name, created_at, updated_at) VALUES ('rstad0000001', ?, 'event', 'Testwiese', now(), now())", [$setup]);
        $conn->executeStatement("INSERT INTO join_request (id, user_id, department_id, status, created_at, updated_at) VALUES ('rstjr0000001', ?, ?, 'pending', now(), now())", [$c->userId('matwart'), $setup]);
        $conn->executeStatement("INSERT INTO department_grossanlass_place (id, department_id, name, public_code, created_at) VALUES ('rstpl0000001', ?, 'Pin', 'RSTPIN000001', now())", [$setup]);
        $conn->executeStatement(
            "UPDATE department_grossanlass_config SET location_text = 'Testwiese', notes = 'Notiz', guest_activity_type = 'event', has_guest_departments = true, struktur_modus = 'verschachtelt', status = 'published', published_at = now(), planned_event_start = '2031-01-01', planned_event_end = '2031-01-05', logistics_group_id = 'rsttg0000001', venue_address_id = 'rstad0000001', setup_released_at = now(), setup_released_by_user_id = ? WHERE department_id = ?",
            [$mw, $setup],
        );
        $conn->executeStatement("UPDATE department SET name = 'Mein eigener Name' WHERE id = ?", [$setup]);
        $conn->executeStatement("UPDATE membership SET role = 'u' WHERE department_id = ? AND user_id = ?", [$setup, $c->userId('ga-ok')]);
        $conn->executeStatement('DELETE FROM membership WHERE department_id = ? AND user_id = ?', [$setup, $c->userId('ga-spon')]);
        $conn->executeStatement("INSERT INTO membership (user_id, department_id, role, is_primary) VALUES (?, ?, 'l1', false)", [$c->userId('matwart'), $setup]);
        $c->em->clear();
    }

    /** @return array<string, mixed> Zustand des Setup-Departments, der nach einem Reset dem Seed entsprechen muss */
    private function setupState(object $c, string $setup): array
    {
        $conn = $c->conn;

        return [
            'name' => $conn->fetchOne('SELECT name FROM department WHERE id = ?', [$setup]),
            'config' => $conn->fetchAssociative('SELECT status, published_at IS NOT NULL AS published, struktur_modus, location_text, notes, guest_activity_type, has_guest_departments, invite_group_ids, logistics_group_id, venue_address_id, setup_released_at, setup_released_by_user_id, planned_event_start, planned_event_end FROM department_grossanlass_config WHERE department_id = ?', [$setup]),
            'groups' => (int) $conn->fetchOne('SELECT count(*) FROM "group" WHERE department_id = ?', [$setup]),
            'group_members' => (int) $conn->fetchOne('SELECT count(*) FROM group_membership gm JOIN "group" g ON g.id = gm.group_id WHERE g.department_id = ?', [$setup]),
            'addresses' => (int) $conn->fetchOne('SELECT count(*) FROM address WHERE department_id = ?', [$setup]),
            'places' => (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_place WHERE department_id = ?', [$setup]),
            'join_requests' => (int) $conn->fetchOne('SELECT count(*) FROM join_request WHERE department_id = ?', [$setup]),
            'roles' => $conn->fetchAllKeyValue('SELECT user_id, role FROM membership WHERE department_id = ? ORDER BY user_id', [$setup]),
            'period' => $conn->fetchAssociative("SELECT start_date, end_date FROM department_calendar_period WHERE department_id = ? AND label = 'grossanlass'", [$setup]),
        ];
    }

    /** @return array<string, mixed> alles ausserhalb des Setup-Departments, das ein Reset nicht anfassen darf */
    private function outside(object $c, string $setup): array
    {
        $conn = $c->conn;

        return [
            'other_departments' => $conn->fetchAllAssociative('SELECT id, name, demo_clock_offset_seconds, parent_id FROM department WHERE id <> ? ORDER BY id', [$setup]),
            'other_config' => $conn->fetchAllAssociative('SELECT * FROM department_grossanlass_config WHERE department_id <> ? ORDER BY department_id', [$setup]),
            'other_groups' => $conn->fetchAllAssociative('SELECT * FROM "group" WHERE department_id <> ? ORDER BY id', [$setup]),
            'other_memberships' => $conn->fetchAllAssociative('SELECT user_id, department_id, role, is_primary FROM membership WHERE department_id <> ? ORDER BY user_id, department_id', [$setup]),
            'users' => $conn->fetchAllAssociative('SELECT id, password, profile_id, state FROM "user" ORDER BY id'),
            'profiles' => $conn->fetchAllAssociative('SELECT id, email, roles, admin_capabilities FROM profile ORDER BY id'),
            'ledger_other' => (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE scenario_key <> 'grossanlass-setup'"),
        ];
    }

    public function testPreviewShowsTheRealChangesAndResetRestoresTheSeedState(): void
    {
        $this->withDb(function (object $c): void {
            $setup = $c->deptId(self::SETUP);
            $seed = $this->setupState($c, $setup);
            self::assertSame(0, $seed['groups']);
            self::assertNull($seed['config']['setup_released_at']);
            $this->mutate($c, $setup);
            $mutated = $this->setupState($c, $setup);
            self::assertNotSame($seed, $mutated);
            $outsideBefore = $this->outside($c, $setup);
            $mw = $c->user('ga-mw');

            $preview = $c->service->preview($mw, $setup);
            $plan = $preview['plan'];
            self::assertSame([], $plan['blocked']);
            self::assertCount(2, $plan['delete']['groups']);
            self::assertSame(1, $plan['delete']['group_members']);
            self::assertSame(1, $plan['delete']['places']);
            self::assertSame('Testwiese', $plan['delete']['addresses'][0]['name']);
            self::assertSame(1, $plan['delete']['join_requests']);
            foreach (['location_text', 'notes', 'status', 'struktur_modus', 'has_guest_departments', 'logistics_group_id', 'venue_address_id', 'planned_event_start'] as $field) {
                self::assertArrayHasKey($field, $plan['restore']['config'], $field);
            }
            $managed = implode(' | ', $plan['restore']['managed']);
            self::assertStringContainsString(self::SETUP . ':department', $managed);
            self::assertStringContainsString(self::SETUP . ':config', $managed);
            self::assertStringContainsString(self::SETUP . ':membership:ga-ok', $managed);
            self::assertSame(1, $plan['restore']['recreated'], 'gelöschte Seed-Mitgliedschaft ga-spon wird neu angelegt');
            self::assertCount(1, $plan['keep']['memberships_extra'], 'fremde Mitgliedschaft bleibt erhalten und wird gemeldet');

            // Die Vorschau schreibt nichts
            $c->em->clear();
            self::assertSame($mutated, $this->setupState($c, $setup));
            self::assertSame($outsideBefore, $this->outside($c, $setup));

            $result = $c->service->execute($mw, $setup, $preview['plan_hash'], self::SETUP);
            self::assertSame($preview['plan_hash'], $result['plan_hash']);
            $c->em->clear();
            $after = $this->setupState($c, $setup);

            self::assertSame(0, $after['groups']);
            self::assertSame(0, $after['group_members']);
            self::assertSame(0, $after['addresses']);
            self::assertSame(0, $after['places']);
            self::assertSame(0, $after['join_requests']);
            self::assertSame($seed['name'], $after['name']);
            self::assertNull($after['config']['setup_released_at']);
            self::assertNull($after['config']['setup_released_by_user_id']);
            self::assertSame('draft', $after['config']['status']);
            self::assertFalse($after['config']['published']);
            self::assertSame('offen', $after['config']['struktur_modus']);
            self::assertSame('', $after['config']['location_text']);
            self::assertSame('', $after['config']['notes']);
            self::assertSame($seed['config']['guest_activity_type'], $after['config']['guest_activity_type']);
            self::assertFalse($after['config']['has_guest_departments']);
            self::assertNull($after['config']['logistics_group_id']);
            self::assertNull($after['config']['venue_address_id']);
            self::assertSame((new \DateTime('today'))->modify('+7 days')->format('Y-m-d'), substr((string) $after['config']['planned_event_start'], 0, 10));
            // Seed-Rollen wiederhergestellt, Seed-Mitgliedschaft neu angelegt, fremde Mitgliedschaft unverändert
            self::assertSame('dc', $after['roles'][$c->userId('ga-ok')]);
            self::assertSame($seed['roles'][$c->userId('ga-spon')], $after['roles'][$c->userId('ga-spon')]);
            self::assertSame('l1', $after['roles'][$c->userId('matwart')]);
            unset($after['roles'][$c->userId('matwart')]);
            self::assertSame($seed['roles'], $after['roles']);
            // nichts ausserhalb des Setup-Departments (Konten, Passwörter, Profile, andere Departments, Szenarien)
            self::assertSame($outsideBefore, $this->outside($c, $setup));
            self::assertSame([], $c->resetter->departmentScopedTables() === [] ? ['schema'] : []);
        });
    }

    public function testSecondResetIsIdempotent(): void
    {
        $this->withDb(function (object $c): void {
            $setup = $c->deptId(self::SETUP);
            $this->mutate($c, $setup);
            $mw = $c->user('ga-mw');
            $first = $c->service->preview($mw, $setup);
            $c->service->execute($mw, $setup, $first['plan_hash'], self::SETUP);
            $c->em->clear();
            $afterFirst = $this->setupState($c, $setup);

            $second = $c->service->preview($c->user('ga-mw'), $setup);
            self::assertSame([], $second['plan']['delete']['groups']);
            self::assertSame([], $second['plan']['restore']['config']);
            self::assertSame([], $second['plan']['restore']['managed']);
            $c->service->execute($c->user('ga-mw'), $setup, $second['plan_hash'], self::SETUP);
            $c->em->clear();
            self::assertSame($afterFirst, $this->setupState($c, $setup));
        });
    }

    public function testBlockedByUnknownDataChangesNothing(): void
    {
        $this->withDb(function (object $c): void {
            $setup = $c->deptId(self::SETUP);
            $this->mutate($c, $setup);
            $c->conn->executeStatement("INSERT INTO inventory_task (id, department_id, title, status, lines_json, created_at, updated_at) VALUES ('rstit0000001', ?, 'Fremde Inventur', 'open', '[]', now(), now())", [$setup]);
            $before = $this->setupState($c, $setup);
            $mw = $c->user('ga-mw');

            $preview = $c->service->preview($mw, $setup);
            self::assertNotSame([], $preview['plan']['blocked']);
            self::assertStringContainsString('inventory_task', implode(' ', $preview['plan']['blocked']));
            try {
                $c->service->execute($c->user('ga-mw'), $setup, $preview['plan_hash'], self::SETUP);
                self::fail('Blockierter Reset wurde ausgeführt');
            } catch (DemoScenarioException $e) {
                self::assertStringContainsString('blockiert', $e->getMessage());
            }
            $c->em->clear();
            self::assertSame($before, $this->setupState($c, $setup), 'kein Teil-Reset');
            self::assertSame(1, (int) $c->conn->fetchOne('SELECT count(*) FROM inventory_task WHERE department_id = ?', [$setup]));
        });
    }

    public function testChangedStateAfterPreviewAbortsWithoutPartialReset(): void
    {
        $this->withDb(function (object $c): void {
            $setup = $c->deptId(self::SETUP);
            $this->mutate($c, $setup);
            $mw = $c->user('ga-mw');
            $preview = $c->service->preview($mw, $setup);

            // zwischen Vorschau und Bestätigung kommt ein weiteres Ressort dazu
            $c->conn->executeStatement("INSERT INTO \"group\" (id, department_id, name, sort_order, created_at, updated_at) VALUES ('rsttg0000009', ?, 'Neu', 0, now(), now())", [$setup]);
            $before = $this->setupState($c, $setup);
            try {
                $c->service->execute($c->user('ga-mw'), $setup, $preview['plan_hash'], self::SETUP);
                self::fail('Reset trotz geändertem Datenstand');
            } catch (DemoScenarioException $e) {
                self::assertStringContainsString('geändert', $e->getMessage());
            }
            $c->em->clear();
            self::assertSame($before, $this->setupState($c, $setup), 'vollständiger Rollback, kein Teil-Reset');

            // falsche Bestätigung
            $this->expectException(DemoScenarioException::class);
            $c->service->execute($c->user('ga-mw'), $setup, $preview['plan_hash'], 'falsch');
        });
    }

    public function testOnlySupportedDemoDepartmentsAuthorizedUsersAndAllowedEnvironments(): void
    {
        $this->withDb(function (object $c): void {
            $setup = $c->deptId(self::SETUP);
            $camp = $c->deptId(DemoScenarioKey::GROSSANLASS_CAMP);
            $mw = $c->user('ga-mw');

            self::assertTrue($c->service->status($mw, $setup)['supported']);
            self::assertSame(self::SETUP, $c->service->status($mw, $setup)['scenario']);
            // andere Demo-Szenarien
            foreach ([DemoScenarioKey::GROSSANLASS_CAMP, DemoScenarioKey::GROSSANLASS_EVENT, DemoScenarioKey::MATERIALVERWALTUNG] as $key) {
                $other = $c->deptId($key);
                self::assertFalse($c->service->status($c->user('ga-mw'), $other)['supported'], $key);
                $this->assertRejected(fn () => $c->service->preview($c->user('ga-mw'), $other), $key);
                $this->assertRejected(fn () => $c->service->execute($c->user('ga-mw'), $other, 'x', $key), $key);
            }
            // Nicht-Demo-Department und unbekannte ID
            $c->conn->executeStatement("INSERT INTO organisation (id, name, created_at, updated_at) VALUES ('rstorg000001', 'Echt', now(), now())");
            $c->conn->executeStatement("INSERT INTO department (id, organisation_id, name, created_at, updated_at, demo_mode, is_grossanlass) VALUES ('rstreal00001', 'rstorg000001', 'Echtes GA', now(), now(), false, true)");
            $this->assertRejected(fn () => $c->service->preview($c->user('ga-mw'), 'rstreal00001'), 'nicht-demo');
            $this->assertRejected(fn () => $c->service->preview($c->user('ga-mw'), 'gibtsnicht01'), 'unbekannt');
            // nicht berechtigte Rollen im Setup-Department
            foreach (['ga-helfer', 'ga-bereich', 'ga-cmw', 'ga-komm', 'ga-lw'] as $account) {
                self::assertFalse($c->service->status($c->user($account), $setup)['supported'], $account);
                $this->assertRejected(fn () => $c->service->preview($c->user($account), $setup), $account);
            }
            self::assertTrue($c->service->status($c->user('ga-ok'), $setup)['supported'], 'OK-Leitung darf zurücksetzen');
            // Benutzer ohne Mitgliedschaft
            self::assertFalse($c->service->status($c->user('camp-mw'), $setup)['supported']);
            self::assertNotSame($setup, $camp);
        });
    }

    public function testProductionStagingAndUnknownEnvironmentsAreRefused(): void
    {
        $this->withDb(function (object $c): void {
            $setup = $c->deptId(self::SETUP);
            $kernel = $c->kernel;
            $container = $kernel->getContainer();
            $devTools = $container->get(DevEnvironmentService::class);
            foreach (['production' => false, 'staging' => false, 'develop' => false, 'weiss-nicht' => false] as $name => $destructive) {
                $service = new DemoResetService(
                    $c->em,
                    new DemoEnvironmentGuard($kernel, $devTools, $name, $destructive),
                    $container->get(DemoScenarioRegistry::class),
                    $container->get(DemoScenarioRunner::class),
                    $container->get(GrossanlassAccessService::class),
                    $container->get(AdminCapabilityChecker::class),
                );
                $mw = $c->user('ga-mw');
                self::assertFalse($service->status($mw, $setup)['supported'], $name);
                $this->assertRejected(fn () => $service->preview($c->user('ga-mw'), $setup), $name);
                $this->assertRejected(fn () => $service->execute($c->user('ga-mw'), $setup, 'x', self::SETUP), $name);
            }
            // Develop nur mit ausdrücklicher Freigabe
            $develop = new DemoResetService(
                $c->em,
                new DemoEnvironmentGuard($kernel, $devTools, 'develop', true),
                $container->get(DemoScenarioRegistry::class),
                $container->get(DemoScenarioRunner::class),
                $container->get(GrossanlassAccessService::class),
                $container->get(AdminCapabilityChecker::class),
            );
            self::assertTrue($develop->status($c->user('ga-mw'), $setup)['supported']);
            // Es wurde nichts gelöscht
            self::assertSame(9, (int) $c->conn->fetchOne('SELECT count(*) FROM membership WHERE department_id = ?', [$setup]));
        });
    }

    public function testEveryDepartmentScopedTableIsClassified(): void
    {
        $this->withDb(function (object $c): void {
            // Unbekannte Tabellen sind zur Laufzeit blockierend (sicher); der Test hält die Klassifizierung bewusst aktuell.
            $classified = array_merge(DemoSetupResetter::keptTables(), DemoSetupResetter::resetTables());
            $known = [
                // blockierend, weil sie nicht zum Einrichtungsstand gehören
                'accounting_acquisition_follow_up', 'accounting_booking', 'accounting_budget_line', 'activity_grossanlass_procurement_category', 'activity_grossanlass_procurement_finance',
                'activity_grossanlass_procurement_line', 'activity_surplus_report', 'batch_storage_allocation', 'department_grossanlass_budget',
                'department_grossanlass_charge_movement', 'department_grossanlass_commitment', 'department_grossanlass_cost', 'department_grossanlass_einsatz',
                'department_grossanlass_gmail_account', 'department_grossanlass_gmail_unmatched', 'department_grossanlass_guest_share', 'department_grossanlass_inquiry',
                'department_grossanlass_mail_template', 'department_grossanlass_map', 'department_grossanlass_pack', 'department_grossanlass_participant', 'department_grossanlass_task',
                'department_grossanlass_unterlager', 'department_grossanlass_vehicle_need', 'department_grossanlass_workshop_case', 'department_print_preset', 'department_repair_template',
                'department_vehicle', 'external_structure_identity', 'inbox_message', 'inventory_task', 'material_item', 'material_template', 'onboarding_sandbox_state', 'print_layout',
                'print_task_item', 'public_code', 'public_found_item_message', 'storage_rack', 'supplier_delivery', 'workshop_ticket',
            ];
            $unknown = array_diff($c->resetter->departmentScopedTables(), $classified, $known);
            self::assertSame([], array_values($unknown), 'Neue Tabelle mit Department-Bezug: im DemoSetupResetter als BEHALTEN oder ZURÜCKSETZEN einordnen, sonst bleibt sie blockierend (sicher).');
        });
    }

    private function assertRejected(callable $call, string $label): void
    {
        try {
            $call();
        } catch (DemoScenarioException) {
            return;
        }
        self::fail('Nicht abgelehnt: ' . $label);
    }
}
