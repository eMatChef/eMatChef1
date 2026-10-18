<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use App\Service\Demo\Scenario\SeedResult;
use App\Service\Display\DisplayClockPayload;
use App\Service\Grossanlass\GrossanlassBauprojektService;
use App\Service\Grossanlass\GrossanlassDispatchService;
use App\Tests\Wiring\FreshKernel;
use App\Util\DemoAccounts;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Aufbau-Demo des Szenarios grossanlass-event (Preset «Aufbau», 31.05.2027 14:00) gegen eine ISOLIERTE Test-Datenbank
 * (EMATCHEF_TEST_DB_URL, Name «val_*» oder «*_test», nie mvdb; Rollback am Ende): vorhandene echte Aufgaben und Fahraufträge,
 * Zeitsprung ohne Buchungen, zulässige Statusschritte über die bestehenden Dienste und fachliche Zeitstempel.
 */
final class DemoBuildPhaseIntegrationTest extends TestCase
{
    private const BUILD = '2027-05-31 14:00:00';

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
                $test($c, $em, $conn);
            } finally {
                $conn->rollBack();
            }
        }, $url, [DemoScenarioRunner::class, DemoScenarioRegistry::class, BusinessClock::class, GrossanlassBauprojektService::class, GrossanlassDispatchService::class, DisplayClockPayload::class, \App\Service\Display\DisplayLiveDataService::class, \App\Service\Grossanlass\GrossanlassPackService::class]);
    }

    private function user(EntityManagerInterface $em, string $account): User
    {
        $id = $em->getConnection()->fetchOne('SELECT u.id FROM "user" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?', [DemoAccounts::email($account)]);
        $user = $em->find(User::class, $id);
        self::assertInstanceOf(User::class, $user, $account);

        return $user;
    }

    /** @return array<string, int|string> Zähler aller fachlichen Verlaufs- und Buchungstabellen, die ein Zeitsprung nie verändern darf */
    private function ledger(Connection $conn, string $event): array
    {
        $out = [];
        foreach (['department_grossanlass_dispatch_event' => 'department_id', 'department_grossanlass_order_event' => 'department_id', 'department_grossanlass_staffing_log' => 'department_id', 'department_grossanlass_charge_movement' => 'department_id'] as $table => $col) {
            $out[$table] = (int) $conn->fetchOne("SELECT count(*) FROM $table WHERE $col = ?", [$event]);
        }
        $out['task_event'] = (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_task_event e JOIN department_grossanlass_task t ON t.id=e.task_id WHERE t.department_id=?', [$event]);
        $out['tour_event'] = (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_tour_event e JOIN department_grossanlass_tour t ON t.id=e.tour_id WHERE t.department_id=?', [$event]);
        $out['tasks'] = (string) $conn->fetchOne("SELECT string_agg(id || ':' || status, ',' ORDER BY id) FROM department_grossanlass_task WHERE department_id=?", [$event]);
        $out['einsatz'] = (string) $conn->fetchOne("SELECT string_agg(id || ':' || status || ':' || packed::text || ':' || coalesce(trip_released_at::text, '-'), ',' ORDER BY id) FROM department_grossanlass_einsatz WHERE department_id=?", [$event]);
        $out['packs'] = (string) $conn->fetchOne("SELECT string_agg(id || ':' || status, ',' ORDER BY id) FROM department_grossanlass_pack WHERE department_id=?", [$event]);
        $out['tours'] = (string) $conn->fetchOne("SELECT string_agg(id || ':' || status, ',' ORDER BY id) FROM department_grossanlass_tour WHERE department_id=?", [$event]);

        return $out;
    }

    public function testBuildPhaseHasRealTasksAndTripsAndAJumpBooksNothing(): void
    {
        $this->withDb(function ($c, EntityManagerInterface $em, Connection $conn): void {
            $runner = $c->get(DemoScenarioRunner::class);
            $scenario = $c->get(DemoScenarioRegistry::class)->all()[DemoScenarioKey::GROSSANLASS_EVENT];
            $result = $runner->sync($scenario);
            self::assertSame(SeedResult::OK, $result->status);
            self::assertSame([], $runner->verify($scenario));
            $em->clear();
            $event = (string) $conn->fetchOne("SELECT id FROM department WHERE demo_scenario_key='grossanlass-event'");
            $department = $em->find(Department::class, $event);
            $mw = $this->user($em, 'ga-mw');

            // Zeitsprung auf das Preset «Aufbau»: reiner Offset, kein Seed-Lauf, keine Buchung
            $before = $this->ledger($conn, $event);
            $clock = $c->get(BusinessClock::class);
            $clock->travelTo($department, new \DateTimeImmutable(self::BUILD, new \DateTimeZone('UTC')));
            $em->flush();
            self::assertEqualsWithDelta((new \DateTimeImmutable(self::BUILD, new \DateTimeZone('UTC')))->getTimestamp(), $clock->now($department)->getTimestamp(), 3);

            // Freigegebene Aufgaben (/ga/tasks/all): vier Bauaufträge, Verantwortliche, Checklisten; nicht freigegebene bleiben unsichtbar
            $bauprojekt = $c->get(GrossanlassBauprojektService::class);
            $tasks = $bauprojekt->listAllTasks($department, $mw);
            $released = array_values(array_filter($tasks, static fn (array $t) => $t['released'] === true));
            self::assertGreaterThanOrEqual(27, count($released));
            $titles = array_column($released, 'title');
            foreach (['Beschilderung montieren', 'Bodenrahmen legen', 'Wandelemente stellen', 'Tresenrahmen bauen', 'Arbeitsplatte montieren', 'Podest montieren'] as $title) {
                self::assertContains($title, $titles);
            }
            self::assertGreaterThanOrEqual(15, count(array_filter($released, static fn (array $t) => $t['assignee_user_id'] !== null)), 'zugeteilte Verantwortliche');
            self::assertGreaterThanOrEqual(13, count(array_filter($released, static fn (array $t) => $t['steps'] !== [])), 'Aufgaben mit Checkliste');
            self::assertSame([], array_filter($released, static fn (array $t) => $t['status'] !== 'open'), 'der Seed setzt keinen Fortschritt');

            // Fahraufträge: vier mit unterschiedlichem Stand, dazu offene Fahrzeugwünsche
            $dispatch = $c->get(GrossanlassDispatchService::class);
            $overview = $dispatch->overview($department, $mw);
            self::assertSame(substr(self::BUILD, 0, 16), substr(str_replace('T', ' ', (string) $overview['now']), 0, 16), 'Disposition rechnet mit der Fachzeit');
            self::assertGreaterThanOrEqual(4, count(array_filter($overview['needs'], static fn (array $n) => $n['einsatz_id'] !== null)));

            self::assertSame($before, $this->ledger($conn, $event), 'ein Zeitsprung und das Lesen buchen nichts und setzen keinen Status zurück');

            // Display: nur das Demo-Department liefert seine Fachzeit; der Wert ist die Uhr des GA-Control
            $payload = $c->get(DisplayClockPayload::class)->for($department);
            self::assertSame('demo', $payload['mode']);
            self::assertSame(substr(self::BUILD, 0, 16), substr(str_replace('T', ' ', (string) $payload['now']), 0, 16));
            $live = $c->get(\App\Service\Display\DisplayLiveDataService::class)->forScreen((new DepartmentDisplayScreen())->setDepartmentId($event));
            self::assertSame('ready', $live['state']);
            self::assertSame($payload['mode'], $live['clock']['mode']);
            self::assertContains('tour_confirmed', array_column($live['feed'], 'kind'), 'Tourbestätigungen erscheinen im Feed');
            self::assertContains('dispatch_released', array_column($live['feed'], 'kind'));
            self::assertSame(substr(self::BUILD, 0, 16), substr(str_replace('T', ' ', (string) $live['generated_at']), 0, 16));
        });
    }

    /** Preset-Zeitpunkte aus dem Seed (`demo_clock_presets`): jeder zeigt passende Aufgaben und Fahrten, der Wechsel bucht nichts. */
    public function testEverySixPresetShowsMatchingTasksAndTripsAndJumpsBookNothing(): void
    {
        $this->withDb(function ($c, EntityManagerInterface $em, Connection $conn): void {
            $runner = $c->get(DemoScenarioRunner::class);
            $scenario = $c->get(DemoScenarioRegistry::class)->all()[DemoScenarioKey::GROSSANLASS_EVENT];
            $runner->sync($scenario);
            $em->clear();
            $event = (string) $conn->fetchOne("SELECT id FROM department WHERE demo_scenario_key='grossanlass-event'");
            $department = $em->find(Department::class, $event);
            $mw = $this->user($em, 'ga-mw');
            $clock = $c->get(BusinessClock::class);
            $presets = json_decode((string) $conn->fetchOne("SELECT setting_value FROM department_setting WHERE department_id=? AND setting_key='demo_clock_presets'", [$event]), true);
            self::assertCount(6, $presets);
            $baseline = $this->ledger($conn, $event);

            $tasks = $c->get(GrossanlassBauprojektService::class)->listAllTasks($department, $mw);
            $dispatch = $c->get(GrossanlassDispatchService::class);
            $byDay = static function (array $rows, string $day): array {
                return array_values(array_filter($rows, static fn (array $r) => str_starts_with((string) ($r['starts_at'] ?? ''), $day)));
            };
            foreach ($presets as $preset) {
                $clock->travelTo($department, new \DateTimeImmutable($preset['at'], new \DateTimeZone('UTC')));
                $em->flush();
                $overview = $dispatch->overview($department, $mw);
                self::assertSame(substr($preset['at'], 0, 16), substr((string) $overview['now'], 0, 16), $preset['key']);
                self::assertSame($baseline, $this->ledger($conn, $event), 'Sprung auf ' . $preset['key'] . ' bucht nichts und setzt keinen Status zurück');
            }

            $day = static fn (string $key): string => substr((string) array_values(array_filter($presets, static fn (array $p) => $p['key'] === $key))[0]['at'], 0, 10);
            $trips = static fn (array $needs): array => array_values(array_filter($needs, static fn (array $n) => $n['einsatz_id'] !== null));
            $needs = $dispatch->overview($department, $mw)['needs'];

            // Während Event: Betriebsaufgaben mit Verantwortlichen und Checklisten, Nachschubfahrt freigegeben
            $running = $byDay($tasks, $day('during_event'));
            self::assertGreaterThanOrEqual(5, count($running));
            self::assertNotEmpty(array_filter($running, static fn (array $t) => $t['assignee_user_id'] !== null && $t['steps'] !== []));
            $supply = array_values(array_filter($trips($needs), static fn (array $n) => str_starts_with((string) $n['starts_at'], '2027-06-05T18:30')));
            self::assertCount(1, $supply);
            self::assertSame([true, 'Bar-Tresen West'], [$supply[0]['released'], $supply[0]['to']]);
            self::assertNotNull($supply[0]['tour_id']);

            // Abbau: Rückbauaufgaben, Rücktransport offen (Fahrauftrag ohne Tour) und Teleskoplader-Rückgabe disponiert, nicht freigegeben
            $teardown = $byDay($tasks, $day('teardown'));
            self::assertGreaterThanOrEqual(5, count($teardown));
            $return = array_values(array_filter($trips($needs), static fn (array $n) => str_starts_with((string) $n['starts_at'], '2027-06-08T11')));
            self::assertCount(1, $return);
            self::assertSame(['open', false, null], [$return[0]['status'], $return[0]['released'], $return[0]['tour_id']]);
            self::assertNotNull($return[0]['vehicle_id']);
            $lader = array_values(array_filter($trips($needs), static fn (array $n) => str_starts_with((string) $n['starts_at'], '2027-06-09T08')));
            self::assertCount(1, $lader);
            self::assertSame([false, true], [$lader[0]['released'], $lader[0]['tour_id'] !== null]);

            // Abschluss: Restarbeiten sind offen und überfällig, Materialklärung liegt in der Werkstatt
            $rest = array_values(array_filter($tasks, static fn (array $t) => $t['status'] === 'open' && substr((string) $t['starts_at'], 0, 10) >= '2027-06-09'));
            self::assertGreaterThanOrEqual(3, count($rest));
            self::assertSame(2, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_workshop_case WHERE department_id=?', [$event]));
        });
    }

    public function testTaskStepsFollowTheRulesAndCarryBusinessTimestamps(): void
    {
        $this->withDb(function ($c, EntityManagerInterface $em, Connection $conn): void {
            $runner = $c->get(DemoScenarioRunner::class);
            $runner->sync($c->get(DemoScenarioRegistry::class)->all()[DemoScenarioKey::GROSSANLASS_EVENT]);
            $em->clear();
            $event = (string) $conn->fetchOne("SELECT id FROM department WHERE demo_scenario_key='grossanlass-event'");
            $department = $em->find(Department::class, $event);
            $c->get(BusinessClock::class)->travelTo($department, new \DateTimeImmutable(self::BUILD, new \DateTimeZone('UTC')));
            $em->flush();
            $mw = $this->user($em, 'ga-mw');
            $helper = $this->user($em, 'ga-user');
            $bauprojekt = $c->get(GrossanlassBauprojektService::class);

            $task = null;
            foreach ($bauprojekt->listAllTasks($department, $mw) as $row) {
                if ($row['title'] === 'Wandelemente stellen') {
                    $task = $row;
                }
            }
            self::assertNotNull($task);
            self::assertTrue($task['assigned_to_me'], 'ga-mw ist Verantwortlicher («Meine Einsätze»)');
            $group = $em->find(\App\Entity\Group::class, $task['group_id']);

            // Zulässige Schritte: starten, Checkliste abhaken, fertig melden, abnehmen
            $started = $bauprojekt->updateTask($department, $mw, $group, $task['id'], ['status' => 'progress']);
            self::assertSame('progress', $started['status']);
            $step = $task['steps'][0]['id'];
            $ticked = $bauprojekt->updateTaskStep($department, $mw, $group, $task['id'], $step, ['done' => true]);
            self::assertTrue($ticked['steps'][0]['done']);
            // Fertig melden und Abnahme: ein Status «erledigt» entsteht nie durch direktes Setzen
            try {
                $bauprojekt->updateTask($department, $mw, $group, $task['id'], ['status' => 'done']);
                self::fail('done darf nur über Abnahme entstehen');
            } catch (\InvalidArgumentException) {
            }
            $reported = $bauprojekt->reportTaskDone($department, $mw, $group, $task['id'], 'Wände stehen');
            self::assertSame('review', $reported['status']);
            $accepted = $bauprojekt->acceptTask($department, $mw, $group, $task['id']);
            self::assertSame('done', $accepted['status']);

            // Fachliche Zeitstempel: Verlauf und Abhaken tragen die Fachzeit (31.05.2027, 14 Uhr), nicht die reale Zeit
            $events = $conn->fetchFirstColumn('SELECT created_at FROM department_grossanlass_task_event WHERE task_id=? ORDER BY created_at', [$task['id']]);
            self::assertNotEmpty($events);
            foreach ($events as $at) {
                self::assertStringStartsWith('2027-05-31 14:', (string) $at);
            }
            self::assertStringStartsWith('2027-05-31 14:', (string) $conn->fetchOne('SELECT done_at FROM department_grossanlass_task_step WHERE id=?', [$step]));
            // Technische Zeit bleibt real
            self::assertSame(date('Y-m'), substr((string) $conn->fetchOne('SELECT created_at FROM department_grossanlass_task WHERE id=?', [$task['id']]), 0, 7));

            // «Meine Einsätze» folgt der Zuteilung: die Aufgabe von ga-mw gehört nicht dem Helfer
            foreach ($bauprojekt->listAllTasks($department, $helper) as $row) {
                if ($row['title'] === 'Tresenrahmen bauen') {
                    self::assertFalse($row['assigned_to_me']);
                }
            }
        });
    }

    public function testTripsMoveOnThroughTheExistingWorkflowOnly(): void
    {
        $this->withDb(function ($c, EntityManagerInterface $em, Connection $conn): void {
            $runner = $c->get(DemoScenarioRunner::class);
            $runner->sync($c->get(DemoScenarioRegistry::class)->all()[DemoScenarioKey::GROSSANLASS_EVENT]);
            $em->clear();
            $event = (string) $conn->fetchOne("SELECT id FROM department WHERE demo_scenario_key='grossanlass-event'");
            $department = $em->find(Department::class, $event);
            $c->get(BusinessClock::class)->travelTo($department, new \DateTimeImmutable(self::BUILD, new \DateTimeZone('UTC')));
            $em->flush();
            $mw = $this->user($em, 'ga-mw');
            $dispatch = $c->get(GrossanlassDispatchService::class);

            $trips = [];
            foreach ($dispatch->overview($department, $mw)['needs'] as $n) {
                if ($n['einsatz_id'] !== null) {
                    $trips[$n['starts_at']] = $n;
                }
            }
            [$holz, $verteilung, $rampe] = [$trips['2027-05-19T12:00:00'], $trips['2027-05-31T16:00:00'], $trips['2027-06-01T08:00:00']];

            // Voraussetzungen werden nicht umgangen: ohne Pack keine Freigabe
            try {
                $dispatch->releaseNeed($department, $mw, $holz['id']);
                self::fail('Freigabe ohne Pack muss scheitern');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Pack', $e->getMessage());
            }
            self::assertFalse($dispatch->overview($department, $mw)['needs'][array_search($holz['id'], array_column($dispatch->overview($department, $mw)['needs'], 'id'), true)]['released']);

            // Der überführte Fahrauftrag «Rampenhölzer» lässt sich disponieren: Tour mit Chauffeur und Fahrzeug, dann bestätigen
            $lw = $this->user($em, 'ga-lw');
            $tour = $dispatch->createTour($department, $mw, ['name' => 'Rampenhölzer', 'need_ids' => [$rampe['id']], 'vehicle_id' => $rampe['vehicle_id'], 'chauffeur_user_id' => $lw->getId()]);
            self::assertSame('draft', $tour['status']);
            $confirmed = $dispatch->confirmTour($department, $mw, (string) $tour['id']);
            self::assertSame('confirmed', $confirmed['status']);
            try {
                $dispatch->releaseNeed($department, $mw, $rampe['id']);
                self::fail('Freigabe vor dem Packen muss scheitern');
            } catch (\InvalidArgumentException) {
            }

            // Freigabe zurücknehmen und erneut erteilen (bestehende Aktionen): Protokoll mit Fachzeit
            $dispatch->revokeNeedRelease($department, $mw, $verteilung['id']);
            $again = $dispatch->releaseNeed($department, $mw, $verteilung['id']);
            self::assertTrue($again['released']);
            $times = $conn->fetchFirstColumn("SELECT created_at FROM department_grossanlass_dispatch_event WHERE department_id=? AND subject_id=? AND action IN ('release_revoked', 'released') AND created_at > '2027-01-01' ORDER BY created_at", [$event, $verteilung['id']]);
            self::assertGreaterThanOrEqual(2, count($times), 'Rücknahme und Freigabe tragen die Fachzeit (2027), nicht die reale Zeit');
            foreach ($times as $at) {
                self::assertStringStartsWith('2027-05-31 14:', (string) $at);
            }
            $tourTimes = $conn->fetchFirstColumn('SELECT created_at FROM department_grossanlass_tour_event WHERE tour_id=?', [$tour['id']]);
            self::assertNotEmpty($tourTimes);
            foreach ($tourTimes as $at) {
                self::assertStringStartsWith('2027-05-31 14:', (string) $at);
            }
        });
    }
}
