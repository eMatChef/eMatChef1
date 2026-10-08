<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Demo\Organisation\DemoOrganisationCatalog;
use App\Service\Demo\Organisation\ManagedSeedApplier;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use App\Service\Demo\Scenario\SeedResult;
use App\Tests\Wiring\FreshKernel;
use App\Util\DemoAccounts;
use App\Util\IdGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Integrationstest gegen eine ISOLIERTE Test-Datenbank (Migrationen eingespielt). Läuft nur, wenn
 * EMATCHEF_TEST_DB_URL gesetzt ist (Datenbankname muss mit «val_» beginnen oder auf «_test» enden; nie mvdb).
 * Jeder Test läuft in einer äusseren Transaktion, die am Ende zurückgerollt wird.
 *
 * Beispiel: EMATCHEF_TEST_DB_URL='postgresql://val:val@127.0.0.1:55432/val_empty?serverVersion=16&charset=utf8' vendor/bin/phpunit tests/Integration
 */
final class DemoOrganisationSeedIntegrationTest extends TestCase
{
    private const TABLES = ['organisation', 'department', '"group"', 'group_membership', 'membership', '"user"', 'profile', 'demo_seed_record'];

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
            $conn->beginTransaction();
            try {
                $test($c->get(DemoScenarioRunner::class), $c->get(DemoScenarioRegistry::class), $em, $conn, $c->get(DemoOrganisationCatalog::class), $c);
            } finally {
                $conn->rollBack();
            }
        }, $url, [DemoScenarioRunner::class, DemoScenarioRegistry::class, DemoOrganisationCatalog::class, \App\Service\Demo\Legacy\LegacyDemoRename::class, \App\Service\Bootstrap\DemoGrossanlassSeedService::class]);
    }

    /** @return array<string, int> */
    private function counts(Connection $conn): array
    {
        $out = [];
        foreach (self::TABLES as $t) {
            $out[$t] = (int) $conn->fetchOne("SELECT count(*) FROM $t");
        }

        return $out;
    }

    private function syncAll(DemoScenarioRunner $runner, DemoScenarioRegistry $registry, ?EntityManagerInterface $em = null): array
    {
        $results = [];
        foreach (DemoScenarioKey::all() as $key) {
            $em?->clear();
            $results[$key] = $runner->sync($registry->get($key));
        }

        return $results;
    }

    private function realWorld(EntityManagerInterface $em): array
    {
        $conn = $em->getConnection();
        $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('realorg00001','Echte Abteilung',now(),now())");
        $conn->executeStatement("INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES ('realdept0001','realorg00001','Echtes Department',now(),now(),false,false)");
        $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('realprof0001','echt@example.org','Eva','Echt','Eva','[\"ROLE_USER\"]',now(),now())");

        return [
            $conn->fetchAllAssociative("SELECT * FROM department WHERE id='realdept0001'"),
            $conn->fetchAllAssociative("SELECT * FROM organisation WHERE id='realorg00001'"),
            $conn->fetchAllAssociative("SELECT * FROM profile WHERE id='realprof0001'"),
        ];
    }

    public function testFirstInstallationRepeatedSyncAndRealDataIsUntouched(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn, DemoOrganisationCatalog $catalog): void {
            $real = $this->realWorld($em);
            $before = $this->counts($conn);

            $first = $this->syncAll($runner, $registry, $em);
            $after = $this->counts($conn);
            foreach ($first as $key => $result) {
                self::assertSame(SeedResult::OK, $result->status, $key);
                self::assertSame([], $result->notes, $key);
            }
            self::assertSame($before['organisation'] + 3, $after['organisation']);
            $extraDepartments = 0;
            foreach (DemoScenarioKey::all() as $key) {
                $extraDepartments += \count($catalog->scenario($key)['departments'] ?? []);
            }
            self::assertSame($before['department'] + 3 + $extraDepartments, $after['department']);
            self::assertSame(3, (int) $conn->fetchOne('SELECT count(*) FROM department WHERE demo_scenario_key IS NOT NULL AND demo_mode = true'));
            $accounts = [];
            $memberships = 0;
            $expectedGroups = 0;
            foreach (DemoScenarioKey::all() as $key) {
                $members = $catalog->scenario($key)['members'];
                $memberships += \count($members);
                $expectedGroups += \count($catalog->scenario($key)['groups']);
                foreach ($members as $m) {
                    $accounts[$m['account']] = true;
                }
            }
            // Demo-Benutzer: jedes Konto genau einmal (auch wenn in mehreren Departments), plus Lieferant
            $expectedUsers = \count($accounts) + \count($catalog->sharedAccounts());
            self::assertSame($before['"user"'] + $expectedUsers, $after['"user"']);
            self::assertSame($before['"group"'] + $expectedGroups, $after['"group"']);
            self::assertSame($memberships, $after['membership']);
            self::assertSame($expectedUsers, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE scenario_key='demo-users'"));
            self::assertSame(1, (int) $conn->fetchOne('SELECT count(*) FROM profile WHERE email=?', [DemoAccounts::email('supplier')]));
            // Globale Admins sind nur dort Mitglied, wo der Katalog es ausdrücklich vorsieht, und nur einmal vorhanden
            self::assertSame(2, (int) $conn->fetchOne("SELECT count(*) FROM membership m JOIN \"user\" u ON u.id=m.user_id JOIN profile p ON p.id=u.profile_id WHERE p.email=?", [DemoAccounts::email('superadmin')]));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM membership m JOIN \"user\" u ON u.id=m.user_id JOIN profile p ON p.id=u.profile_id WHERE m.is_primary AND p.email=?", [DemoAccounts::email('superadmin')]));
            // Event/Camp sind Grossanlass-Departments, Materialverwaltung nicht
            self::assertTrue((bool) $conn->fetchOne("SELECT is_grossanlass FROM department WHERE demo_scenario_key='grossanlass-camp'"));
            self::assertFalse((bool) $conn->fetchOne("SELECT is_grossanlass FROM department WHERE demo_scenario_key='materialverwaltung'"));
            // Echte Daten unverändert
            self::assertSame($real[0], $conn->fetchAllAssociative("SELECT * FROM department WHERE id='realdept0001'"));
            self::assertSame($real[1], $conn->fetchAllAssociative("SELECT * FROM organisation WHERE id='realorg00001'"));
            self::assertSame($real[2], $conn->fetchAllAssociative("SELECT * FROM profile WHERE id='realprof0001'"));
            // verify ist sauber
            foreach (DemoScenarioKey::all() as $key) {
                $em->clear();
                self::assertSame([], $runner->verify($registry->get($key)), $key);
            }

            // Wiederholter Sync: keine neuen Zeilen, keine Notizen, nichts verändert
            $snapshot = $conn->fetchAllAssociative('SELECT * FROM department ORDER BY id');
            $again = $this->syncAll($runner, $registry, $em);
            self::assertSame($after, $this->counts($conn));
            self::assertSame($snapshot, $conn->fetchAllAssociative('SELECT * FROM department ORDER BY id'));
            foreach ($again as $key => $result) {
                self::assertSame([], $result->notes, $key);
                self::assertSame(0, $result->created, $key);
            }
        });
    }

    /** @return array<string, list<string>> Konto => ["<Department-Name>=<Rolle>", …] */
    private function rolesByAccount(Connection $conn, array $accounts): array
    {
        $out = [];
        foreach ($accounts as $account) {
            $out[$account] = $conn->fetchFirstColumn(
                'SELECT d.name || \'=\' || m.role FROM membership m JOIN "user" u ON u.id=m.user_id JOIN profile p ON p.id=u.profile_id JOIN department d ON d.id=m.department_id WHERE p.email=? ORDER BY 1',
                [DemoAccounts::email($account)],
            );
        }

        return $out;
    }

    public function testRolesPerDepartmentHierarchyScopesAndGuestDepartment(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $this->syncAll($runner, $registry, $em);

            // Globale Rollen stehen im Profil, nie als MW-Mitgliedschaft; normale Rollen je Department ausdrücklich
            self::assertSame(
                [
                    'superadmin' => ['Demo Grossanlass Event=lw', 'Demo Materialverwaltung=u'],
                    'orgchef' => ['Demo Grossanlass Camp=u'],
                    'suborgchef' => ['Demo Grossanlass Camp=l2', 'Demo Materialverwaltung=u'],
                ],
                $this->rolesByAccount($conn, ['superadmin', 'orgchef', 'suborgchef']),
            );
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM membership m JOIN \"user\" u ON u.id=m.user_id JOIN profile p ON p.id=u.profile_id WHERE m.role IN ('mw','cmw') AND p.roles::text ~ 'SUPERADMIN|ORGANISATIONSCHEF|SUBORGCHEF'"));
            self::assertSame(['["ROLE_USER", "ROLE_SUPERADMIN", "ROLE_WEBADMIN"]', '["ROLE_USER", "ROLE_ORGANISATIONSCHEF"]', '["ROLE_USER", "ROLE_SUBORGCHEF"]'], array_map(
                static fn (string $a): string => (string) $conn->fetchOne('SELECT roles::jsonb::text FROM profile WHERE email=?', [DemoAccounts::email($a)]),
                ['superadmin', 'orgchef', 'suborgchef'],
            ));
            // dieselbe Person: andere Rolle im Gast-Department als im eigenen Department
            self::assertSame(['Demo Gast-Abteilung=mw', 'Demo Materialverwaltung=mw'], $this->rolesByAccount($conn, ['matwart'])['matwart']);

            // Mehrstufige Hierarchie: Kantonalverband (selbst Department) → Materialverwaltung | Abteilung Süd → Aussenstelle
            $id = static fn (string $name): string => (string) $conn->fetchOne('SELECT id FROM department WHERE name=?', [$name]);
            $parent = static fn (string $name): ?string => ($v = $conn->fetchOne('SELECT parent_id FROM department WHERE name=?', [$name])) === false ? null : ($v === null ? null : (string) $v);
            self::assertNull($parent('Demo Kantonalverband'));
            self::assertSame($id('Demo Kantonalverband'), $parent('Demo Materialverwaltung'));
            self::assertSame($id('Demo Kantonalverband'), $parent('Demo Abteilung Süd'));
            self::assertSame($id('Demo Abteilung Süd'), $parent('Demo Abteilung Süd Aussenstelle'));
            self::assertSame(
                (string) $conn->fetchOne("SELECT organisation_id FROM department WHERE demo_scenario_key='materialverwaltung'"),
                (string) $conn->fetchOne("SELECT organisation_id FROM department WHERE name='Demo Abteilung Süd Aussenstelle'"),
            );
            // Zusätzliche Departments sind Demo-Departments ohne Szenario-Schlüssel
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM department WHERE name IN ('Demo Kantonalverband','Demo Abteilung Süd','Demo Abteilung Süd Aussenstelle','Demo Gast-Abteilung') AND (demo_mode = false OR demo_scenario_key IS NOT NULL)"));

            // Verwaltungsbereiche (kombinierbar): Orgchef = Department Kantonalverband + Organisation Camp,
            // Suborgchef = Department Abteilung Süd + Organisation Event; Superadmin braucht keinen Scope
            $scope = static function (string $account) use ($conn): array {
                $raw = json_decode((string) $conn->fetchOne('SELECT admin_capabilities::text FROM profile WHERE email=?', [DemoAccounts::email($account)]), true)['scope'] ?? [];

                return ['departments' => $raw['department_root_ids'] ?? [], 'organisations' => $raw['organisation_ids'] ?? []];
            };
            $orgId = static fn (string $scenario): string => (string) $conn->fetchOne('SELECT organisation_id FROM department WHERE demo_scenario_key=?', [$scenario]);
            self::assertSame(['departments' => [$id('Demo Kantonalverband')], 'organisations' => [$orgId('grossanlass-camp')]], $scope('orgchef'));
            self::assertSame(['departments' => [$id('Demo Abteilung Süd')], 'organisations' => [$orgId('grossanlass-event')]], $scope('suborgchef'));
            self::assertNull($conn->fetchOne('SELECT admin_capabilities FROM profile WHERE email=?', [DemoAccounts::email('superadmin')]) ?: null);
            // Fehlender Scope: kein anderes Demo-Konto trägt Verwaltungsbereiche
            self::assertSame(2, (int) $conn->fetchOne("SELECT count(*) FROM profile WHERE email LIKE '%@demo.ematchef.ch' AND admin_capabilities IS NOT NULL"));

            // Gast-Department im Grossanlass: angenommene Teilnahme
            self::assertSame('accepted', $conn->fetchOne("SELECT status FROM department_grossanlass_participant WHERE host_department_id=(SELECT id FROM department WHERE demo_scenario_key='grossanlass-event') AND guest_department_id=?", [$id('Demo Gast-Abteilung')]));

            // verify sauber, wiederholter Sync ohne Änderungen
            foreach (DemoScenarioKey::all() as $key) {
                $em->clear();
                self::assertSame([], $runner->verify($registry->get($key)), $key);
            }
            $snapshot = [
                $conn->fetchAllAssociative('SELECT * FROM membership ORDER BY user_id, department_id'),
                $conn->fetchAllAssociative('SELECT id, admin_capabilities FROM profile ORDER BY id'),
                $conn->fetchAllAssociative('SELECT id, name, parent_id FROM department ORDER BY id'),
                $conn->fetchAllAssociative('SELECT * FROM department_grossanlass_participant'),
                $conn->fetchAllAssociative('SELECT seed_key, managed_hash FROM demo_seed_record ORDER BY scenario_key, seed_key'),
            ];
            $counts = $this->counts($conn);
            foreach ($this->syncAll($runner, $registry, $em) as $key => $result) {
                self::assertSame([], $result->notes, $key);
                self::assertSame(0, $result->created, $key);
            }
            self::assertSame($counts, $this->counts($conn));
            self::assertSame($snapshot, [
                $conn->fetchAllAssociative('SELECT * FROM membership ORDER BY user_id, department_id'),
                $conn->fetchAllAssociative('SELECT id, admin_capabilities FROM profile ORDER BY id'),
                $conn->fetchAllAssociative('SELECT id, name, parent_id FROM department ORDER BY id'),
                $conn->fetchAllAssociative('SELECT * FROM department_grossanlass_participant'),
                $conn->fetchAllAssociative('SELECT seed_key, managed_hash FROM demo_seed_record ORDER BY scenario_key, seed_key'),
            ]);
        });
    }

    /**
     * Stand der früheren Seeds nachstellen: Superadmin/Orgchef/Suborgchef mit automatischer MW-Rolle in allen drei
     * Demo-Departments, im Ledger als unverändert vom Seed geschrieben verbucht.
     */
    private function plantLegacyMwMemberships(Connection $conn): void
    {
        $hash = ManagedSeedApplier::hash(['role' => 'mw', 'is_primary' => false]);
        foreach (['superadmin', 'orgchef', 'suborgchef'] as $account) {
            $user = (string) $conn->fetchOne('SELECT u.id FROM "user" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?', [DemoAccounts::email($account)]);
            foreach (DemoScenarioKey::all() as $key) {
                $dept = (string) $conn->fetchOne('SELECT id FROM department WHERE demo_scenario_key=?', [$key]);
                $conn->executeStatement('DELETE FROM membership WHERE user_id=? AND department_id=?', [$user, $dept]);
                $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES (?,?,'mw',false)", [$user, $dept]);
                $conn->executeStatement('DELETE FROM demo_seed_record WHERE scenario_key=? AND seed_key=?', [$key, $key . ':membership:' . $account]);
                $conn->executeStatement(
                    "INSERT INTO demo_seed_record (id,scenario_key,seed_key,entity_class,entity_id,department_id,managed_hash,catalog_version,created_at) VALUES (?,?,?,?,?,?,?,'2026.10.2',now())",
                    [substr(md5($user . $dept), 0, 12), $key, $key . ':membership:' . $account, Membership::class, $user . ':' . $dept, $dept, $hash],
                );
            }
        }
    }

    public function testLegacyAutomaticMwMembershipsAreCorrectedOnlyWhenUntouched(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $real = $this->realWorld($em);
            $this->syncAll($runner, $registry, $em);
            $this->plantLegacyMwMemberships($conn);
            $userId = static fn (string $account): string => (string) $conn->fetchOne('SELECT u.id FROM "user" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?', [DemoAccounts::email($account)]);
            // Mitgliedschaft in einem echten Department (wie Cevi ZH11 lokal): bleibt in jedem Fall unberührt
            $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES (?,'realdept0001','mw',true)", [$userId('superadmin')]);
            // Von Hand geändert (Orgchef als Leader im Materialverwaltungs-Department): bleibt bestehen, wird gemeldet
            $conn->executeStatement("UPDATE membership SET role='l1' WHERE user_id=? AND department_id=(SELECT id FROM department WHERE demo_scenario_key='materialverwaltung')", [$userId('orgchef')]);

            $em->clear();
            $results = $this->syncAll($runner, $registry, $em);

            self::assertSame(
                [
                    'superadmin' => ['Demo Grossanlass Event=lw', 'Demo Materialverwaltung=u', 'Echtes Department=mw'],
                    'orgchef' => ['Demo Grossanlass Camp=u', 'Demo Materialverwaltung=l1'],
                    'suborgchef' => ['Demo Grossanlass Camp=l2', 'Demo Materialverwaltung=u'],
                ],
                $this->rolesByAccount($conn, ['superadmin', 'orgchef', 'suborgchef']),
            );
            // Hinweis auf die von Hand geänderte Zuordnung, nichts anderes
            $notes = array_merge(...array_values(array_map(static fn ($r): array => $r->notes, $results)));
            self::assertCount(1, $notes, implode("\n", $notes));
            self::assertStringContainsString('materialverwaltung:membership:orgchef', $notes[0]);
            // Die echte Mitgliedschaft samt Primär-Flag ist unverändert; echte Daten ebenfalls
            self::assertTrue((bool) $conn->fetchOne("SELECT is_primary FROM membership WHERE user_id=? AND department_id='realdept0001'", [$userId('superadmin')]));
            self::assertSame($real[0], $conn->fetchAllAssociative("SELECT * FROM department WHERE id='realdept0001'"));

            // Entfernte Zuordnungen sind auch aus dem Ledger verschwunden; zweiter Lauf ändert nichts mehr
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key IN ('grossanlass-camp:membership:superadmin','grossanlass-event:membership:orgchef','grossanlass-event:membership:suborgchef')"));
            $snapshot = $conn->fetchAllAssociative('SELECT * FROM membership ORDER BY user_id, department_id');
            $again = $this->syncAll($runner, $registry, $em);
            self::assertSame($snapshot, $conn->fetchAllAssociative('SELECT * FROM membership ORDER BY user_id, department_id'));
            $notesAgain = array_merge(...array_values(array_map(static fn ($r): array => $r->notes, $again)));
            self::assertCount(1, $notesAgain, implode("\n", $notesAgain));
        });
    }

    public function testExistingAdminScopeAndParentSetByHandAreNeverOverwritten(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            // Vorab: Orgchef-Profil mit eigenem Scope (z. B. von Hand eingerichtet), Materialverwaltungs-Department mit eigenem Parent
            $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('handorg00001','Meine Org',now(),now())");
            $conn->executeStatement("INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES ('handroot0001','handorg00001','Mein Verband',now(),now(),false,false)");
            $this->syncAll($runner, $registry, $em);
            $profile = DemoAccounts::email('orgchef');
            $scopeOf = static fn (): array => json_decode((string) $conn->fetchOne('SELECT admin_capabilities::text FROM profile WHERE email=?', [$profile]), true)['scope'];
            $kv = (string) $conn->fetchOne("SELECT id FROM department WHERE name='Demo Kantonalverband'");

            // Eigener Anteil des Szenarios fehlt (zuletzt vom Seed so geschrieben), eine von Hand ergänzte Zuweisung ausserhalb der
            // Demo-Strukturen und der Organisations-Scope aus dem Camp-Szenario bleiben neben dem nachgetragenen Eintrag erhalten
            $campOrg = (string) $conn->fetchOne("SELECT organisation_id FROM department WHERE demo_scenario_key='grossanlass-camp'");
            $conn->executeStatement('UPDATE profile SET admin_capabilities=? WHERE email=?', [json_encode(['scope' => ['organisation_ids' => [$campOrg], 'department_root_ids' => ['handroot0001']]]), $profile]);
            $conn->executeStatement('UPDATE demo_seed_record SET managed_hash=? WHERE seed_key=?', [ManagedSeedApplier::hash(['department_root_ids' => [], 'organisation_ids' => []]), 'materialverwaltung:adminscope:orgchef']);
            $em->clear();
            $runner->sync($registry->get('materialverwaltung'));
            self::assertEqualsCanonicalizing([$kv, 'handroot0001'], $scopeOf()['department_root_ids']);
            self::assertSame([$campOrg], $scopeOf()['organisation_ids']);

            // Von Hand umgestellter Seed-Eintrag (Wurzel ersetzt) → bleibt, Abweichung gemeldet
            $conn->executeStatement('UPDATE profile SET admin_capabilities=? WHERE email=?', [json_encode(['scope' => ['organisation_ids' => [], 'department_root_ids' => [(string) $conn->fetchOne("SELECT id FROM department WHERE name='Demo Materialverwaltung'")]]]), $profile]);
            $em->clear();
            $result = $runner->sync($registry->get('materialverwaltung'));
            self::assertSame([(string) $conn->fetchOne("SELECT id FROM department WHERE name='Demo Materialverwaltung'")], $scopeOf()['department_root_ids']);
            self::assertNotEmpty(array_filter($result->notes, static fn (string $n): bool => str_contains($n, 'materialverwaltung:adminscope:orgchef')));

            // Parent des Szenario-Departments von Hand umgehängt → bleibt, Abweichung gemeldet
            $conn->executeStatement("UPDATE department SET parent_id='handroot0001' WHERE demo_scenario_key='materialverwaltung'");
            $em->clear();
            $result = $runner->sync($registry->get('materialverwaltung'));
            self::assertSame('handroot0001', $conn->fetchOne("SELECT parent_id FROM department WHERE demo_scenario_key='materialverwaltung'"));
            self::assertNotEmpty(array_filter($result->notes, static fn (string $n): bool => str_contains($n, 'materialverwaltung:department-parent')));
        });
    }

    public function testProtectedStateSurvivesSync(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $this->syncAll($runner, $registry, $em);
            $conn->executeStatement("UPDATE \"user\" SET password='von-hand-gesetzt' WHERE profile_id=(SELECT id FROM profile WHERE email=?)", [DemoAccounts::email('ga-mw')]);
            $conn->executeStatement("UPDATE department SET demo_clock_offset_seconds=123456 WHERE demo_scenario_key='grossanlass-event'");
            $conn->executeStatement("UPDATE membership SET role='u' WHERE department_id=(SELECT id FROM department WHERE demo_scenario_key='grossanlass-event') AND user_id=(SELECT u.id FROM \"user\" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?)", [DemoAccounts::email('ga-ok')]);

            $results = $this->syncAll($runner, $registry, $em);

            self::assertSame('von-hand-gesetzt', $conn->fetchOne("SELECT u.password FROM \"user\" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?", [DemoAccounts::email('ga-mw')]));
            self::assertSame(123456, (int) $conn->fetchOne("SELECT demo_clock_offset_seconds FROM department WHERE demo_scenario_key='grossanlass-event'"));
            // manuell geänderte Rolle bleibt und wird als Abweichung gemeldet
            self::assertSame('u', $conn->fetchOne("SELECT role FROM membership m JOIN \"user\" u ON u.id=m.user_id JOIN profile p ON p.id=u.profile_id WHERE p.email=?", [DemoAccounts::email('ga-ok')]));
            self::assertNotEmpty(array_filter($results['grossanlass-event']->notes, static fn (string $n): bool => str_contains($n, 'membership:ga-ok')));
            self::assertSame([], $results['grossanlass-camp']->notes);
        });
    }

    public function testCatalogStructureChangesRenameAddAndReportRemovedEntriesWithoutDeleting(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn, DemoOrganisationCatalog $catalog): void {
            $this->syncAll($runner, $registry, $em);
            $groupsBefore = (int) $conn->fetchOne('SELECT count(*) FROM "group"');
            // Eine Gruppe von Hand umbenannt (soll geschützt bleiben), eine zweite bleibt unberührt
            $conn->executeStatement("UPDATE \"group\" SET name='Meine Bauten' WHERE name='Bauten' AND grossanlass_kind='teilbereich' AND department_id=(SELECT id FROM department WHERE demo_scenario_key='grossanlass-event')");

            $data = json_decode((string) file_get_contents(DemoOrganisationCatalog::FILE), true, 512, JSON_THROW_ON_ERROR);
            $data['catalogVersion'] = '2026.10.2';
            foreach ($data['scenarios']['grossanlass-event']['groups'] as &$g) {
                if ($g['key'] === 'bauten') {
                    $g['name'] = 'Bauten NEU';
                }
                if ($g['key'] === 'wasser-sanitaer') {
                    $g['name'] = 'Wasser & Sanitär NEU';
                }
            }
            unset($g);
            $data['scenarios']['grossanlass-event']['groups'][] = ['key' => 'strom', 'name' => 'Strom', 'sort' => 50, 'parent' => 'infrastruktur', 'kind' => 'teilbereich'];
            // Eintrag aus dem Katalog entfernt: ga-spon
            $data['scenarios']['grossanlass-event']['members'] = array_values(array_filter($data['scenarios']['grossanlass-event']['members'], static fn (array $m): bool => $m['account'] !== 'ga-spon'));
            $file = tempnam(sys_get_temp_dir(), 'cat');
            file_put_contents($file, json_encode($data));
            try {
                $prop = new \ReflectionProperty(DemoOrganisationCatalog::class, 'file');
                $prop->setValue($catalog, $file);
                (new \ReflectionProperty(DemoOrganisationCatalog::class, 'data'))->setValue($catalog, null);

                $em->clear();
                $result = $runner->sync($registry->get('grossanlass-event'));
            } finally {
                unlink($file);
            }

            // geschützt: manuell umbenannt → bleibt, Abweichung gemeldet
            self::assertSame('Meine Bauten', $conn->fetchOne("SELECT g.name FROM \"group\" g JOIN demo_seed_record r ON r.entity_id=g.id WHERE r.seed_key='grossanlass-event:group:bauten'"));
            self::assertNotEmpty(array_filter($result->notes, static fn (string $n): bool => str_contains($n, 'group:bauten')));
            // unberührt → folgt dem Katalog
            self::assertSame('Wasser & Sanitär NEU', $conn->fetchOne("SELECT g.name FROM \"group\" g JOIN demo_seed_record r ON r.entity_id=g.id WHERE r.seed_key='grossanlass-event:group:wasser-sanitaer'"));
            // neu ergänzt
            self::assertSame($groupsBefore + 1, (int) $conn->fetchOne('SELECT count(*) FROM "group"'));
            // entfernt aus dem Katalog: gemeldet, aber nicht gelöscht
            self::assertNotEmpty(array_filter($result->notes, static fn (string $n): bool => str_contains($n, 'grossanlass-event:membership:ga-spon')));
            self::assertSame(1, (int) $conn->fetchOne('SELECT count(*) FROM profile WHERE email=?', [DemoAccounts::email('ga-spon')]));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM membership m JOIN \"user\" u ON u.id=m.user_id JOIN profile p ON p.id=u.profile_id WHERE p.email=?", [DemoAccounts::email('ga-spon')]));
        });
    }

    public function testUserOwnershipConflictAndAdoption(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $this->realWorld($em);
            // (a) Konto gleicher Adresse, aber Mitglied eines echten Departments → Konflikt, unverändert
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('conflprof001',?,'Echt','Person','E','[\"ROLE_USER\"]',now(),now())", [DemoAccounts::email('matwart')]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('confluser001','conflprof001','active','echtes-passwort',true,now(),now())");
            $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES ('confluser001','realdept0001','mw',true)");
            // (c) Demo-Konto, das fälschlich in einem echten Department hängt, aber noch das Demo-Passwort hat → eindeutiger Nachweis
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('proofprof001',?,'Alt','Name','A','[\"ROLE_USER\"]',now(),now())", [DemoAccounts::email('ga-komm')]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('proofuser001','proofprof001','active',?,true,now(),now())", [password_hash(DemoAccounts::password(), PASSWORD_BCRYPT, ['cost' => 4])]);
            $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES ('proofuser001','realdept0001','u',true)");
            // (b) Legacy-Demo-Konto ohne echte Mitgliedschaft → wird übernommen, Passwort bleibt
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('adoptprof001',?,'Alt','Name','A','[\"ROLE_USER\"]',now(),now())", [DemoAccounts::email('ga-lw')]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('adoptuser001','adoptprof001','active','altes-passwort',true,now(),now())");

            $results = $this->syncAll($runner, $registry, $em);

            $mat = $results['materialverwaltung'];
            self::assertNotEmpty(array_filter($mat->notes, static fn (string $n): bool => str_contains($n, 'Konflikt') && str_contains($n, 'matwart')));
            self::assertSame('echtes-passwort', $conn->fetchOne("SELECT password FROM \"user\" WHERE id='confluser001'"));
            self::assertSame('Person', $conn->fetchOne("SELECT last_name FROM profile WHERE id='conflprof001'"));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM membership WHERE user_id='confluser001'"));
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='demo-users:user:matwart'"));
            // restliche Mitglieder des Szenarios wurden trotzdem angelegt; matwart hat hier keine Demo-Mitgliedschaft
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM membership WHERE user_id='confluser001' AND department_id<>'realdept0001'"));

            // (c) übernommen; Mitgliedschaft im echten Department bleibt unverändert (primär), Hinweis statt Konflikt
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='demo-users:user:ga-komm' AND entity_id='proofuser001'"));
            self::assertSame('u', $conn->fetchOne("SELECT role FROM membership WHERE user_id='proofuser001' AND department_id='realdept0001'"));
            self::assertTrue((bool) $conn->fetchOne("SELECT is_primary FROM membership WHERE user_id='proofuser001' AND department_id='realdept0001'"));
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM membership WHERE user_id='proofuser001' AND is_primary AND department_id<>'realdept0001'"));
            self::assertNotEmpty(array_filter($results['grossanlass-event']->notes, static fn (string $n): bool => str_contains($n, 'ga-komm') && str_contains($n, 'Hinweis')));
            self::assertSame('altes-passwort', $conn->fetchOne("SELECT password FROM \"user\" WHERE id='adoptuser001'"));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='demo-users:user:ga-lw' AND entity_id='adoptuser001'"));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM profile WHERE email=?", [DemoAccounts::email('ga-lw')]));

            // Nach dem Beheben des Konflikts (Konto ohne echte Mitgliedschaft) wird es beim nächsten Sync übernommen
            $conn->executeStatement("DELETE FROM membership WHERE user_id='confluser001'");
            $em->clear();
            $runner->sync($registry->get('materialverwaltung'));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='demo-users:user:matwart' AND entity_id='confluser001'"));
            self::assertSame('echtes-passwort', $conn->fetchOne("SELECT password FROM \"user\" WHERE id='confluser001'"));
        });
    }

    public function testDeletedRowsAreRestoredAndDryRunWritesNothing(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $before = $this->counts($conn);
            $dry = $runner->sync($registry->get('grossanlass-camp'), true);
            self::assertSame($before, $this->counts($conn));
            self::assertStringContainsString('Dry-Run', $dry->message);

            $this->syncAll($runner, $registry, $em);
            $conn->executeStatement("DELETE FROM group_membership WHERE group_id IN (SELECT id FROM \"group\" WHERE name='Zelte')");
            $em->clear();
            self::assertNotEmpty($runner->verify($registry->get('grossanlass-camp')));

            $em->clear();
            $runner->sync($registry->get('grossanlass-camp'));
            $em->clear();
            self::assertSame([], $runner->verify($registry->get('grossanlass-camp')));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM group_membership WHERE group_id IN (SELECT id FROM \"group\" WHERE name='Zelte')"));
        });
    }

    public function testScenariosAreIndependent(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $runner->sync($registry->get('grossanlass-camp'));
            $campRows = $conn->fetchAllAssociative("SELECT * FROM demo_seed_record WHERE scenario_key='grossanlass-camp' ORDER BY seed_key");
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM department WHERE demo_scenario_key<>'grossanlass-camp'"));

            $em->clear();
            $runner->sync($registry->get('grossanlass-event'));
            $runner->sync($registry->get('materialverwaltung'));
            self::assertSame($campRows, $conn->fetchAllAssociative("SELECT * FROM demo_seed_record WHERE scenario_key='grossanlass-camp' ORDER BY seed_key"));
            // Gruppen gehören je einem Department; gemeinsame Demo-Benutzer liegen ausserhalb der Szenario-Ledger
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE scenario_key<>'demo-users' AND entity_class LIKE '%\\User'"));
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE scenario_key='demo-users' AND department_id IS NOT NULL"));
            self::assertSame(1, (int) $conn->fetchOne('SELECT count(*) FROM profile WHERE email=?', [DemoAccounts::email('orgchef')]));
        });
    }

    /** Zeilen-Fingerabdrücke ohne Namens- und Zeitstempelspalten (Beweis: nur Anzeigenamen ändern sich). */
    private function fingerprint(Connection $conn): array
    {
        $out = [];
        foreach (['organisation', 'department', '"group"', 'membership', 'group_membership', '"user"', 'profile'] as $t) {
            $out[$t] = $conn->fetchFirstColumn("SELECT md5((to_jsonb(t) - 'name' - 'updated_at')::text) FROM $t t ORDER BY 1");
        }

        return $out;
    }

    /** Wie fingerprint(), zusätzlich Aktivitäten und Kalenderperioden (ohne ihre Titel). */
    private function fingerprintWithTitles(Connection $conn): array
    {
        $out = $this->fingerprint($conn);
        foreach (['activity', 'department_calendar_period'] as $t) {
            $out[$t] = $conn->fetchFirstColumn("SELECT md5((to_jsonb(t) - 'name' - 'updated_at')::text) FROM $t t ORDER BY 1");
        }

        return $out;
    }

    public function testLegacyRenameOnlyChangesNamesIsIdempotentAndKeepsNewSeedsSeparate(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn, DemoOrganisationCatalog $catalog, $container): void {
            $rename = $container->get(\App\Service\Demo\Legacy\LegacyDemoRename::class);
            $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('5f35b7cde9b5','Demo-Organisation',now(),now()),('decoyorg0001','Andere Org',now(),now())");
            $dept = static fn (string $id, string $org, string $name, string $ga = 'false', ?string $parent = null) => $conn->executeStatement(
                'INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass,parent_id) VALUES (?,?,?,now(),now(),false,' . $ga . ',?)',
                [$id, $org, $name, $parent],
            );
            $dept('7ae5770a1180', '5f35b7cde9b5', 'Demo-Department-Parent');
            $dept('638c8d301090', '5f35b7cde9b5', 'Demo-Department (mein Umbau)', 'false', '7ae5770a1180'); // Name weicht ab → bleibt
            $dept('72605b231274', '5f35b7cde9b5', 'Demo-Grossanlass-Camp', 'true', '7ae5770a1180');
            $dept('3dc94912d836', '5f35b7cde9b5', 'Demo-Grossanlass-Event', 'true');
            $dept('7aa39b221bab', '5f35b7cde9b5', 'old-Demo Grossanlass', 'true'); // schon gekennzeichnet
            $dept('decoydept001', 'decoyorg0001', 'Demo-Department'); // gleicher Name, andere ID → nie anfassen
            $group = static fn (string $id, string $d, string $name, ?string $parent = null) => $conn->executeStatement(
                'INSERT INTO "group" (id,department_id,name,parent_id,sort_order,created_at,updated_at) VALUES (?,?,?,?,0,now(),now())',
                [$id, $d, $name, $parent],
            );
            $group('grp000000001', '3dc94912d836', 'Bauten');
            $group('grp000000002', '3dc94912d836', 'Holzbau', 'grp000000001');
            $group('grp000000003', '3dc94912d836', 'old-Schon markiert');
            $group('grp000000004', '7aa39b221bab', 'Infrastruktur');
            $group('grp000000005', 'decoydept001', 'Bauten'); // Decoy-Gruppe
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('legprof00001',?,'A','B','C','[\"ROLE_USER\"]',now(),now())", [DemoAccounts::email('ga-lw')]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('leguser00001','legprof00001','active','pw',true,now(),now())");
            $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES ('leguser00001','3dc94912d836','lw',true)");
            $conn->executeStatement("INSERT INTO group_membership (user_id,group_id,role,is_primary,can_procure,created_at) VALUES ('leguser00001','grp000000001','member',true,false,now())");
            $activity = static fn (string $id, string $d, string $name) => $conn->executeStatement(
                'INSERT INTO activity (id,department_id,name,created_at,updated_at) VALUES (?,?,?,now(),now())',
                [$id, $d, $name],
            );
            $period = static fn (string $id, string $d, string $name) => $conn->executeStatement(
                "INSERT INTO department_calendar_period (id,department_id,label,name,start_date,end_date,created_at,updated_at) VALUES (?,?,'grossanlass',?,'2026-11-13','2026-11-15',now(),now())",
                [$id, $d, $name],
            );
            $activity('act000000001', '3dc94912d836', 'Demo-Grossanlass-Event');
            $activity('act000000002', '7aa39b221bab', 'old-Demo Grossanlass'); // schon gekennzeichnet
            $activity('act000000003', 'decoydept001', 'Demo-Department'); // fremdes Department → nie anfassen
            $activity('act000000004', '3dc94912d836', 'Mein eigener Anlass'); // gehört zum Legacy-Department → wird gekennzeichnet
            $period('per000000001', '3dc94912d836', 'Aufbau');
            $period('per000000002', '3dc94912d836', 'Demo-Grossanlass-Event');
            $period('per000000003', 'decoydept001', 'Aufbau');
            $before = $this->fingerprintWithTitles($conn);
            $deptCount = (int) $conn->fetchOne('SELECT count(*) FROM department');

            // Dry-Run (plan) schreibt nichts
            $em->clear();
            $plan = $rename->plan();
            self::assertSame($before, $this->fingerprintWithTitles($conn));
            self::assertSame('Demo-Organisation', $conn->fetchOne("SELECT name FROM organisation WHERE id='5f35b7cde9b5'"));
            $status = array_column(array_filter($plan, static fn (array $i): bool => $i['type'] === 'department'), 'status', 'id');
            self::assertSame('done', $status['7aa39b221bab']);
            self::assertSame('skip', $status['638c8d301090']);
            self::assertSame('rename', $status['3dc94912d836']);

            $em->clear();
            self::assertSame(1 + 3 + 3 + 2 + 2, $rename->apply($plan)); // Organisation + 3 Departments + 3 Gruppen + 2 Aktivitäten + 2 Kalenderperioden
            $em->clear();

            // Nur diese Namen haben sich geändert
            self::assertSame('old-Demo-Organisation', $conn->fetchOne("SELECT name FROM organisation WHERE id='5f35b7cde9b5'"));
            self::assertSame('old-Demo-Grossanlass-Event', $conn->fetchOne("SELECT name FROM department WHERE id='3dc94912d836'"));
            self::assertSame('old-Demo-Grossanlass-Camp', $conn->fetchOne("SELECT name FROM department WHERE id='72605b231274'"));
            self::assertSame('old-Demo-Department-Parent', $conn->fetchOne("SELECT name FROM department WHERE id='7ae5770a1180'"));
            self::assertSame('old-Demo Grossanlass', $conn->fetchOne("SELECT name FROM department WHERE id='7aa39b221bab'"));
            self::assertSame('Demo-Department (mein Umbau)', $conn->fetchOne("SELECT name FROM department WHERE id='638c8d301090'"));
            self::assertSame('Demo-Department', $conn->fetchOne("SELECT name FROM department WHERE id='decoydept001'"));
            self::assertSame(['old-Bauten', 'old-Holzbau', 'old-Schon markiert'], $conn->fetchFirstColumn("SELECT name FROM \"group\" WHERE department_id='3dc94912d836' ORDER BY name"));
            self::assertSame('old-Infrastruktur', $conn->fetchOne("SELECT name FROM \"group\" WHERE id='grp000000004'"));
            self::assertSame('Bauten', $conn->fetchOne("SELECT name FROM \"group\" WHERE id='grp000000005'"));
            // Sichtbare Aktivitäts- und Kalendertitel der Legacy-Departments; Fremdes und Gekennzeichnetes bleibt
            self::assertSame(['old-Demo-Grossanlass-Event', 'old-Mein eigener Anlass'], $conn->fetchFirstColumn("SELECT name FROM activity WHERE department_id='3dc94912d836' ORDER BY name"));
            self::assertSame('old-Demo Grossanlass', $conn->fetchOne("SELECT name FROM activity WHERE id='act000000002'"));
            self::assertSame('Demo-Department', $conn->fetchOne("SELECT name FROM activity WHERE id='act000000003'"));
            self::assertSame(['old-Aufbau', 'old-Demo-Grossanlass-Event'], $conn->fetchFirstColumn("SELECT name FROM department_calendar_period WHERE department_id='3dc94912d836' ORDER BY name"));
            self::assertSame('Aufbau', $conn->fetchOne("SELECT name FROM department_calendar_period WHERE id='per000000003'"));
            // Technische Schlüssel (Kalender-Label), IDs, Benutzer, Mitgliedschaften und alle übrigen Spalten unverändert, nichts gelöscht
            self::assertSame(['grossanlass'], array_values(array_unique($conn->fetchFirstColumn("SELECT label FROM department_calendar_period WHERE department_id='3dc94912d836'"))));
            self::assertSame($before, $this->fingerprintWithTitles($conn));
            self::assertSame($deptCount, (int) $conn->fetchOne('SELECT count(*) FROM department'));

            // Idempotent: zweiter Lauf ändert nichts, kein doppeltes Präfix
            $again = $rename->plan();
            self::assertSame([], array_filter($again, static fn (array $i): bool => $i['status'] === 'rename'));
            self::assertSame(0, $rename->apply($again));
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM department WHERE name ILIKE 'old-old-%'") + (int) $conn->fetchOne("SELECT count(*) FROM \"group\" WHERE name ILIKE 'old-old-%'") + (int) $conn->fetchOne("SELECT count(*) FROM activity WHERE name ILIKE 'old-old-%'") + (int) $conn->fetchOne("SELECT count(*) FROM department_calendar_period WHERE name ILIKE 'old-old-%'"));

            // Neue Seeds legen eigene Departments an, fassen die umbenannten nicht an und legen die alten Namen nicht neu an
            $legacyFingerprint = $this->fingerprint($conn);
            $legacyNames = $conn->fetchAllAssociative("SELECT id,name FROM department WHERE id IN ('5f35b7cde9b5')  OR id IN ('3dc94912d836','72605b231274','7ae5770a1180','7aa39b221bab','638c8d301090') ORDER BY id");
            $this->syncAll($runner, $registry, $em);
            self::assertSame($legacyNames, $conn->fetchAllAssociative("SELECT id,name FROM department WHERE id IN ('5f35b7cde9b5')  OR id IN ('3dc94912d836','72605b231274','7ae5770a1180','7aa39b221bab','638c8d301090') ORDER BY id"));
            self::assertSame(3, (int) $conn->fetchOne('SELECT count(*) FROM department WHERE demo_scenario_key IS NOT NULL'));
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE entity_id IN ('3dc94912d836','72605b231274','638c8d301090','7ae5770a1180','7aa39b221bab')"));
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM department WHERE name IN ('Demo Grossanlass','Demo-Grossanlass-Event','Demo-Grossanlass-Camp','Demo-Department-Parent')"));

            $seed = $container->get(\App\Service\Bootstrap\DemoGrossanlassSeedService::class);
            $org = $em->find(\App\Entity\Organisation::class, '5f35b7cde9b5');
            $owner = $em->find(\App\Entity\User::class, 'leguser00001');
            try {
                $seed->ensureDepartment($org, $owner, 'Demo Grossanlass');
                self::fail('Legacy-Name darf nicht neu angelegt werden');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('ausgemusterten', $e->getMessage());
            }
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM department WHERE name = 'Demo Grossanlass'"));
        });
    }
}
