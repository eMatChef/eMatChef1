<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\Demo\Organisation\DemoOrganisationCatalog;
use App\Service\Demo\Organisation\ManagedSeedApplier;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use App\Service\Demo\Scenario\SeedResult;
use App\Tests\Wiring\FreshKernel;
use App\Util\DemoAccounts;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Gemeinsame Demo-Organisation und Event-Inhalt (grossanlass-event) gegen eine ISOLIERTE Test-Datenbank
 * (EMATCHEF_TEST_DB_URL, Name «val_*» oder «*_test», nie mvdb; jeder Test in einer zurückgerollten Transaktion).
 */
final class DemoSharedOrganisationEventIntegrationTest extends TestCase
{
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
                $test($c->get(DemoScenarioRunner::class), $c->get(DemoScenarioRegistry::class), $em, $conn);
            } finally {
                $conn->rollBack();
            }
        }, $url, [DemoScenarioRunner::class, DemoScenarioRegistry::class, DemoOrganisationCatalog::class, \App\Service\Demo\Legacy\LegacyDemoRename::class, \App\Service\Bootstrap\DemoGrossanlassSeedService::class]);
    }

    /** @return array<string, SeedResult> */
    private function syncAll(DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em): array
    {
        $results = [];
        foreach ($registry->all() as $key => $scenario) {
            $em->clear();
            $results[$key] = $runner->sync($scenario);
        }

        return $results;
    }

    /** @return array<string, int> */
    private function counts(Connection $conn): array
    {
        $out = [];
        foreach (['organisation', 'department', '"group"', 'activity_grossanlass_wish_line', 'department_grossanlass_place', 'department_grossanlass_task', 'department_grossanlass_vehicle_need', 'activity_grossanlass_procurement_line', 'department_grossanlass_cost', 'address', 'demo_seed_record', 'membership', 'group_membership'] as $t) {
            $out[$t] = (int) $conn->fetchOne("SELECT count(*) FROM $t");
        }

        return $out;
    }

    private function eventId(Connection $conn): string
    {
        return (string) $conn->fetchOne("SELECT id FROM department WHERE demo_scenario_key='grossanlass-event'");
    }

    public function testOneSharedOrganisationFullEventContentIdempotencyAndIsolation(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('realorg00001','Echte Abteilung',now(),now())");
            $conn->executeStatement("INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES ('realdept0001','realorg00001','Echtes Department',now(),now(),false,false)");
            $real = $conn->fetchAllAssociative("SELECT * FROM department WHERE id='realdept0001'");

            $results = $this->syncAll($runner, $registry, $em);
            foreach ($results as $key => $result) {
                self::assertSame(SeedResult::OK, $result->status, $key);
                self::assertSame([], $result->notes, $key);
            }
            foreach ($registry->all() as $key => $scenario) {
                self::assertSame([], $runner->verify($scenario), $key);
            }

            // Eine Organisation für alle Demo-Departments, echte Daten unberührt
            $demoOrgs = $conn->fetchFirstColumn("SELECT DISTINCT organisation_id FROM department WHERE demo_mode = true");
            self::assertCount(1, $demoOrgs);
            self::assertSame('Demo Organisation', $conn->fetchOne('SELECT name FROM organisation WHERE id=?', [$demoOrgs[0]]));
            self::assertSame(2, (int) $conn->fetchOne('SELECT count(*) FROM organisation'));
            self::assertSame($real, $conn->fetchAllAssociative("SELECT * FROM department WHERE id='realdept0001'"));
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='demo-organisation:organisation' AND scenario_key='demo-organisation' AND department_id IS NULL"));

            // Hierarchie: Einrichtung, Camp und Event auf oberster Ebene (keine Rechteausweitung für den Orgchef über den Unterbaum)
            $kv = (string) $conn->fetchOne("SELECT id FROM department WHERE name='Demo Kantonalverband'");
            $parentOf = static fn (string $key): ?string => ($v = $conn->fetchOne('SELECT parent_id FROM department WHERE demo_scenario_key=?', [$key])) === null || $v === false ? null : (string) $v;
            self::assertNull($parentOf('grossanlass-setup'));
            self::assertNull($parentOf('grossanlass-camp'));
            self::assertNull($parentOf('grossanlass-event'));
            self::assertSame($kv, $parentOf('materialverwaltung'));

            // Einrichtung bleibt nicht freigegeben, Camp und Event sind freigegeben, Typen unverändert
            $released = static fn (string $key): array => $conn->fetchAssociative('SELECT c.guest_activity_type AS t, c.setup_released_at IS NOT NULL AS r FROM department_grossanlass_config c JOIN department d ON d.id=c.department_id WHERE d.demo_scenario_key=?', [$key]) ?: [];
            self::assertSame(['t' => 'camp', 'r' => false], $released('grossanlass-setup'));
            self::assertSame(['t' => 'camp', 'r' => true], $released('grossanlass-camp'));
            self::assertSame(['t' => 'event', 'r' => true], $released('grossanlass-event'));

            // Verwaltungsbereiche: nur Department-Wurzeln, nie die gemeinsame Organisation
            $scope = static function (string $account) use ($conn): array {
                $raw = json_decode((string) $conn->fetchOne('SELECT admin_capabilities::text FROM profile WHERE email=?', [DemoAccounts::email($account)]), true)['scope'] ?? [];
                $roots = $raw['department_root_ids'] ?? [];
                sort($roots);

                return ['roots' => $roots, 'organisations' => $raw['organisation_ids'] ?? []];
            };
            $id = static fn (string $name): string => (string) $conn->fetchOne('SELECT id FROM department WHERE name=?', [$name]);
            $camp = (string) $conn->fetchOne("SELECT id FROM department WHERE demo_scenario_key='grossanlass-camp'");
            $expectedOrg = [$kv, $camp];
            sort($expectedOrg);
            $expectedSub = [$id('Demo Abteilung Süd'), $this->eventId($conn), $id('Demo Gast-Abteilung')];
            sort($expectedSub);
            self::assertSame(['roots' => $expectedOrg, 'organisations' => []], $scope('orgchef'));
            self::assertSame(['roots' => $expectedSub, 'organisations' => []], $scope('suborgchef'));

            // Event-Stammdaten
            $event = $this->eventId($conn);
            $config = $conn->fetchAssociative('SELECT * FROM department_grossanlass_config WHERE department_id=?', [$event]);
            self::assertSame('Demo-Festplatz Seewiese', $config['location_text']);
            self::assertStringContainsString('Mehrtägige Pfadi-Grossveranstaltung', (string) $config['notes']);
            $venue = $conn->fetchAssociative('SELECT * FROM address WHERE id=?', [$config['venue_address_id']]);
            self::assertSame(['event', 'Seewiesenweg', '1', '6000', 'Luzern', 'Schweiz'], [$venue['type'], $venue['street'], $venue['street_number'], $venue['postal_code'], $venue['city'], $venue['country']]);
            $start = new \DateTimeImmutable((string) $config['planned_event_start']);
            self::assertSame($start->setTime(0, 0)->modify('+2 days')->format('Y-m-d') . ' 23:59:59', (new \DateTimeImmutable((string) $config['planned_event_end']))->format('Y-m-d H:i:s'));
            self::assertSame((new \DateTimeImmutable((string) $config['planned_event_end']))->format('Y-m-d'), (string) $conn->fetchOne('SELECT end_date FROM department_calendar_period WHERE department_id=?', [$event]));

            // Ressorts und Bereiche (Organisationsstruktur) sowie Bauprojekte: 13 Katalog-Gruppen + 6 Gewerke + 20 Bauprojekte
            self::assertSame(13 + 6 + 20, (int) $conn->fetchOne('SELECT count(*) FROM "group" WHERE department_id=?', [$event]));
            self::assertSame(8, (int) $conn->fetchOne("SELECT count(*) FROM \"group\" WHERE department_id=? AND grossanlass_kind='ressort'", [$event]));
            self::assertSame(20, (int) $conn->fetchOne("SELECT count(*) FROM \"group\" WHERE department_id=? AND grossanlass_kind='teilbereich' AND build_status='planned'", [$event]));
            // Zeitfenster relativ zum Anlass: Aufbau vor Beginn, Rückbau nach dem Ende
            $min = (string) $conn->fetchOne('SELECT min(window_start) FROM "group" WHERE department_id=?', [$event]);
            $max = (string) $conn->fetchOne('SELECT max(window_end) FROM "group" WHERE department_id=?', [$event]);
            self::assertSame($start->setTime(0, 0)->modify('-10 days')->format('Y-m-d'), substr($min, 0, 10));
            self::assertSame($start->setTime(0, 0)->modify('+3 days')->format('Y-m-d'), substr($max, 0, 10));
            // Materialwünsche, Orte, Aufgaben, Fahrzeugbedarfe, Beschaffung und Kosten
            self::assertSame(57, (int) $conn->fetchOne('SELECT count(*) FROM activity_grossanlass_wish_line l JOIN "group" g ON g.id=l.group_id WHERE g.department_id=?', [$event]));
            self::assertSame(20, (int) $conn->fetchOne("SELECT count(*) FROM department_grossanlass_place WHERE department_id=? AND kind='bauprojekt'", [$event]));
            self::assertSame([5, 5, 4, 4], [
                (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_task WHERE department_id=?', [$event]),
                (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_vehicle_need WHERE department_id=?', [$event]),
                (int) $conn->fetchOne('SELECT count(*) FROM activity_grossanlass_procurement_line WHERE department_id=?', [$event]),
                (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_cost WHERE department_id=?', [$event]),
            ]);
            // Der Event-Inhalt gehört ausschliesslich dem Event-Szenario
            self::assertSame(0, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE scenario_key<>'grossanlass-event' AND (seed_key LIKE '%:wunsch:%' OR seed_key LIKE '%:bauprojekt:%')"));
            foreach (['grossanlass-camp', 'grossanlass-setup', 'materialverwaltung'] as $other) {
                self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM "group" g JOIN department d ON d.id=g.department_id WHERE d.demo_scenario_key=? AND g.window_start IS NOT NULL', [$other]), $other);
            }

            // Wiederholter Sync: identische Zeilenzahlen, keine Hinweise, andere Szenarien unverändert
            $before = $this->counts($conn);
            $othersBefore = $conn->fetchAllAssociative("SELECT * FROM demo_seed_record WHERE scenario_key IN ('grossanlass-camp','grossanlass-setup','materialverwaltung') ORDER BY seed_key");
            $again = $this->syncAll($runner, $registry, $em);
            foreach ($again as $key => $result) {
                self::assertSame([], $result->notes, $key);
            }
            self::assertSame($before, $this->counts($conn));
            self::assertSame($othersBefore, $conn->fetchAllAssociative("SELECT * FROM demo_seed_record WHERE scenario_key IN ('grossanlass-camp','grossanlass-setup','materialverwaltung') ORDER BY seed_key"));
        });
    }

    public function testManualChangesInTheEventAreProtectedAndDeletedRowsAreRecreated(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $this->syncAll($runner, $registry, $em);
            $event = $this->eventId($conn);
            $group = static fn (string $name): array => $conn->fetchAssociative('SELECT * FROM "group" WHERE department_id=? AND name=?', [$event, $name]) ?: [];
            $podestBefore = $group('Hauptbühnen-Podest');

            $conn->executeStatement("UPDATE \"group\" SET description='Von Hand angepasst', window_end = window_end + interval '5 days' WHERE id=?", [$podestBefore['id']]);
            $conn->executeStatement("UPDATE activity_grossanlass_wish_line SET quantity = 99 WHERE group_id=? AND label LIKE 'Euro-Paletten%'", [$group('Bar-Tresen West')['id']]);
            $conn->executeStatement("UPDATE department_grossanlass_task SET title='Eigener Titel' WHERE title='Podest montieren' AND department_id=?", [$event]);
            $conn->executeStatement("DELETE FROM department_grossanlass_vehicle_need WHERE task_label='Tresen-Elemente liefern' AND department_id=?", [$event]);
            $before = $this->counts($conn);

            $em->clear();
            $result = $runner->sync($registry->get(DemoScenarioKey::GROSSANLASS_EVENT));

            self::assertSame('Von Hand angepasst', $group('Hauptbühnen-Podest')['description']);
            self::assertSame(99, (int) $conn->fetchOne('SELECT quantity FROM activity_grossanlass_wish_line WHERE group_id=? AND label LIKE ?', [$group('Bar-Tresen West')['id'], 'Euro-Paletten%']));
            self::assertSame('Eigener Titel', $conn->fetchOne("SELECT title FROM department_grossanlass_task WHERE department_id=? AND title='Eigener Titel'", [$event]));
            foreach (['bauprojekt:hauptbuehnen-podest', 'wunsch:bar-tresen-west:euro-paletten-neuwertig', 'aufgabe:podest-montage'] as $seedKey) {
                self::assertNotEmpty(array_filter($result->notes, static fn (string $n): bool => str_contains($n, 'Abweichung') && str_contains($n, 'grossanlass-event:' . $seedKey)), $seedKey);
            }
            // gelöschter Fahrzeugbedarf wird wieder angelegt, sonst keine Dubletten
            self::assertSame($before['demo_seed_record'], $this->counts($conn)['demo_seed_record']);
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM department_grossanlass_vehicle_need WHERE task_label='Tresen-Elemente liefern' AND department_id=?", [$event]));
            self::assertSame($before['"group"'], $this->counts($conn)['"group"']);
            self::assertSame([], $runner->verify($registry->get(DemoScenarioKey::GROSSANLASS_EVENT)));
        });
    }

    public function testLegacySeparateOrganisationsAreMergedKeepingIdsAndMigratingScopes(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $this->syncAll($runner, $registry, $em);
            $ids = [];
            foreach ($conn->fetchAllAssociative('SELECT id, name, demo_scenario_key FROM department WHERE demo_mode = true') as $row) {
                $ids[$row['name']] = $row['id'];
            }
            $shared = (string) $conn->fetchOne("SELECT entity_id FROM demo_seed_record WHERE seed_key='demo-organisation:organisation'");

            // Zustand vor der Zusammenführung herstellen: je Szenario eine Organisation (Materialverwaltung = spätere gemeinsame)
            $record = static function (string $scenario, string $seedKey, string $class, string $entityId, string $hash) use ($conn): void {
                $conn->executeStatement(
                    "INSERT INTO demo_seed_record (id, scenario_key, seed_key, entity_class, entity_id, department_id, managed_hash, catalog_version, created_at) VALUES (substr(md5(random()::text),1,12), ?, ?, ?, ?, NULL, ?, '2026.10.6', now())",
                    [$scenario, $seedKey, $class, $entityId, $hash],
                );
            };
            $conn->executeStatement("UPDATE organisation SET name='Demo Organisation Materialverwaltung' WHERE id=?", [$shared]);
            $record('materialverwaltung', 'materialverwaltung:organisation', \App\Entity\Organisation::class, $shared, ManagedSeedApplier::hash(['name' => 'Demo Organisation Materialverwaltung']));
            $legacy = ['grossanlass-event' => ['legevent0001', 'Demo Organisation Grossanlass Event'], 'grossanlass-camp' => ['legcamp00001', 'Demo Organisation Grossanlass Camp'], 'grossanlass-setup' => ['legsetup0001', 'Demo Organisation Grossanlass Einrichtung']];
            foreach ($legacy as $key => [$orgId, $name]) {
                $conn->executeStatement('INSERT INTO organisation (id,name,created_at,updated_at) VALUES (?,?,now(),now())', [$orgId, $name]);
                $conn->executeStatement('UPDATE department SET organisation_id=? WHERE demo_scenario_key=?', [$orgId, $key]);
                $record($key, $key . ':organisation', \App\Entity\Organisation::class, $orgId, ManagedSeedApplier::hash(['name' => $name]));
            }
            $conn->executeStatement("UPDATE department SET organisation_id='legevent0001' WHERE name='Demo Gast-Abteilung'");
            $conn->executeStatement("DELETE FROM demo_seed_record WHERE seed_key='demo-organisation:organisation' OR seed_key LIKE '%:department-organisation%'");
            $conn->executeStatement("UPDATE department SET parent_id=NULL WHERE demo_scenario_key='grossanlass-setup'");
            $conn->executeStatement("DELETE FROM demo_seed_record WHERE seed_key='grossanlass-setup:department-parent'");
            // frühere Verwaltungsbereiche: Orgchef = Kantonalverband + Organisation Camp, Suborgchef = Abteilung Süd + Organisation Event
            $setScope = static function (string $account, array $roots, array $orgs) use ($conn): void {
                $conn->executeStatement('UPDATE profile SET admin_capabilities=? WHERE email=?', [json_encode(['scope' => ['organisation_ids' => $orgs, 'department_root_ids' => $roots]]), DemoAccounts::email($account)]);
            };
            $setScope('orgchef', [$ids['Demo Kantonalverband']], ['legcamp00001']);
            $setScope('suborgchef', [$ids['Demo Abteilung Süd']], ['legevent0001']);
            $conn->executeStatement('UPDATE demo_seed_record SET managed_hash=? WHERE seed_key=?', [ManagedSeedApplier::hash(['department_root_ids' => [$ids['Demo Kantonalverband']], 'organisation_ids' => []]), 'materialverwaltung:adminscope:orgchef']);
            $conn->executeStatement('UPDATE demo_seed_record SET managed_hash=? WHERE seed_key=?', [ManagedSeedApplier::hash(['department_root_ids' => [$ids['Demo Abteilung Süd']], 'organisation_ids' => []]), 'materialverwaltung:adminscope:suborgchef']);
            $conn->executeStatement('UPDATE demo_seed_record SET managed_hash=? WHERE seed_key=?', [ManagedSeedApplier::hash(['department_root_ids' => [], 'organisation_ids' => ['legcamp00001']]), 'grossanlass-camp:adminscope:orgchef']);
            $conn->executeStatement('UPDATE demo_seed_record SET managed_hash=? WHERE seed_key=?', [ManagedSeedApplier::hash(['department_root_ids' => [], 'organisation_ids' => ['legevent0001']]), 'grossanlass-event:adminscope:suborgchef']);
            $departmentsBefore = $conn->fetchFirstColumn('SELECT id FROM department ORDER BY id');
            $membershipsBefore = $conn->fetchFirstColumn('SELECT user_id || department_id FROM membership ORDER BY 1');

            $results = $this->syncAll($runner, $registry, $em);
            foreach ($results as $key => $result) {
                self::assertSame(SeedResult::OK, $result->status, $key);
                foreach ($result->notes as $note) {
                    self::assertStringNotContainsString('Konflikt', $note, $key);
                    self::assertStringNotContainsString('Abweichung', $note, $key);
                }
            }

            // IDs, Mitgliedschaften und die Organisation der Materialverwaltung bleiben; alle Demo-Departments liegen darin
            self::assertSame($departmentsBefore, $conn->fetchFirstColumn('SELECT id FROM department ORDER BY id'));
            self::assertSame($membershipsBefore, $conn->fetchFirstColumn('SELECT user_id || department_id FROM membership ORDER BY 1'));
            self::assertSame([$shared], $conn->fetchFirstColumn('SELECT DISTINCT organisation_id FROM department WHERE demo_mode = true'));
            self::assertSame('Demo Organisation', $conn->fetchOne('SELECT name FROM organisation WHERE id=?', [$shared]));
            self::assertSame($shared, $conn->fetchOne("SELECT entity_id FROM demo_seed_record WHERE seed_key='demo-organisation:organisation'"));
            // Die früheren Szenario-Organisationen werden weder gelöscht noch umbenannt
            foreach ($legacy as [$orgId, $name]) {
                self::assertSame($name, $conn->fetchOne('SELECT name FROM organisation WHERE id=?', [$orgId]));
            }
            self::assertNull($conn->fetchOne("SELECT parent_id FROM department WHERE demo_scenario_key='grossanlass-setup'") ?: null);

            // Verwaltungsbereiche: gleiche Departments wie zuvor, die alten Organisations-Einträge sind durch Department-Wurzeln ersetzt
            $scope = static function (string $account) use ($conn): array {
                $raw = json_decode((string) $conn->fetchOne('SELECT admin_capabilities::text FROM profile WHERE email=?', [DemoAccounts::email($account)]), true)['scope'];
                sort($raw['department_root_ids']);

                return [$raw['department_root_ids'], $raw['organisation_ids']];
            };
            $camp = $ids['Demo Grossanlass Camp'];
            $eventId = $this->eventId($conn);
            $expectedOrgchef = [$ids['Demo Kantonalverband'], $camp];
            sort($expectedOrgchef);
            $expectedSuborgchef = [$ids['Demo Abteilung Süd'], $eventId, $ids['Demo Gast-Abteilung']];
            sort($expectedSuborgchef);
            self::assertSame([$expectedOrgchef, []], $scope('orgchef'));
            self::assertSame([$expectedSuborgchef, []], $scope('suborgchef'));

            // Wiederholung: nichts ändert sich mehr
            $before = $this->counts($conn);
            foreach ($this->syncAll($runner, $registry, $em) as $key => $result) {
                self::assertSame([], array_values(array_filter($result->notes, static fn (string $n): bool => !str_starts_with($n, 'Verwaist'))), $key);
            }
            self::assertSame($before, $this->counts($conn));
        });
    }
}
