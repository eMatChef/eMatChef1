<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassDispatchService;
use App\Service\Grossanlass\GrossanlassPackService;
use App\Service\Grossanlass\GrossanlassUebersichtService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Freigabe, Ablieferung, Tourabschluss mit Restfahrten und Änderungswünsche der Logistik-Disposition gegen die echte
 * Datenbank. Läuft nur mit EMATCHEF_TEST_DB_URL; jeder Test endet mit Rollback.
 */
final class GrossanlassDispatchExecutionIntegrationTest extends TestCase
{
    private const D = 'deptsa000001';
    private const MW = 'ushsa00000mw';
    private const DC = 'ushsa00000dc';
    private const HELPER = 'ushsa000000u';
    private const DRIVER = 'ushsa00000hv';
    private const LEADER = 'ushsa0000sub';

    /** @param callable(GrossanlassDispatchService, GrossanlassPackService, EntityManagerInterface, Department, Connection): void $test */
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
                $conn->executeStatement("INSERT INTO department_grossanlass_user_card (department_id, user_id, public_code, may_drive, drive_classes) VALUES (?, ?, 'codehv', true, '[\"b\"]')", [self::D, self::DRIVER]);
                $conn->executeStatement("INSERT INTO department_grossanlass_commitment (id, department_id, name, family, source, services, created_at, updated_at) VALUES ('cmsa00000001', ?, 'Transporter', 'vehicle', 'own', '[]', now(), now())", [self::D]);
                $conn->executeStatement("INSERT INTO department_grossanlass_place (id, department_id, name, public_code, created_at) VALUES ('plsa00000001', ?, 'Festgelände Süd', 'plsacode1', now()), ('plsa00000002', ?, 'Festgelände Nord', 'plsacode2', now())", [self::D, self::D]);
                $conn->executeStatement("INSERT INTO group_membership (user_id, group_id, role, is_primary, created_at, can_procure) VALUES (?, 'grpsa0000001', 'leader', false, now(), false)", [self::LEADER]);
                $em->clear();
                $test($c->get(GrossanlassDispatchService::class), $c->get(GrossanlassPackService::class), $em, $em->find(Department::class, self::D), $conn, $c->get(GrossanlassUebersichtService::class));
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassDispatchService::class, GrossanlassPackService::class, GrossanlassUebersichtService::class]);
    }

    /** Fahrauftrag mit Pack (Teilpack reicht) und Ziel; `$packStatus` steuert den Stand im QR-Ablauf. */
    private static function trip(Connection $conn, string $id, string $from, string $to, string $packStatus = 'staging', bool $packed = true, string $einsatzStatus = 'planned'): void
    {
        $conn->executeStatement(
            "INSERT INTO department_grossanlass_einsatz (id, department_id, group_id, kind, qty, starts_at, ends_at, status, place, who, delivery, packed, destination_place_id, created_at, updated_at)
             VALUES (?, ?, 'grpsa0000001', 'einsatz', 1, ?, ?, ?, 'lager', ?, 'trip', ?, 'plsa00000001', now(), now())",
            [$id, self::D, $from, $to, $einsatzStatus, 'Fahrt ' . $id, $packed ? 'true' : 'false'],
        );
        $suffix = substr($id, -6);
        $conn->executeStatement(
            "INSERT INTO department_grossanlass_pack (id, department_id, einsatz_id, public_code, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, now(), now())",
            ['pk' . $suffix . 'aaaa', self::D, $id, 'code' . $suffix, $packStatus],
        );
        $conn->executeStatement("INSERT INTO department_grossanlass_pack_line (id, pack_id, label, qty_needed, qty_packed) VALUES (?, ?, 'Material', 1, ?)", ['pl' . $suffix . 'aaaa', 'pk' . $suffix . 'aaaa', $packed ? 1 : 0]);
        if (in_array($packStatus, ['trip_released', 'in_transit', 'at_place'], true)) {
            $conn->executeStatement('UPDATE department_grossanlass_pack SET trip_released_at = now() WHERE einsatz_id = ?', [$id]);
            $conn->executeStatement('UPDATE department_grossanlass_einsatz SET trip_released_at = now() WHERE id = ?', [$id]);
        }
    }

    private static function user(EntityManagerInterface $em, string $id): User
    {
        return $em->find(User::class, $id);
    }

    /** @return array<string, mixed> */
    private static function need(GrossanlassDispatchService $service, Department $d, User $u, string $id): array
    {
        foreach ($service->overview($d, $u)['needs'] as $need) {
            if ($need['id'] === $id) {
                return $need;
            }
        }
        self::fail('Bedarf fehlt: ' . $id);
    }

    private function confirmedTour(GrossanlassDispatchService $service, User $mw, Department $d, array $needIds): array
    {
        $tour = $service->createTour($d, $mw, ['need_ids' => $needIds, 'vehicle_id' => 'cmsa00000001', 'chauffeur_user_id' => self::DRIVER]);

        return $service->confirmTour($d, $mw, $tour['id']);
    }

    public function testReleaseIsSeparateFromConfirmationAndUsesExistingRules(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            self::trip($conn, 'etsa00000002', '2027-06-04 10:00', '2027-06-04 11:00', packed: false);
            $mw = self::user($em, self::MW);
            $draft = $service->createTour($d, $mw, ['need_ids' => ['etsa00000001'], 'vehicle_id' => 'cmsa00000001', 'chauffeur_user_id' => self::DRIVER]);

            try {
                $service->releaseNeed($d, $mw, 'etsa00000001');
                self::fail('Tourentwurf: Fahrauftrag nicht freigebbar');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Tour zuerst bestätigen', $e->getMessage());
            }
            self::assertFalse(self::need($service, $d, $mw, 'etsa00000001')['released']);

            $service->confirmTour($d, $mw, $draft['id']);
            self::assertFalse(self::need($service, $d, $mw, 'etsa00000001')['released'], 'Tour bestätigt ist nicht freigegeben');

            try {
                $service->releaseNeed($d, self::user($em, self::DC), 'etsa00000001');
                self::fail('Sehen heisst nicht Freigeben');
            } catch (\RuntimeException) {
                self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz WHERE trip_released_at IS NOT NULL'));
            }

            $released = $service->releaseNeed($d, $mw, 'etsa00000001');
            self::assertTrue($released['released']);
            self::assertSame(self::DRIVER, $released['chauffeur_user_id'], 'Chauffeur der Tour wird in den Fahrauftrag übernommen');
            self::assertSame('trip_released', $released['pack_status']);
            self::assertSame('trip_released', $conn->fetchOne("SELECT status FROM department_grossanlass_pack WHERE einsatz_id = 'etsa00000001'"));
            self::assertNotNull($conn->fetchOne("SELECT trip_released_at FROM department_grossanlass_einsatz WHERE id = 'etsa00000001'"));

            // Bestehende Regel: nur gepackte Fahrten
            $second = $service->createTour($d, $mw, ['need_ids' => ['etsa00000002'], 'vehicle_id' => 'cmsa00000001', 'chauffeur_user_id' => self::DRIVER]);
            $service->confirmTour($d, $mw, $second['id']);
            try {
                $service->releaseTour($d, $mw, $second['id']);
                self::fail('ohne Pack nicht freigebbar');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('erst nach Pack', $e->getMessage());
            }

            // Rücknahme: Pack wieder bereitgestellt
            $revoked = $service->revokeNeedRelease($d, $mw, 'etsa00000001');
            self::assertFalse($revoked['released']);
            self::assertSame('staging', $revoked['pack_status']);
            $events = $conn->fetchFirstColumn("SELECT action FROM department_grossanlass_dispatch_event WHERE subject_id = 'etsa00000001' ORDER BY created_at, id");
            self::assertContains('released', $events);
            self::assertContains('release_revoked', $events);
        });
    }

    public function testManualArrivalNeedsReasonAndRightsAndIsLogged(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00', 'in_transit', einsatzStatus: 'issued');
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET chauffeur_user_id = ? WHERE id = 'etsa00000001'", [self::DRIVER]);
            self::trip($conn, 'etsa00000002', '2027-06-04 10:00', '2027-06-04 11:00', 'trip_released');
            $em->clear();
            $d = $em->find(Department::class, self::D);
            $packId = trim((string) $conn->fetchOne("SELECT id FROM department_grossanlass_pack WHERE einsatz_id = 'etsa00000001'"));

            foreach ([[self::DRIVER, ''], [self::DRIVER, 'kurz']] as [$who, $reason]) {
                try {
                    $packs->arriveManually($d, self::user($em, $who), $packId, $reason);
                    self::fail('Begründung erforderlich');
                } catch (\InvalidArgumentException $e) {
                    self::assertStringContainsString('Begründung', $e->getMessage());
                }
            }
            try {
                $packs->arriveManually($d, self::user($em, self::HELPER), $packId, 'Der QR-Code am Ziel ist abgerissen');
                self::fail('Fremde Mitglieder dürfen nicht ohne QR abliefern');
            } catch (\RuntimeException) {
                self::assertSame('in_transit', $conn->fetchOne('SELECT status FROM department_grossanlass_pack WHERE id = ?', [$packId]));
            }
            // Nicht gestartet: keine Ablieferung
            $notStarted = trim((string) $conn->fetchOne("SELECT id FROM department_grossanlass_pack WHERE einsatz_id = 'etsa00000002'"));
            try {
                $packs->arriveManually($d, self::user($em, self::MW), $notStarted, 'Der QR-Code am Ziel ist abgerissen');
                self::fail('Pack muss unterwegs sein');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('zuerst scannen', $e->getMessage());
            }

            $done = $packs->arriveManually($d, self::user($em, self::DRIVER), $packId, 'Der QR-Code am Ziel ist abgerissen');
            self::assertSame('at_place', $done['status']);
            self::assertSame('plsa00000001', trim((string) $done['current_place_id']));
            self::assertSame('done', self::need($service, $d, self::user($em, self::MW), 'etsa00000001')['status']);
            self::assertSame(
                'Der QR-Code am Ziel ist abgerissen',
                $conn->fetchOne("SELECT note FROM department_grossanlass_dispatch_event WHERE subject_id = 'etsa00000001' AND action = 'delivered_manually'"),
            );
        });
    }

    public function testClosingATourKeepsDeliveredTripsAndReopensNotExecutedOnes(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            self::trip($conn, 'etsa00000002', '2027-06-04 09:30', '2027-06-04 10:30');
            self::trip($conn, 'etsa00000003', '2027-06-04 11:00', '2027-06-04 12:00');
            $mw = self::user($em, self::MW);
            $tour = $this->confirmedTour($service, $mw, $d, ['etsa00000001', 'etsa00000002', 'etsa00000003']);
            $service->releaseTour($d, $mw, $tour['id']);
            self::assertTrue(self::need($service, $d, $mw, 'etsa00000002')['released']);

            // 1 ausgeliefert, 2 unterwegs, 3 nicht gestartet
            $conn->executeStatement("UPDATE department_grossanlass_pack SET status = 'at_place' WHERE einsatz_id = 'etsa00000001'");
            $conn->executeStatement("UPDATE department_grossanlass_pack SET status = 'in_transit' WHERE einsatz_id = 'etsa00000002'");
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET status = 'issued' WHERE id = 'etsa00000002'");
            $em->clear();
            $d = $em->find(Department::class, self::D);
            $mw = self::user($em, self::MW);

            try {
                $service->closeTour($d, $mw, $tour['id']);
                self::fail('Unterwegs: erst abliefern');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Erst abliefern', $e->getMessage());
            }
            $conn->executeStatement("UPDATE department_grossanlass_pack SET status = 'at_place' WHERE einsatz_id = 'etsa00000002'");
            $em->clear();
            $d = $em->find(Department::class, self::D);
            $mw = self::user($em, self::MW);
            $packCount = (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_pack');
            $einsatzCount = (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz');

            $closed = $service->closeTour($d, $mw, $tour['id'], 'Fahrzeug fiel aus');
            self::assertSame('closed', $closed['status']);
            self::assertEqualsCanonicalizing(
                [['need_id' => 'etsa00000001', 'outcome' => 'delivered'], ['need_id' => 'etsa00000002', 'outcome' => 'delivered'], ['need_id' => 'etsa00000003', 'outcome' => 'not_executed']],
                $closed['outcomes'],
            );
            self::assertSame($packCount, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_pack'), 'keine neuen Packs');
            self::assertSame($einsatzCount, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz'), 'keine neuen Fahraufträge');

            $rest = self::need($service, $d, $mw, 'etsa00000003');
            self::assertSame('open', $rest['status'], 'nicht ausgeführte Fahrt ist wieder offener Bedarf');
            self::assertFalse($rest['released']);
            self::assertNull($rest['tour_id']);
            self::assertNull($rest['chauffeur_user_id']);
            self::assertSame('staging', $rest['pack_status']);
            self::assertSame('done', self::need($service, $d, $mw, 'etsa00000001')['status'], 'ausgelieferte Fahrt bleibt dokumentiert');
            self::assertSame($tour['id'], self::need($service, $d, $mw, 'etsa00000001')['tour_id']);
            self::assertContains('not_executed', $conn->fetchFirstColumn("SELECT action FROM department_grossanlass_dispatch_event WHERE subject_id = 'etsa00000003'"));

            // neu disponierbar, abgeschlossene Tour bleibt unveränderlich
            $again = $service->createTour($d, $mw, ['need_ids' => ['etsa00000003']]);
            self::assertSame(['etsa00000003'], $again['need_ids']);
            try {
                $service->updateTour($d, $mw, $tour['id'], ['name' => 'x']);
                self::fail('abgeschlossen');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
            try {
                $service->closeTour($d, self::user($em, self::DC), $again['id']);
                self::fail('Berechtigung');
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
        });
    }

    public function testRessortChangeRequestKeepsThePlanUntilLogisticsDecides(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            $mw = self::user($em, self::MW);
            $leader = self::user($em, self::LEADER);
            $tour = $this->confirmedTour($service, $mw, $d, ['etsa00000001']);

            try {
                $service->requestChange($d, self::user($em, self::HELPER), 'etsa00000001', ['note' => 'Bitte früher']);
                self::fail('Helfer ohne Ressortbezug');
            } catch (\RuntimeException) {
                self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_change_request'));
            }
            try {
                $service->requestChange($d, $leader, 'etsa00000001', []);
                self::fail('leerer Wunsch');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }

            $request = $service->requestChange($d, $leader, 'etsa00000001', ['note' => 'Lieferung eine Stunde früher', 'starts_at' => '2027-06-04T07:00:00', 'ends_at' => '2027-06-04T08:00:00', 'destination_place_id' => 'plsa00000002']);
            self::assertSame('open', $request['status']);
            // bestehende Planung bleibt gültig
            self::assertSame('2027-06-04 08:00:00', $conn->fetchOne("SELECT starts_at FROM department_grossanlass_einsatz WHERE id = 'etsa00000001'"));
            self::assertSame('plsa00000001', trim((string) $conn->fetchOne("SELECT destination_place_id FROM department_grossanlass_einsatz WHERE id = 'etsa00000001'")));
            self::assertSame('confirmed', $service->overview($d, $mw)['tours'][0]['status']);
            self::assertCount(1, $service->changeRequests($d, $leader), 'Ressort sieht den eigenen Wunsch');
            self::assertCount(1, $service->overview($d, $mw)['change_requests']);

            try {
                $service->decideChangeRequest($d, $leader, $request['id'], ['decision' => 'accept']);
                self::fail('Entscheiden darf nur die Logistik');
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
            try {
                $service->decideChangeRequest($d, $mw, $request['id'], ['decision' => 'reject', 'note' => 'ja']);
                self::fail('Ablehnung braucht eine Begründung');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }

            // angepasst übernehmen: andere Zeit, Tour wird wieder Entwurf
            $decided = $service->decideChangeRequest($d, $mw, $request['id'], ['decision' => 'adjust', 'note' => 'Halbe Stunde früher', 'starts_at' => '2027-06-04T07:30:00', 'ends_at' => '2027-06-04T08:30:00']);
            self::assertSame('adjusted', $decided['status']);
            self::assertSame('2027-06-04 07:30:00', $conn->fetchOne("SELECT starts_at FROM department_grossanlass_einsatz WHERE id = 'etsa00000001'"));
            self::assertSame('draft', $service->overview($d, $mw)['tours'][0]['status']);
            try {
                $service->decideChangeRequest($d, $mw, $request['id'], ['decision' => 'accept']);
                self::fail('nur einmal entscheidbar');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }

            // begründet ablehnen: nichts ändert sich, Entscheid ist dokumentiert
            $second = $service->requestChange($d, $leader, 'etsa00000001', ['note' => 'Doch ganz früh', 'starts_at' => '2027-06-04T05:00:00', 'ends_at' => '2027-06-04T06:00:00']);
            $rejected = $service->decideChangeRequest($d, $mw, $second['id'], ['decision' => 'reject', 'note' => 'Ziel vorher nicht zugänglich']);
            self::assertSame('rejected', $rejected['status']);
            self::assertSame('Ziel vorher nicht zugänglich', $rejected['decision_note']);
            self::assertSame('2027-06-04 07:30:00', $conn->fetchOne("SELECT starts_at FROM department_grossanlass_einsatz WHERE id = 'etsa00000001'"));
            self::assertContains('decided_rejected', $conn->fetchFirstColumn('SELECT action FROM department_grossanlass_dispatch_event'));
            self::assertContains('requested', $conn->fetchFirstColumn('SELECT action FROM department_grossanlass_dispatch_event'));
        });
    }

    public function testScanStartAndArriveAreLimitedToAssignedDriverOrLogistics(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00', 'trip_released');
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET chauffeur_user_id = ? WHERE id = 'etsa00000001'", [self::DRIVER]);
            $em->clear();
            $d = $em->find(Department::class, self::D);
            $packId = trim((string) $conn->fetchOne("SELECT id FROM department_grossanlass_pack WHERE einsatz_id = 'etsa00000001'"));
            $status = static fn () => $conn->fetchOne('SELECT status FROM department_grossanlass_pack WHERE id = ?', [$packId]);

            // Verweigert: Mitglied ohne Zuteilung, Leiter ohne Logistikrecht, Benutzer ohne Mitgliedschaft
            foreach ([self::HELPER, self::LEADER, 'ushsa000nomb'] as $who) {
                try {
                    $packs->scanStart($d, self::user($em, $who), $packId);
                    self::fail('Unberechtigt darf nicht starten: ' . $who);
                } catch (\RuntimeException) {
                    self::assertSame('trip_released', $status());
                }
            }

            // Erlaubt: zugeteilter Chauffeur
            self::assertSame('in_transit', $packs->scanStart($d, self::user($em, self::DRIVER), $packId)['status']);

            // Ankunft: Fremde verweigert, Status bleibt unterwegs; falscher Ziel-QR und Chauffeur erlaubt
            try {
                $packs->scanArrive($d, self::user($em, self::HELPER), $packId, 'plsa00000001');
                self::fail('Unberechtigt darf nicht ankommen');
            } catch (\RuntimeException) {
                self::assertSame('in_transit', $status());
            }
            try {
                $packs->scanArrive($d, self::user($em, self::DRIVER), $packId, 'plsa00000002');
                self::fail('Falscher Ziel-QR');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Falscher Ziel-QR', $e->getMessage());
            }
            self::assertSame('at_place', $packs->scanArrive($d, self::user($em, self::DRIVER), $packId, 'plsa00000001')['status']);
        });
    }

    public function testLogisticsMayScanAndManualExceptionStillNeedsReasonAndIsLogged(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00', 'trip_released');
            self::trip($conn, 'etsa00000002', '2027-06-04 10:00', '2027-06-04 11:00', 'trip_released');
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET chauffeur_user_id = ? WHERE id IN ('etsa00000001', 'etsa00000002')", [self::DRIVER]);
            $em->clear();
            $d = $em->find(Department::class, self::D);
            $mw = self::user($em, self::MW);
            $packId = trim((string) $conn->fetchOne("SELECT id FROM department_grossanlass_pack WHERE einsatz_id = 'etsa00000001'"));

            self::assertSame('in_transit', $packs->scanStart($d, $mw, $packId)['status'], 'Logistikverantwortliche dürfen für den Chauffeur buchen');
            try {
                $packs->arriveManually($d, $mw, $packId, 'kurz');
                self::fail('Begründung bleibt Pflicht');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Begründung', $e->getMessage());
            }
            self::assertSame('at_place', $packs->arriveManually($d, $mw, $packId, 'Der QR-Code am Ziel ist abgerissen')['status']);
            self::assertSame('delivered_manually', $conn->fetchOne("SELECT action FROM department_grossanlass_dispatch_event WHERE subject_id = 'etsa00000001' AND action = 'delivered_manually'"));

            // Bestehender Tourstatus/Packstatus: nicht freigegebene Fahrt lässt sich auch von der Logistik nicht starten
            self::trip($conn, 'etsa00000003', '2027-06-04 12:00', '2027-06-04 13:00');
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET chauffeur_user_id = ? WHERE id = 'etsa00000003'", [self::DRIVER]);
            $em->clear();
            $d = $em->find(Department::class, self::D);
            $notReleased = trim((string) $conn->fetchOne("SELECT id FROM department_grossanlass_pack WHERE einsatz_id = 'etsa00000003'"));
            try {
                $packs->scanStart($d, self::user($em, self::MW), $notReleased);
                self::fail('Nicht freigegeben');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('noch nicht frei', $e->getMessage());
            }
        });
    }

    /** Fahrzeugwunsch mit Beschaffungslinie; `$vehicles` Fahrzeuge (Commitments) an der Linie. */
    private static function vehicleNeed(Connection $conn, int $vehicles = 1, bool $withLine = true, string $purpose = 'transport'): void
    {
        $line = $withLine ? "'plnsa0000001'" : 'NULL';
        if ($withLine) {
            $conn->executeStatement("INSERT INTO activity_grossanlass_procurement_line (id, department_id, group_id, wish_kind, label, quantity, location, created_by_user_id, created_at, updated_at) VALUES ('plnsa0000001', ?, 'grpsa0000001', 'vehicle', 'Stapler', 1, '', ?, now(), now())", [self::D, self::MW]);
        }
        for ($i = 0; $i < $vehicles; ++$i) {
            $conn->executeStatement("INSERT INTO department_grossanlass_commitment (id, department_id, name, family, source, services, procurement_line_id, created_at, updated_at) VALUES (?, ?, 'Stapler', 'vehicle', 'own', '[]', 'plnsa0000001', now(), now())", ['cmsv0000000' . $i, self::D]);
        }
        $conn->executeStatement("INSERT INTO department_grossanlass_vehicle_need (id, department_id, group_id, vehicle_label, task_label, purpose, starts_at, duration_minutes, procurement_line_id, created_at) VALUES ('vnsa00000001', ?, 'grpsa0000001', 'Stapler', 'Bühne laden', ?, '2027-06-03 07:00', 60, " . $line . ', now())', [self::D, $purpose]);
    }

    public function testVehicleNeedBecomesUnreleasedTripOrderExactlyOnce(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::vehicleNeed($conn);
            $mw = self::user($em, self::MW);
            $tour = $service->createTour($d, $mw, ['need_ids' => ['vn:vnsa00000001'], 'vehicle_id' => 'cmsa00000001', 'chauffeur_user_id' => self::DRIVER]);
            $before = (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_pack');

            try {
                $service->convertVehicleNeed($d, self::user($em, self::DC), 'vn:vnsa00000001');
                self::fail('Nur Logistikverantwortliche dürfen überführen');
            } catch (\RuntimeException) {
                self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz'));
            }

            $need = $service->convertVehicleNeed($d, $mw, 'vn:vnsa00000001');
            self::assertSame('vnsa00000001', $need['converted_from_vehicle_need_id']);
            self::assertFalse($need['released'], 'Neuer Fahrauftrag ist nicht freigegeben');
            self::assertNull($need['chauffeur_user_id']);
            self::assertSame($tour['id'], $need['tour_id'], 'Tourzuordnung bleibt erhalten');
            self::assertSame(1, (int) $conn->fetchOne("SELECT count(*) FROM department_grossanlass_einsatz WHERE vehicle_need_id = 'vnsa00000001' AND delivery = 'trip' AND trip_released_at IS NULL"));
            self::assertSame($before + 1, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_pack'), 'genau ein Pack');
            self::assertSame(1, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_tour_item WHERE tour_id = ?', [$tour['id']]));
            self::assertSame($need['id'], $conn->fetchOne('SELECT einsatz_id FROM department_grossanlass_tour_item WHERE tour_id = ?', [$tour['id']]));

            // Kein zweiter Bedarf: der Wunsch taucht nicht mehr neben dem Fahrauftrag auf
            $ids = array_column($service->overview($d, $mw)['needs'], 'id');
            self::assertContains($need['id'], $ids);
            self::assertNotContains('vn:vnsa00000001', $ids);

            try {
                $service->convertVehicleNeed($d, $mw, 'vn:vnsa00000001');
                self::fail('Bereits überführt');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('bereits', $e->getMessage());
            }
            self::assertSame(1, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz'));
        });
    }

    public function testVehicleNeedWithoutConcreteVehicleInventsNoDelivery(): void
    {
        foreach ([[0, false], [0, true], [2, true]] as [$vehicles, $withLine]) {
            $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn) use ($vehicles, $withLine): void {
                self::vehicleNeed($conn, $vehicles, $withLine);
                try {
                    $service->convertVehicleNeed($d, self::user($em, self::MW), 'vn:vnsa00000001');
                    self::fail('Kein Fahrauftrag ohne eindeutiges Fahrzeug');
                } catch (\InvalidArgumentException) {
                    self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz'));
                    self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_pack'));
                }
            });
        }
    }

    public function testReleaseNeedsADriverFromTour(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00');
            $mw = self::user($em, self::MW);
            try {
                $service->releaseNeed($d, $mw, 'etsa00000001');
                self::fail('Ohne Chauffeur nicht freigebbar');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Chauffeur', $e->getMessage());
            }
        });
    }

    public function testPlanningCapturesTripWithoutDriverOnlyWhenDeferred(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn, GrossanlassUebersichtService $uebersicht): void {
            $mw = self::user($em, self::MW);
            $base = ['commitment_id' => 'cmsa00000001', 'qty' => 1, 'from' => '2027-06-04T08:00:00', 'to' => '2027-06-04T09:00:00', 'delivery' => 'trip', 'group_id' => 'grpsa0000001', 'destination_place_id' => 'plsa00000001'];
            try {
                $uebersicht->createEinsatz($d, $mw, $base);
                self::fail('Ohne Planungs-Erfassung bleibt der Chauffeur Pflicht');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Chauffeur', $e->getMessage());
            }
            $uebersicht->createEinsatz($d, $mw, $base + ['chauffeur_deferred' => true]);
            self::assertSame(1, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz WHERE chauffeur_user_id IS NULL AND trip_released_at IS NULL'));
            $need = self::need($service, $d, $mw, (string) $conn->fetchOne('SELECT id FROM department_grossanlass_einsatz'));
            self::assertSame('open', $need['status']);
            self::assertFalse($need['released']);
        });
    }

    public function testSiteUseVehicleNeedIsNeverConvertedEvenWithAVehicleOnTheLine(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn): void {
            self::vehicleNeed($conn, 1, true, 'site_use');
            try {
                $service->convertVehicleNeed($d, self::user($em, self::MW), 'vn:vnsa00000001');
                self::fail('Einsatz auf der Baustelle ist kein Transportbedarf');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('kein Transportbedarf', $e->getMessage());
                self::assertSame(0, (int) $conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz'));
            }
        });
    }

    public function testAssignedDriverWithoutOverviewRightSeesOnlyOwnReleasedTrips(): void
    {
        $this->withServices(function (GrossanlassDispatchService $service, GrossanlassPackService $packs, EntityManagerInterface $em, Department $d, Connection $conn, GrossanlassUebersichtService $uebersicht): void {
            self::trip($conn, 'etsa00000001', '2027-06-04 08:00', '2027-06-04 09:00', 'trip_released'); // freigegeben, Chauffeur HELPER
            self::trip($conn, 'etsa00000002', '2027-06-04 10:00', '2027-06-04 11:00'); // nicht freigegeben, Chauffeur HELPER
            self::trip($conn, 'etsa00000003', '2027-06-04 12:00', '2027-06-04 13:00', 'trip_released'); // freigegeben, anderer Chauffeur
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET chauffeur_user_id = ? WHERE id IN ('etsa00000001', 'etsa00000002')", [self::HELPER]);
            $conn->executeStatement("UPDATE department_grossanlass_einsatz SET chauffeur_user_id = ? WHERE id = 'etsa00000003'", [self::DRIVER]);
            $em->clear();
            $d = $em->find(Department::class, self::D);

            $own = $uebersicht->overview($d, self::user($em, self::HELPER));
            self::assertSame(['etsa00000001'], array_map(static fn (array $row) => trim((string) $row['id']), $own['einsaetze']));
            self::assertSame([], $own['cards']);

            try {
                $uebersicht->overview($d, self::user($em, 'ushsa000nomb'));
                self::fail('Ohne Mitgliedschaft keine Übersicht');
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
            self::assertGreaterThanOrEqual(3, count($uebersicht->overview($d, self::user($em, self::MW))['einsaetze']), 'Logistik sieht weiterhin alles');
        });
    }
}
