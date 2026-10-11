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
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
            try {
                $test($c->get(DemoScenarioRunner::class), $c->get(DemoScenarioRegistry::class), $em, $conn, $c);
            } finally {
                $conn->rollBack();
            }
        }, $url, [DemoScenarioRunner::class, DemoScenarioRegistry::class, DemoOrganisationCatalog::class, \App\Service\Demo\Legacy\LegacyDemoRename::class, \App\Service\Bootstrap\DemoGrossanlassSeedService::class, \App\Service\Grossanlass\GrossanlassBudgetAreaService::class, \App\Service\Grossanlass\GrossanlassChargeMovementService::class, \App\Service\Grossanlass\GrossanlassPackService::class, \App\Service\Clock\BusinessClock::class, \App\Service\Grossanlass\GrossanlassPlaceService::class]);
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
            self::assertSame('Event Platz', $config['location_text']);
            self::assertStringContainsString('Mehrtägige Pfadi-Grossveranstaltung', (string) $config['notes']);
            $venue = $conn->fetchAssociative('SELECT * FROM address WHERE id=?', [$config['venue_address_id']]);
            self::assertSame(['event', 'Event Platz', '', '6212', 'Kaltbach', 'Schweiz'], [$venue['type'], $venue['name'], (string) $venue['street'], $venue['postal_code'], $venue['city'], $venue['country']]);
            // Zustellpunkt (hängt am Eventplatz) und Lager (primär) mit je eigenen Koordinaten
            $delivery = $conn->fetchAssociative('SELECT * FROM address WHERE department_id=? AND type=?', [$event, 'event_delivery']);
            $storage = $conn->fetchAssociative('SELECT * FROM address WHERE department_id=? AND type=?', [$event, 'storage']);
            self::assertSame([$venue['id'], 'Ettiswilerstrasse', '6242'], [$delivery['parent_id'], $delivery['street'], $delivery['postal_code']]);
            self::assertTrue((bool) $storage['is_primary']);
            self::assertCount(3, array_unique([$venue['latitude'] . '/' . $venue['longitude'], $delivery['latitude'] . '/' . $delivery['longitude'], $storage['latitude'] . '/' . $storage['longitude']]));
            // Feste Event-Zeiten 2027 (lokale Wandzeit): Anlasszeitraum (Aufbau bis Abbau) in Anlass und Hauptaktivität, Kalenderperioden Aufbau/Durchführung/Abbau
            self::assertSame(['2027-05-19 13:00:00', '2027-06-23 12:00:00'], [(string) $config['planned_event_start'], (string) $config['planned_event_end']]);
            self::assertSame(['2027-05-19 13:00:00', '2027-06-23 12:00:00'], array_values($conn->fetchNumeric('SELECT a.usage_start, a.usage_end FROM activity a JOIN department_grossanlass_config c ON c.main_activity_id=a.id WHERE c.department_id=?', [$event])));
            $periods = [];
            foreach ($conn->fetchAllAssociative('SELECT label, start_date || \' \' || start_time AS s, end_date || \' \' || end_time AS e FROM department_calendar_period WHERE department_id=? ORDER BY label', [$event]) as $row) {
                $periods[$row['label']] = [$row['s'], $row['e']];
            }
            ksort($periods);
            self::assertSame([
                'abbau' => ['2027-06-06 17:00:00', '2027-06-23 12:00:00'],
                'aufbau' => ['2027-05-19 13:00:00', '2027-06-04 16:00:00'],
                'grossanlass' => ['2027-06-04 16:00:00', '2027-06-06 16:00:00'],
            ], $periods);
            // Schnellwahl der Demo-Uhr als Einstellung (feste Wandzeiten, mitten in den Phasen)
            $presets = json_decode((string) $conn->fetchOne("SELECT setting_value FROM department_setting WHERE department_id=? AND setting_key='demo_clock_presets'", [$event]), true);
            self::assertSame(
                ['2027-01-04T16:00:00', '2027-04-04T16:00:00', '2027-05-31T14:00:00', '2027-06-05T17:00:00', '2027-06-08T08:00:00', '2027-08-06T16:00:00'],
                array_column($presets, 'at'),
            );
            // Demo-Uhr: einmalige Erstinitialisierung auf 04.04.2027 16:00 (Wandzeit)
            $offset = (int) $conn->fetchOne('SELECT demo_clock_offset_seconds FROM department WHERE id=?', [$event]);
            self::assertEqualsWithDelta((new \DateTimeImmutable('2027-04-04 16:00:00', new \DateTimeZone('UTC')))->getTimestamp(), time() + $offset, 180);
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM demo_seed_record WHERE seed_key='grossanlass-event:clock'"));

            // Ressorts und Bereiche (Organisationsstruktur) sowie Bauprojekte: 13 Katalog-Gruppen + 6 Gewerke + 20 Bauprojekte
            self::assertSame(13 + 6 + 20, (int) $conn->fetchOne('SELECT count(*) FROM "group" WHERE department_id=?', [$event]));
            self::assertSame(8, (int) $conn->fetchOne("SELECT count(*) FROM \"group\" WHERE department_id=? AND grossanlass_kind='ressort'", [$event]));
            self::assertSame(20, (int) $conn->fetchOne("SELECT count(*) FROM \"group\" WHERE department_id=? AND grossanlass_kind='teilbereich' AND build_status='planned'", [$event]));
            // Feste Zeitfenster: vom Aufbaubeginn (19.05.) bis zum Ende der Rücklieferung innerhalb des Abbaus (16.06.)
            self::assertSame('2027-05-19', substr((string) $conn->fetchOne('SELECT min(window_start) FROM "group" WHERE department_id=?', [$event]), 0, 10));
            self::assertSame('2027-06-16', substr((string) $conn->fetchOne('SELECT max(window_end) FROM "group" WHERE department_id=?', [$event]), 0, 10));
            self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM "group" WHERE department_id=? AND (window_start < DATE \'2027-05-19\' OR window_end > DATE \'2027-06-23\')', [$event]));
            // Materialwünsche, Orte, Aufgaben, Fahrzeugbedarfe, Beschaffung und Kosten (6 aus Beschaffung/Zusagen, 2 aus den Chargen Leihe und Spende)
            self::assertSame(57, (int) $conn->fetchOne('SELECT count(*) FROM activity_grossanlass_wish_line l JOIN "group" g ON g.id=l.group_id WHERE g.department_id=?', [$event]));
            self::assertSame(20, (int) $conn->fetchOne("SELECT count(*) FROM department_grossanlass_place WHERE department_id=? AND kind='bauprojekt'", [$event]));
            self::assertSame([7, 6, 5, 8], [
                (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_task WHERE department_id=?', [$event]),
                (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_vehicle_need WHERE department_id=?', [$event]),
                (int) $conn->fetchOne('SELECT count(*) FROM activity_grossanlass_procurement_line WHERE department_id=?', [$event]),
                (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_cost WHERE department_id=?', [$event]),
            ]);
            // Beschaffungsablauf: Bedarf → Anfrage → Offerte (PDF) → Budget → Bestellung → Zusage → Einsatz → Packauftrag
            $row = static fn (string $sql): array => $conn->fetchAllAssociative($sql, [$event]);
            self::assertSame(['bestellt', 'bedarf', 'budgetiert', 'offerte_eingeholt', 'bedarf'], array_column($row("SELECT status FROM activity_grossanlass_procurement_line WHERE department_id=? ORDER BY CASE label WHEN 'Schwartenbretter' THEN 0 WHEN 'Festzelt 3x3 m' THEN 1 WHEN 'Teleskoplader' THEN 2 WHEN 'Nägel 5x80' THEN 3 ELSE 4 END"), 'status'));
            self::assertSame([3, 2], [
                (int) $conn->fetchOne('SELECT count(*) FROM activity_grossanlass_procurement_quote q JOIN activity_grossanlass_procurement_line l ON l.id=q.procurement_line_id WHERE l.department_id=?', [$event]),
                (int) $conn->fetchOne('SELECT count(*) FROM activity_grossanlass_procurement_quote q JOIN activity_grossanlass_procurement_line l ON l.id=q.procurement_line_id WHERE l.department_id=? AND q.pdf_filename IS NOT NULL', [$event]),
            ]);
            // Bestellung gehört zur gewählten Offerte derselben Position und zur Kauf-Zusage; die Zusage der Miete kommt aus der Anfrage
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM activity_grossanlass_procurement_order o JOIN activity_grossanlass_procurement_quote q ON q.procurement_line_id=o.procurement_line_id AND q.selected = true JOIN department_grossanlass_commitment c ON c.procurement_line_id=o.procurement_line_id AND c.origin='buy' WHERE c.department_id=? AND o.order_ref='B-2027-014' AND q.amount_chf = o.cost_chf", [$event]));
            $origins = [];
            foreach ($conn->fetchAllAssociative('SELECT origin, count(*) AS n FROM department_grossanlass_commitment WHERE department_id=? GROUP BY origin', [$event]) as $r) {
                $origins[$r['origin']] = (int) $r['n'];
            }
            ksort($origins);
            // Beschaffung: 1 Kauf (Bestellung) und 1 Miete (Zusage); Chargen: Eigenbestand, Leihe, Spende
            self::assertSame(['buy' => 1, 'donation' => 1, 'loan' => 2, 'own' => 1], $origins);
            self::assertSame(['antwort', 'entwurf', 'gesendet', 'zusage'], $conn->fetchFirstColumn('SELECT status FROM department_grossanlass_inquiry WHERE department_id=? ORDER BY status', [$event]));
            self::assertSame([4, 4], [
                (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz WHERE department_id=?', [$event]),
                (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_pack WHERE department_id=?', [$event]),
            ]);
            // Packaufträge: neue, eindeutige öffentliche Codes (keine Legacy-Codes)
            $codes = $conn->fetchFirstColumn('SELECT public_code FROM department_grossanlass_pack WHERE department_id=?', [$event]);
            self::assertCount(4, array_unique($codes));
            self::assertSame([], array_values(array_intersect($codes, ['pk03ac0d0924', 'pk192ad9fe02', 'pkabe9899b32', 'pkbb2dde53cb'])));
            // Geländekarte: Datei (Hash im Katalog), Bounds, Opazität, platzierte Orte mit Koordinaten
            $map = $conn->fetchAssociative('SELECT * FROM department_grossanlass_map WHERE department_id=?', [$event]);
            self::assertSame(['Gelände (© swisstopo)', 943, 721], [$map['name'], (int) $map['image_width'], (int) $map['image_height']]);
            self::assertEqualsWithDelta([47.18446266494, 47.176207763535, 8.0551671981812, 8.0383444725869, 0.72], [(float) $map['bounds_north'], (float) $map['bounds_south'], (float) $map['bounds_east'], (float) $map['bounds_west'], (float) $map['overlay_opacity']], 1e-9);
            self::assertSame(20, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_place WHERE department_id=? AND map_id=? AND map_x IS NOT NULL AND latitude IS NOT NULL', [$event, $map['id']]));
            self::assertFileExists(dirname(__DIR__, 2) . '/var/uploads/' . $event . '/photos/grossanlass-map/' . $map['id'] . '/' . $map['image_filename']);
            self::assertSame((new DemoOrganisationCatalog())->scenario('grossanlass-event')['grossanlass']['map']['sha256'], hash_file('sha256', dirname(__DIR__, 2) . '/data/seeds/dev-demo/files/event-gelaende.webp'));
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
            self::assertSame($offset, (int) $conn->fetchOne('SELECT demo_clock_offset_seconds FROM department WHERE id=?', [$event]));
            // Vom Benutzer verstellte Uhr (z. B. «Während Event») bleibt nach jedem weiteren Sync erhalten
            $manual = (new \DateTimeImmutable('2027-06-05 12:00:00', new \DateTimeZone('UTC')))->getTimestamp() - time();
            $conn->executeStatement('UPDATE department SET demo_clock_offset_seconds=? WHERE id=?', [$manual, $event]);
            $this->syncAll($runner, $registry, $em);
            self::assertSame($manual, (int) $conn->fetchOne('SELECT demo_clock_offset_seconds FROM department WHERE id=?', [$event]));
            // Auch eine von Hand geänderte Schnellwahl und die Anlassdaten bleiben nach dem Sync erhalten; die Uhr ändert nur die Zeit
            $conn->executeStatement("UPDATE department_setting SET setting_value=? WHERE department_id=? AND setting_key='demo_clock_presets'", [json_encode([['key' => 'build', 'at' => '2027-05-25T10:00:00']]), $event]);
            $periodBefore = $conn->fetchAssociative('SELECT planned_event_start, planned_event_end FROM department_grossanlass_config WHERE department_id=?', [$event]);
            $this->syncAll($runner, $registry, $em);
            self::assertSame([['key' => 'build', 'at' => '2027-05-25T10:00:00']], json_decode((string) $conn->fetchOne("SELECT setting_value FROM department_setting WHERE department_id=? AND setting_key='demo_clock_presets'", [$event]), true));
            self::assertSame($periodBefore, $conn->fetchAssociative('SELECT planned_event_start, planned_event_end FROM department_grossanlass_config WHERE department_id=?', [$event]));
            self::assertSame($othersBefore, $conn->fetchAllAssociative("SELECT * FROM demo_seed_record WHERE scenario_key IN ('grossanlass-camp','grossanlass-setup','materialverwaltung') ORDER BY seed_key"));
        });
    }

    public function testLogisticsMasterDataBudgetAndStableQrCodes(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn, $container): void {
            $this->syncAll($runner, $registry, $em);
            $event = $this->eventId($conn);
            $key = DemoScenarioKey::GROSSANLASS_EVENT;

            // Budget: je ein Limit für Material und Logistik, Gesamtbudget = Summe (Anlass-Rahmen folgt)
            $limits = [];
            foreach ($conn->fetchAllAssociative('SELECT area, limit_chf FROM department_grossanlass_budget_area WHERE department_id=?', [$event]) as $row) {
                $limits[$row['area']] = (float) $row['limit_chf'];
            }
            ksort($limits);
            self::assertSame(['logistik' => 900.0, 'material' => 2000.0], $limits);
            self::assertSame(2900.0, (float) $conn->fetchOne('SELECT rahmen_chf FROM department_grossanlass_budget WHERE department_id=? AND payer_group_id IS NULL', [$event]) ?: (float) $conn->fetchOne('SELECT rahmen_chf FROM activity_grossanlass_procurement_finance WHERE department_id=?', [$event]));

            // Budgetauswertung mit den Demo-Positionen: jede Position einmal, Teleskoplader (Fahrzeug) in der Logistik
            $mw = $conn->fetchOne('SELECT u.id FROM "user" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?', [DemoAccounts::email('ga-mw')]);
            $overview = $container->get(\App\Service\Grossanlass\GrossanlassBudgetAreaService::class)->overview($em->find(\App\Entity\Department::class, $event), $em->find(\App\Entity\User::class, $mw));
            self::assertSame(2900.0, $overview['total']['planned_chf']);
            self::assertSame(round($overview['material']['expected_chf'] + $overview['logistik']['expected_chf'], 2), $overview['total']['expected_chf']);
            self::assertSame(950.0, $overview['logistik']['stages']['zusage']);
            self::assertSame(220.0, $overview['material']['stages']['bestellt']);
            self::assertTrue($overview['logistik']['over_budget']);
            self::assertSame(50.0, $overview['logistik']['overrun_committed_chf']);
            self::assertFalse($overview['material']['over_budget']);
            // 5 Beschaffungspositionen und je eine Kostenzeile der Leihe (Festbänke) und der Spende (Holzschnitzel); die Zusage der Miete zählt nicht doppelt
            self::assertSame(7, $overview['total']['position_count']);

            // Lagerstandort mit Regalen und Fächern; Fahrzeuge, Fahrerkarten, Chargen mit Eigentümer
            $storage = (string) $conn->fetchOne("SELECT id FROM address WHERE department_id=? AND type='storage'", [$event]);
            self::assertSame(2, (int) $conn->fetchOne('SELECT count(*) FROM storage_rack WHERE department_id=? AND storage_address_id=?', [$event, $storage]));
            self::assertSame(5, (int) $conn->fetchOne('SELECT count(*) FROM storage_slot s JOIN storage_rack r ON r.id=s.rack_id WHERE r.department_id=?', [$event]));
            self::assertSame(3, (int) $conn->fetchOne('SELECT count(*) FROM department_vehicle WHERE department_id=?', [$event]));
            self::assertSame(3, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_user_card WHERE department_id=? AND may_drive=true', [$event]));
            $owners = $conn->fetchAllAssociative("SELECT origin, owner_kind FROM department_grossanlass_commitment WHERE department_id=? AND source IN ('Eigenbestand Demo Grossanlass','Zeltverleih Beispiel','Sponsoring Beispiel AG') ORDER BY origin", [$event]);
            self::assertSame([['origin' => 'donation', 'owner_kind' => 'grossanlass'], ['origin' => 'loan', 'owner_kind' => 'external'], ['origin' => 'own', 'owner_kind' => 'grossanlass']], $owners);
            // Der Seed bucht keine Warenbewegung
            self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_charge_movement WHERE department_id=?', [$event]));

            // QR-Identitäten: reproduzierbar aus dem Seed-Schlüssel und ohne Übernahme von Legacy-Codes
            $placeCodes = $conn->fetchAllAssociative("SELECT p.public_code, r.seed_key FROM department_grossanlass_place p JOIN demo_seed_record r ON r.entity_id=p.id AND r.entity_class=? WHERE p.department_id=? AND r.seed_key LIKE '%:ort:%'", [\App\Entity\DepartmentGrossanlassPlace::class, $event]);
            self::assertCount(20, $placeCodes);
            foreach ($placeCodes as $row) {
                self::assertSame(\App\Service\Demo\DemoStableCodes::prefixed('ga', $key, $row['seed_key']), $row['public_code'], $row['seed_key']);
            }
            $packCodes = $conn->fetchAllAssociative("SELECT p.public_code, r.seed_key FROM department_grossanlass_pack p JOIN demo_seed_record r ON r.entity_id=p.id AND r.entity_class=? WHERE p.department_id=?", [\App\Entity\DepartmentGrossanlassPack::class, $event]);
            self::assertCount(4, $packCodes);
            foreach ($packCodes as $row) {
                self::assertSame(\App\Service\Demo\DemoStableCodes::prefixed('pk', $key, $row['seed_key']), $row['public_code'], $row['seed_key']);
            }
            $storageCodes = $conn->fetchFirstColumn("SELECT public_code FROM public_code WHERE department_id=? AND entity_type LIKE 'storage_%' ORDER BY public_code", [$event]);
            self::assertCount(1 + 2 + 5, $storageCodes);

            // Die Codes lösen tatsächlich auf (Pack-QR /i/k/…, Ort-QR /i/ga/…) und die Orte liegen auf der Karte innerhalb ihrer Grenzen
            $packService = $container->get(\App\Service\Grossanlass\GrossanlassPackService::class);
            foreach ($packCodes as $row) {
                self::assertNotNull($packService->resolvePublic($row['public_code']), $row['seed_key']);
            }
            $placeService = $container->get(\App\Service\Grossanlass\GrossanlassPlaceService::class);
            foreach ($placeCodes as $row) {
                self::assertSame($event, $placeService->resolvePublic($row['public_code'])['department']['id'] ?? null, $row['seed_key']);
            }
            $map = $conn->fetchAssociative('SELECT * FROM department_grossanlass_map WHERE department_id=?', [$event]);
            $placed = $conn->fetchAllAssociative('SELECT name, map_x, map_y, latitude, longitude FROM department_grossanlass_place WHERE department_id=? AND map_id=?', [$event, $map['id']]);
            self::assertCount(20, $placed);
            foreach ($placed as $p) {
                self::assertGreaterThanOrEqual((float) $map['bounds_south'], (float) $p['latitude'], $p['name']);
                self::assertLessThanOrEqual((float) $map['bounds_north'], (float) $p['latitude'], $p['name']);
                self::assertGreaterThanOrEqual((float) $map['bounds_west'], (float) $p['longitude'], $p['name']);
                self::assertLessThanOrEqual((float) $map['bounds_east'], (float) $p['longitude'], $p['name']);
                // Position auf dem Bild und geografische Koordinaten passen zusammen
                self::assertEqualsWithDelta((float) $map['bounds_west'] + (float) $p['map_x'] * ((float) $map['bounds_east'] - (float) $map['bounds_west']), (float) $p['longitude'], 1e-6, $p['name']);
                self::assertEqualsWithDelta((float) $map['bounds_north'] - (float) $p['map_y'] * ((float) $map['bounds_north'] - (float) $map['bounds_south']), (float) $p['latitude'], 1e-6, $p['name']);
            }
            // Eventplatz, Lager und Kartenmitte liegen im Kartenausschnitt; der Zustellpunkt liegt ausserhalb (eigene Adresse in Wauwil)
            $inside = static fn (array $a): bool => (float) $a['latitude'] >= (float) $map['bounds_south'] && (float) $a['latitude'] <= (float) $map['bounds_north'] && (float) $a['longitude'] >= (float) $map['bounds_west'] && (float) $a['longitude'] <= (float) $map['bounds_east'];
            $addr = static fn (string $type): array => $conn->fetchAssociative('SELECT latitude, longitude FROM address WHERE department_id=? AND type=?', [$event, $type]) ?: [];
            self::assertTrue($inside($addr('event')));
            self::assertTrue($inside($addr('storage')));
            self::assertFalse($inside($addr('event_delivery')));

            // Nach Löschen und erneutem Sync trägt der Datensatz denselben QR-Code
            $before = $conn->fetchOne("SELECT p.public_code FROM department_grossanlass_place p JOIN \"group\" g ON g.id=p.group_id WHERE p.department_id=? AND g.name='Bar-Tresen West'", [$event]);
            $conn->executeStatement("DELETE FROM department_grossanlass_place WHERE department_id=? AND group_id=(SELECT id FROM \"group\" WHERE department_id=? AND name='Bar-Tresen West')", [$event, $event]);
            $this->syncAll($runner, $registry, $em);
            $after = $conn->fetchOne("SELECT p.public_code FROM department_grossanlass_place p JOIN \"group\" g ON g.id=p.group_id WHERE p.department_id=? AND g.name='Bar-Tresen West'", [$event]);
            self::assertSame($before, $after);
            self::assertNotFalse($after);
        });
    }

    public function testProcurementFlowToTheSiteRunsThroughTheServicesAndClockJumpsBookNothing(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn, $container): void {
            $this->syncAll($runner, $registry, $em);
            $event = $this->eventId($conn);
            $department = $em->find(\App\Entity\Department::class, $event);
            $user = static fn (string $account) => $em->find(\App\Entity\User::class, $conn->fetchOne('SELECT u.id FROM "user" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?', [DemoAccounts::email($account)]));
            $state = static fn (): array => [
                'orders' => (int) $conn->fetchOne('SELECT count(*) FROM activity_grossanlass_procurement_order o JOIN activity_grossanlass_procurement_line l ON l.id=o.procurement_line_id WHERE l.department_id=?', [$event]),
                'movements' => (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_charge_movement WHERE department_id=?', [$event]),
                'packs' => (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_pack WHERE department_id=?', [$event]),
                'costs' => $conn->fetchAllAssociative('SELECT id, status, cash_out_chf FROM department_grossanlass_cost WHERE department_id=? ORDER BY id', [$event]),
                'pack_status' => $conn->fetchFirstColumn('SELECT status FROM department_grossanlass_pack WHERE department_id=? ORDER BY id', [$event]),
            ];
            $overview = fn (): array => $container->get(\App\Service\Grossanlass\GrossanlassBudgetAreaService::class)->overview($department, $user('ga-mw'));

            // Ausgangslage der Demo-Uhr (04.04.2027): Bestellung ist aufgegeben, Ware noch nicht eingegangen
            $baseline = $state();
            self::assertSame(1, $baseline['orders']);
            self::assertSame(220.0, $overview()['material']['stages']['bestellt']);

            // Zeitsprünge allein buchen nichts: weder Bestellung noch Bestand noch Packstatus
            $clock = $container->get(\App\Service\Clock\BusinessClock::class);
            foreach (['2027-01-04 16:00:00', '2027-05-31 14:00:00', '2027-06-05 17:00:00', '2027-06-08 08:00:00', '2027-08-06 16:00:00'] as $at) {
                $clock->travelTo($department, new \DateTimeImmutable($at, new \DateTimeZone('UTC')));
                $em->flush();
                self::assertSame($baseline, $state(), $at);
            }

            // Aufbau (19.05.2027): Wareneingang der bestellten Schwartenbretter, ausdrücklich gebucht
            $clock->travelTo($department, new \DateTimeImmutable('2027-05-19 12:30:00', new \DateTimeZone('UTC')));
            $commitment = (string) $conn->fetchOne("SELECT c.id FROM department_grossanlass_commitment c JOIN activity_grossanlass_procurement_line l ON l.id=c.procurement_line_id WHERE c.department_id=? AND l.label='Schwartenbretter'", [$event]);
            $movements = $container->get(\App\Service\Grossanlass\GrossanlassChargeMovementService::class);
            $booked = $movements->record($department, $user('ga-mw'), $commitment, ['kind' => 'received', 'quantity' => 30, 'occurred_at' => '2027-05-19T12:30:00']);
            self::assertSame('complete', $booked['inbound']['state']);
            self::assertSame(1, $state()['movements']);
            $after = $overview();
            self::assertSame([0.0, 220.0], [$after['material']['stages']['bestellt'], $after['material']['stages']['effektiv']]);
            self::assertSame(220.0, $after['material']['actual_chf']);
            // Überlieferung und noch nicht buchbare Rückgaben werden abgelehnt (Rücknahme ist im Modell vorbereitet, aber nicht buchbar)
            try {
                $movements->record($department, $user('ga-mw'), $commitment, ['kind' => 'received', 'quantity' => 1]);
                self::fail('Überlieferung wurde nicht abgelehnt.');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Überlieferung', $e->getMessage());
            }
            try {
                $movements->record($department, $user('ga-mw'), $commitment, ['kind' => 'returned_to_store', 'quantity' => 30]);
                self::fail('Rückbuchung ins Lager ist heute nicht buchbar.');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('noch nicht verfügbar', $e->getMessage());
            }
        });
    }

    public function testEventResetRestoresSeedValuesAndClockWithoutDeletingOrTouchingOthers(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn, $container): void {
            $this->syncAll($runner, $registry, $em);
            $event = $this->eventId($conn);
            $department = $em->find(\App\Entity\Department::class, $event);
            $mw = $em->find(\App\Entity\User::class, $conn->fetchOne('SELECT u.id FROM "user" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?', [DemoAccounts::email('ga-mw')]));
            $podest = static fn (): array => $conn->fetchAssociative('SELECT description, window_end FROM "group" WHERE department_id=? AND name=?', [$event, 'Hauptbühnen-Podest']) ?: [];
            $venue = static fn (): string => (string) $conn->fetchOne("SELECT latitude FROM address WHERE department_id=? AND type='event'", [$event]);
            $storageCodes = static fn (): array => $conn->fetchFirstColumn("SELECT public_code FROM public_code WHERE department_id=? AND entity_type LIKE 'storage_%' ORDER BY public_code", [$event]);
            $packCodes = static fn (): array => $conn->fetchFirstColumn('SELECT public_code FROM department_grossanlass_pack WHERE department_id=? ORDER BY public_code', [$event]);
            $others = static fn (): array => $conn->fetchAllAssociative("SELECT seed_key, managed_hash FROM demo_seed_record WHERE scenario_key IN ('grossanlass-camp','grossanlass-setup','materialverwaltung') ORDER BY seed_key");
            $members = static fn (): array => $conn->fetchAllAssociative('SELECT user_id, role FROM membership WHERE department_id=? ORDER BY user_id', [$event]);
            $seeded = ['podest' => $podest(), 'venue' => $venue(), 'storage' => $storageCodes(), 'packs' => $packCodes(), 'others' => $others(), 'members' => $members()];

            // Von Hand verändert: verwalteter Wert, Adresse, ein gelöschtes Regal, die Uhr und eine echte Wareneingangsbuchung
            $conn->executeStatement("UPDATE \"group\" SET description='Von Hand', window_end = window_end + interval '5 days' WHERE department_id=? AND name='Hauptbühnen-Podest'", [$event]);
            $conn->executeStatement("UPDATE address SET latitude=47.5 WHERE department_id=? AND type='event'", [$event]);
            $conn->executeStatement("DELETE FROM storage_rack WHERE department_id=? AND name LIKE 'Regal A%'", [$event]);
            $commitment = (string) $conn->fetchOne("SELECT c.id FROM department_grossanlass_commitment c JOIN activity_grossanlass_procurement_line l ON l.id=c.procurement_line_id WHERE c.department_id=? AND l.label='Schwartenbretter'", [$event]);
            $em->clear();
            $department = $em->find(\App\Entity\Department::class, $event);
            $container->get(\App\Service\Grossanlass\GrossanlassChargeMovementService::class)->record($department, $em->find(\App\Entity\User::class, $mw->getId()), $commitment, ['kind' => 'received', 'quantity' => 10]);
            $container->get(\App\Service\Clock\BusinessClock::class)->travelTo($department, new \DateTimeImmutable('2027-06-05 17:00:00', new \DateTimeZone('UTC')));
            $em->flush();
            $extras = [
                'movements' => (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_charge_movement WHERE department_id=?', [$event]),
                'commitments' => (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_commitment WHERE department_id=?', [$event]),
            ];
            self::assertSame(1, $extras['movements']);
            self::assertNotSame($seeded['podest'], $podest());

            $em->clear();
            $result = $runner->reset($registry->get(DemoScenarioKey::GROSSANLASS_EVENT));
            self::assertSame(SeedResult::OK, $result->status);
            self::assertSame([], $result->data['delete']);
            self::assertSame([], $result->data['blocked']);

            // Verwaltete Werte und gelöschte Seed-Datensätze sind zurück, QR-Codes unverändert
            self::assertSame($seeded['podest'], $podest());
            self::assertSame($seeded['venue'], $venue());
            self::assertSame($seeded['storage'], $storageCodes());
            self::assertSame($seeded['packs'], $packCodes());
            self::assertSame(2, (int) $conn->fetchOne('SELECT count(*) FROM storage_rack WHERE department_id=?', [$event]));
            // Nichts wurde gelöscht oder zurückgebucht: Prozessdaten bleiben unverändert
            self::assertSame($extras['movements'], (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_charge_movement WHERE department_id=?', [$event]));
            self::assertSame($extras['commitments'], (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_commitment WHERE department_id=?', [$event]));
            // Uhr zurück auf 04.04.2027 16:00; Benutzer, Rollen und andere Szenarien unverändert
            $offset = (int) $conn->fetchOne('SELECT demo_clock_offset_seconds FROM department WHERE id=?', [$event]);
            self::assertEqualsWithDelta((new \DateTimeImmutable('2027-04-04 16:00:00', new \DateTimeZone('UTC')))->getTimestamp(), time() + $offset, 180);
            self::assertSame($seeded['members'], $members());
            self::assertSame($seeded['others'], $others());
            // Zweiter Reset ohne Änderungen: idempotent
            $em->clear();
            $again = $runner->reset($registry->get(DemoScenarioKey::GROSSANLASS_EVENT));
            self::assertSame([], $again->data['restore']['managed']);
            self::assertSame(0, $again->data['restore']['recreated']);
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

    public function testVerifyDetectsMissingFilesAndRepeatedSyncKeepsThemStable(): void
    {
        $this->withDb(function (DemoScenarioRunner $runner, DemoScenarioRegistry $registry, EntityManagerInterface $em, Connection $conn): void {
            $this->syncAll($runner, $registry, $em);
            $event = $registry->get(DemoScenarioKey::GROSSANLASS_EVENT);
            self::assertSame([], $runner->verify($event));
            $department = $this->eventId($conn);
            $uploads = dirname(__DIR__, 2) . '/var/uploads/' . $department;
            $mapFile = (string) $conn->fetchOne('SELECT image_filename FROM department_grossanlass_map WHERE department_id=?', [$department]);
            $mapId = (string) $conn->fetchOne('SELECT id FROM department_grossanlass_map WHERE department_id=?', [$department]);
            $files = glob($uploads . '/*/*/*/*') ?: [];
            self::assertGreaterThanOrEqual(3, \count($files)); // Karte und zwei Offerten-PDFs im Dateispeicher
            $pdfCount = \count(glob($uploads . '/documents/grossanlass-procurement-quote/*/*.pdf') ?: []);
            self::assertSame(2, $pdfCount);
            $codesBefore = $conn->fetchFirstColumn('SELECT public_code FROM department_grossanlass_pack WHERE department_id=? ORDER BY public_code', [$department]);

            // fehlende Karte und fehlendes PDF werden vom Verify erkannt
            unlink($uploads . '/photos/grossanlass-map/' . $mapId . '/' . $mapFile);
            $pdf = (glob($uploads . '/documents/grossanlass-procurement-quote/*/*.pdf') ?: [])[0];
            unlink($pdf);
            $em->clear();
            $violations = $runner->verify($event);
            self::assertNotEmpty(array_filter($violations, static fn (string $v): bool => str_contains($v, 'Karte') && str_contains($v, 'fehlt')));
            self::assertNotEmpty(array_filter($violations, static fn (string $v): bool => str_contains($v, 'Offerten-PDF')));

            // Wiederholter Sync legt weder Dateien noch Datensätze doppelt an; QR-/Pack-Codes bleiben stabil
            $before = $this->counts($conn);
            $em->clear();
            $runner->sync($event);
            self::assertSame($before, $this->counts($conn));
            self::assertSame($codesBefore, $conn->fetchFirstColumn('SELECT public_code FROM department_grossanlass_pack WHERE department_id=? ORDER BY public_code', [$department]));
            self::assertSame(1, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_map WHERE department_id=?', [$department]));
        });
    }
}
