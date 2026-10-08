<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Demo\Organisation\DemoOrganisationCatalog;
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
                $test($c->get(DemoScenarioRunner::class), $c->get(DemoScenarioRegistry::class), $em, $conn, $c->get(DemoOrganisationCatalog::class));
            } finally {
                $conn->rollBack();
            }
        }, $url, [DemoScenarioRunner::class, DemoScenarioRegistry::class, DemoOrganisationCatalog::class]);
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

        return [$conn->fetchAllAssociative("SELECT * FROM department WHERE id='realdept0001'"), $conn->fetchAllAssociative("SELECT * FROM organisation WHERE id='realorg00001'")];
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
            self::assertSame($before['department'] + 3, $after['department']);
            self::assertSame(3, (int) $conn->fetchOne('SELECT count(*) FROM department WHERE demo_scenario_key IS NOT NULL AND demo_mode = true'));
            $expectedUsers = 0;
            $expectedGroups = 0;
            foreach (DemoScenarioKey::all() as $key) {
                $expectedUsers += \count($catalog->scenario($key)['members']);
                $expectedGroups += \count($catalog->scenario($key)['groups']);
            }
            self::assertSame($before['"user"'] + $expectedUsers, $after['"user"']);
            self::assertSame($before['"group"'] + $expectedGroups, $after['"group"']);
            self::assertSame($expectedUsers, $after['membership']);
            // Event/Camp sind Grossanlass-Departments, Materialverwaltung nicht
            self::assertTrue((bool) $conn->fetchOne("SELECT is_grossanlass FROM department WHERE demo_scenario_key='grossanlass-camp'"));
            self::assertFalse((bool) $conn->fetchOne("SELECT is_grossanlass FROM department WHERE demo_scenario_key='materialverwaltung'"));
            // Echte Daten unverändert
            self::assertSame($real[0], $conn->fetchAllAssociative("SELECT * FROM department WHERE id='realdept0001'"));
            self::assertSame($real[1], $conn->fetchAllAssociative("SELECT * FROM organisation WHERE id='realorg00001'"));
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
            self::assertNotEmpty(array_filter($result->notes, static fn (string $n): bool => str_contains($n, 'grossanlass-event:user:ga-spon')));
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
            // (b) Legacy-Demo-Konto ohne echte Mitgliedschaft → wird übernommen, Passwort bleibt
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('adoptprof001',?,'Alt','Name','A','[\"ROLE_USER\"]',now(),now())", [DemoAccounts::email('ga-lw')]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('adoptuser001','adoptprof001','active','altes-passwort',true,now(),now())");

            $results = $this->syncAll($runner, $registry, $em);

            $mat = $results['materialverwaltung'];
            self::assertNotEmpty(array_filter($mat->notes, static fn (string $n): bool => str_contains($n, 'Konflikt') && str_contains($n, 'matwart')));
            self::assertSame('echtes-passwort', $conn->fetchOne("SELECT password FROM \"user\" WHERE id='confluser001'"));
            self::assertSame('Person', $conn->fetchOne("SELECT last_name FROM profile WHERE id='conflprof001'"));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM membership WHERE user_id='confluser001'"));
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='materialverwaltung:user:matwart'"));
            // restliche Mitglieder des Szenarios wurden trotzdem angelegt; matwart hat hier keine Demo-Mitgliedschaft
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM membership WHERE user_id='confluser001' AND department_id<>'realdept0001'"));

            self::assertSame('altes-passwort', $conn->fetchOne("SELECT password FROM \"user\" WHERE id='adoptuser001'"));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='grossanlass-event:user:ga-lw' AND entity_id='adoptuser001'"));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM profile WHERE email=?", [DemoAccounts::email('ga-lw')]));

            // Nach dem Beheben des Konflikts (Konto ohne echte Mitgliedschaft) wird es beim nächsten Sync übernommen
            $conn->executeStatement("DELETE FROM membership WHERE user_id='confluser001'");
            $em->clear();
            $runner->sync($registry->get('materialverwaltung'));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='materialverwaltung:user:matwart' AND entity_id='confluser001'"));
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
            // jedes Department hat nur eigene Gruppen und Mitgliedschaften
            self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM membership m JOIN department d ON d.id=m.department_id WHERE d.demo_scenario_key IS NOT NULL AND m.user_id IN (SELECT m2.user_id FROM membership m2 WHERE m2.department_id<>m.department_id)'));
        });
    }
}
