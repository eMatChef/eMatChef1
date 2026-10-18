<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassDispatchService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Transporttouren der Logistik-Disposition gegen die echte Datenbank: Zusammenstellen, Stopps, Bestätigung (ohne
 * Ausführungsfreigabe), Berechtigungen und Konflikte. Läuft nur mit EMATCHEF_TEST_DB_URL; jeder Test endet mit Rollback.
 */
final class GrossanlassTourIntegrationTest extends TestCase
{
    private const D = 'deptsa000001';
    private const MW = 'ushsa00000mw';
    private const DC = 'ushsa00000dc';
    private const HELPER = 'ushsa000000u';
    private const DRIVER = 'ushsa00000hv';

    /** @param callable(GrossanlassDispatchService, EntityManagerInterface, Department, Connection): void $test */
    private function withService(callable $test): void
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
                $conn->executeStatement("INSERT INTO department_grossanlass_user_card (department_id, user_id, public_code, may_drive, drive_classes) VALUES (?, ?, 'codehv', true, '[\"b\"]')", [self::D, self::DRIVER]);
                $conn->executeStatement("INSERT INTO department_grossanlass_commitment (id, department_id, name, family, source, services, created_at, updated_at) VALUES ('cmsa00000001', ?, 'Transporter', 'vehicle', 'own', '[]', now(), now()), ('cmsa00000002', ?, 'Kipper', 'vehicle', 'own', '[]', now(), now())", [self::D, self::D]);
                $em->clear();
                $test($c->get(GrossanlassDispatchService::class), $em, $em->find(Department::class, self::D), $conn);
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassDispatchService::class]);
    }

    private static function trip(Connection $conn, string $id, string $from, string $to, string $status = 'planned', ?string $chauffeur = null, string $who = 'Fahrt'): void
    {
        $conn->executeStatement(
            "INSERT INTO department_grossanlass_einsatz (id, department_id, kind, qty, starts_at, ends_at, status, place, who, delivery, chauffeur_user_id, created_at, updated_at)
             VALUES (?, ?, 'einsatz', 1, ?, ?, ?, 'lager', ?, 'trip', ?, now(), now())",
            [$id, self::D, $from, $to, $status, $who, $chauffeur],
        );
    }

    private static function user(EntityManagerInterface $em, string $id): User
    {
        return $em->find(User::class, $id);
    }

    /** @return array<string, array<string, mixed>> */
    private static function needs(GrossanlassDispatchService $service, Department $d, User $u): array
    {
        $out = [];
        foreach ($service->overview($d, $u)['needs'] as $need) {
            $out[$need['id']] = $need;
        }

        return $out;
    }

    public function testTourBundlesTripsAndVehicleWishesWithoutCopyingOrReopening(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00', who: 'Zelt');
            self::trip($conn, 'etsa00000002', '2027-06-04 10:00', '2027-06-04 11:00', who: 'Bühne');
            $conn->executeStatement("INSERT INTO department_grossanlass_vehicle_need (id, department_id, group_id, vehicle_label, task_label, starts_at, duration_minutes, created_at) VALUES ('vnsa00000001', ?, 'grpsa0000001', 'Stapler', 'Laden', '2027-06-04 12:00', 60, now())", [self::D]);
            $mw = self::user($em, self::MW);

            $tour = $service->createTour($d, $mw, ['need_ids' => ['etsa00000001', 'etsa00000002', 'vn:vnsa00000001']]);
            self::assertSame('draft', $tour['status']);
            self::assertSame(['etsa00000001', 'etsa00000002', 'vn:vnsa00000001'], $tour['need_ids']);
            self::assertCount(6, $tour['stops'], 'je Bedarf Aufladen und Abladen');
            $times = array_map(static fn (array $s) => $s['planned_at'], $tour['stops']);
            $sorted = $times;
            sort($sorted);
            self::assertSame($sorted, $times, 'Stopps nach geplanter Zeit vorgeordnet');
            self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz WHERE department_id = ? AND trip_released_at IS NOT NULL', [self::D]));
            self::assertSame(2, (int) $conn->fetchOne("SELECT count(*) FROM department_grossanlass_einsatz WHERE department_id = ? AND delivery = 'trip'", [self::D]), 'keine Fahraufträge dupliziert');

            $needs = self::needs($service, $d, $mw);
            foreach (array_keys($needs) as $id) {
                self::assertSame($tour['id'], $needs[$id]['tour_id']);
                self::assertSame('planned', $needs[$id]['status'], 'zugeordnete Bedarfe sind nicht mehr offen');
            }
            try {
                $service->createTour($d, $mw, ['need_ids' => ['etsa00000001']]);
                self::fail('Bedarf darf nur in einer Tour liegen');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('bereits zu einer Tour', $e->getMessage());
            }

            // Entfernen gibt den Bedarf frei, die letzte Entfernung löscht die Tour
            $service->removeTourNeed($d, $mw, $tour['id'], 'vn:vnsa00000001');
            self::assertSame('open', self::needs($service, $d, $mw)['vn:vnsa00000001']['status']);
            $service->removeTourNeed($d, $mw, $tour['id'], 'etsa00000001');
            $last = $service->removeTourNeed($d, $mw, $tour['id'], 'etsa00000002');
            self::assertSame(['deleted' => true], $last);
            self::assertSame([], $service->overview($d, $mw)['tours']);
        });
    }

    public function testOnlyLogisticsManagersMayWriteAndHelpersSeeNothing(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            $dc = self::user($em, self::DC);
            self::assertFalse($service->overview($d, $dc)['can_manage'], 'Sehen heisst nicht Disponieren');
            self::assertTrue($service->overview($d, self::user($em, self::MW))['can_manage']);
            foreach ([fn () => $service->createTour($d, $dc, ['need_ids' => ['etsa00000001']]), fn () => $service->overview($d, self::user($em, self::HELPER))] as $call) {
                try {
                    $call();
                    self::fail('Zugriff hätte abgelehnt werden müssen');
                } catch (\RuntimeException) {
                    self::assertTrue(true);
                }
            }
            self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_tour'));
        });
    }

    public function testConfirmationNeedsResourcesIsNotAReleaseAndEditingReopensIt(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            $mw = self::user($em, self::MW);
            $tour = $service->createTour($d, $mw, ['need_ids' => ['etsa00000001']]);

            try {
                $service->confirmTour($d, $mw, $tour['id']);
                self::fail('ohne Fahrzeug und Chauffeur nicht bestätigbar');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Fahrzeug fehlt', $e->getMessage());
                self::assertStringContainsString('Chauffeur fehlt', $e->getMessage());
            }
            try {
                $service->updateTour($d, $mw, $tour['id'], ['chauffeur_user_id' => self::HELPER]);
                self::fail('Chauffeur ohne Fahrkarte');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('ohne bestätigte Karte', $e->getMessage());
            }

            $service->updateTour($d, $mw, $tour['id'], ['vehicle_id' => 'cmsa00000001', 'chauffeur_user_id' => self::DRIVER, 'trailer' => 'Anhänger 2']);
            $confirmed = $service->confirmTour($d, $mw, $tour['id']);
            self::assertSame('confirmed', $confirmed['status']);
            self::assertTrue($confirmed['confirmed']);
            $need = self::needs($service, $d, $mw)['etsa00000001'];
            self::assertFalse($need['released'], 'Tour bestätigt ist nicht zur Ausführung freigegeben');
            self::assertNull($conn->fetchOne('SELECT trip_released_at FROM department_grossanlass_einsatz WHERE id = ?', ['etsa00000001']));
            self::assertNull($conn->fetchOne('SELECT chauffeur_user_id FROM department_grossanlass_einsatz WHERE id = ?', ['etsa00000001']), 'Einsatz bleibt unverändert');

            // Änderung an bestätigter Tour verlangt erneute Bestätigung
            $reopened = $service->updateTour($d, $mw, $tour['id'], ['trailer' => 'Anhänger 4']);
            self::assertSame('draft', $reopened['status']);
            $actions = array_column($reopened['events'], 'action');
            self::assertContains('confirmed', $actions);
            self::assertContains('unconfirmed', $actions);
            self::assertSame('confirmed', $service->confirmTour($d, $mw, $tour['id'])['status']);
        });
    }

    public function testVehicleAndChauffeurConflictsAreDetectedAndBlockConfirmation(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 10:00');
            self::trip($conn, 'etsa00000002', '2027-06-04 09:00', '2027-06-04 11:00');
            self::trip($conn, 'etsa00000003', '2027-06-04 13:00', '2027-06-04 14:00');
            self::trip($conn, 'etsa00000004', '2027-06-04 09:30', '2027-06-04 10:30', chauffeur: self::DRIVER, who: 'Fremdfahrt');
            $mw = self::user($em, self::MW);
            $resources = ['vehicle_id' => 'cmsa00000001', 'chauffeur_user_id' => self::DRIVER];

            $a = $service->createTour($d, $mw, ['need_ids' => ['etsa00000001']] + $resources);
            $b = $service->createTour($d, $mw, ['need_ids' => ['etsa00000002']] + $resources);
            $kinds = array_column($b['conflicts'], 'kind');
            self::assertContains('vehicle', $kinds);
            self::assertContains('chauffeur', $kinds);
            self::assertContains($a['name'], array_column($b['conflicts'], 'with'));
            try {
                $service->confirmTour($d, $mw, $b['id']);
                self::fail('Konflikt blockiert die Bestätigung');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Konflikt', $e->getMessage());
            }

            // Überlappende Wünsche sind zulässig, solange Fahrzeug und Chauffeur verschieden sind
            $c = $service->updateTour($d, $mw, $b['id'], ['vehicle_id' => 'cmsa00000002']);
            self::assertNotContains('vehicle', array_column($c['conflicts'], 'kind'));
            self::assertContains('chauffeur', array_column($c['conflicts'], 'kind'));

            // Nicht überlappende Zeit: kein Konflikt, trotz gleicher Ressourcen
            $d3 = $service->createTour($d, $mw, ['need_ids' => ['etsa00000003']] + $resources);
            self::assertSame([], array_values(array_filter($d3['conflicts'], static fn (array $x) => $x['with'] !== 'Fremdfahrt' && in_array($x['with'], [$a['name'], $b['name']], true))));

            // Chauffeur bereits auf einem Fahrauftrag ausserhalb der Tour gebunden
            $service->updateTour($d, $mw, $b['id'], ['chauffeur_user_id' => null]);
            $again = $service->updateTour($d, $mw, $a['id'], ['chauffeur_user_id' => self::DRIVER]);
            self::assertContains('Fremdfahrt', array_column($again['conflicts'], 'with'));
        });
    }

    public function testStopOrderIsValidatedAndRunningTripsLockTheTour(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            self::trip($conn, 'etsa00000002', '2027-06-04 08:30', '2027-06-04 09:30', status: 'issued');
            $mw = self::user($em, self::MW);
            $tour = $service->createTour($d, $mw, ['need_ids' => ['etsa00000001']]);
            $stops = array_map(static fn (array $s) => ['kind' => $s['kind'], 'place_id' => $s['place_id'], 'label' => $s['label'], 'planned_at' => $s['planned_at'], 'need_id' => $s['need_id']], $tour['stops']);

            try {
                $service->replaceTourStops($d, $mw, $tour['id'], array_reverse($stops));
                self::fail('Abladen vor Aufladen ist ungültig');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Aufladen muss vor dem Abladen', $e->getMessage());
            }
            $stops[0]['planned_at'] = '2027-06-04T07:15:00';
            $stops[] = ['kind' => 'unload', 'place_id' => null, 'label' => 'Zusatz', 'planned_at' => '2027-06-04T09:45:00', 'need_id' => 'etsa00000001'];
            $saved = $service->replaceTourStops($d, $mw, $tour['id'], $stops);
            self::assertCount(3, $saved['stops']);
            self::assertSame('2027-06-04T07:15:00', $saved['stops'][0]['planned_at']);
            self::assertSame('2027-06-04T07:15:00', $saved['starts_at'], 'Zeitfenster der Tour folgt den Stopps');

            try {
                $service->createTour($d, $mw, ['need_ids' => ['etsa00000002']]);
                self::fail('unterwegs: nicht zuordenbar');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('unterwegs oder erledigt', $e->getMessage());
            }
        });
    }

    public function testRunningConfirmedTourCannotBeChanged(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            $mw = self::user($em, self::MW);
            $tour = $service->createTour($d, $mw, ['need_ids' => ['etsa00000001'], 'vehicle_id' => 'cmsa00000001', 'chauffeur_user_id' => self::DRIVER]);
            $service->confirmTour($d, $mw, $tour['id']);
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET status = 'issued' WHERE id = 'etsa00000001'");
            $em->clear();
            $d = $em->find(Department::class, self::D);
            $mw = self::user($em, self::MW);
            self::assertSame('underway', $service->overview($d, $mw)['tours'][0]['status']);
            $this->expectException(\InvalidArgumentException::class);
            $service->updateTour($d, $mw, $tour['id'], ['trailer' => 'x']);
        });
    }

    public function testSuggestionsGroupOpenNeedsByTargetAndWindowButNeverConfirm(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $d, Connection $conn): void {
            $conn->executeStatement("INSERT INTO department_grossanlass_place (id, department_id, name, public_code, created_at) VALUES ('plsa00000001', ?, 'Süd', 'c1', now())", [self::D]);
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            self::trip($conn, 'etsa00000002', '2027-06-04 15:00', '2027-06-04 16:00');
            self::trip($conn, 'etsa00000003', '2027-06-04 16:30', '2027-06-04 17:00');
            self::trip($conn, 'etsa00000004', '2027-06-05 08:00', '2027-06-05 09:00');
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET destination_place_id = 'plsa00000001' WHERE id IN ('etsa00000001', 'etsa00000002')");
            $mw = self::user($em, self::MW);

            $suggestions = $service->overview($d, $mw)['suggestions'];
            $byReason = [];
            foreach ($suggestions as $s) {
                $byReason[$s['reason']][] = $s['need_ids'];
            }
            self::assertSame([['etsa00000001', 'etsa00000002']], $byReason['sameTarget']);
            self::assertArrayNotHasKey('sameWindow', $byReason, 'ein einzelner Bedarf je Tag ergibt keinen Vorschlag');
            self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_tour'), 'Vorschläge legen nichts an');

            // Ist ein Bedarf in einer Tour, taucht er in keinem Vorschlag mehr auf
            $service->createTour($d, $mw, ['need_ids' => ['etsa00000001']]);
            self::assertSame([], array_values(array_filter($service->overview($d, $mw)['suggestions'], static fn (array $s) => in_array('etsa00000001', $s['need_ids'], true))));
        });
    }
}
