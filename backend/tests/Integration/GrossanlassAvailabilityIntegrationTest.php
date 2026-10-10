<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassAvailabilityConflict;
use App\Service\Grossanlass\GrossanlassAvailabilityService;
use App\Service\Grossanlass\GrossanlassChargeMovementService;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassUebersichtService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Verfügbarkeit und Überbuchungsschutz der GA-Chargen gegen die echte Datenbank (Services, Sperre, Transaktion).
 * Läuft nur mit EMATCHEF_TEST_DB_URL gegen eine isolierte Datenbank (Name `val_*` oder `*_test`); jeder Test endet mit Rollback.
 */
final class GrossanlassAvailabilityIntegrationTest extends TestCase
{
    private const DAY = '2027-06-01';

    /** @param callable(Department, User, GrossanlassCommitmentService, GrossanlassUebersichtService, GrossanlassChargeMovementService, EntityManagerInterface, Connection, GrossanlassAvailabilityService): void $test */
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
            // Der Rollback-Rahmen des Tests verschachtelt die Transaktionen der Services; ein Konflikt darf nur seinen Teil zurückrollen.
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
            try {
                $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('orgga0000001','Org GA',now(),now())");
                $conn->executeStatement("INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES ('deptga000001','orgga0000001','GA Test',now(),now(),false,true)");
                $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('pga000000001','mw@example.org','Mat','Wart','MW','[\"ROLE_USER\"]',now(),now())");
                $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('uga000000001','pga000000001','active','pw',true,now(),now())");
                $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES ('uga000000001','deptga000001','mw',false)");
                $department = $em->find(Department::class, 'deptga000001');
                $user = $em->find(User::class, 'uga000000001');
                self::assertInstanceOf(Department::class, $department);
                self::assertInstanceOf(User::class, $user);
                $test(
                    $department,
                    $user,
                    $c->get(GrossanlassCommitmentService::class),
                    $c->get(GrossanlassUebersichtService::class),
                    $c->get(GrossanlassChargeMovementService::class),
                    $em,
                    $conn,
                    $c->get(GrossanlassAvailabilityService::class),
                );
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassCommitmentService::class, GrossanlassUebersichtService::class, GrossanlassChargeMovementService::class, GrossanlassAvailabilityService::class]);
    }

    /** @return array{commitment_id: string, qty: int, from: string, to: string, delivery: string} */
    private static function booking(string $commitmentId, int $qty, string $from, string $to, array $extra = []): array
    {
        return $extra + [
            'commitment_id' => $commitmentId,
            'qty' => $qty,
            'from' => self::DAY . 'T' . $from . ':00+00:00',
            'to' => self::DAY . 'T' . $to . ':00+00:00',
            'delivery' => 'pickup',
        ];
    }

    private static function expectConflict(callable $fn, string $kind): GrossanlassAvailabilityConflict
    {
        try {
            $fn();
        } catch (GrossanlassAvailabilityConflict $e) {
            self::assertSame($kind, $e->toPayload()['conflict']['kind']);
            self::assertSame('availability_conflict', $e->toPayload()['code']);
            self::assertNotSame('', $e->getMessage());

            return $e;
        }
        self::fail("Erwartet: Konflikt «$kind»");
    }

    /** @return string id der Charge */
    private static function charge(GrossanlassCommitmentService $commitments, Department $d, User $u, int $qty, string $origin = 'own', string $family = 'material'): string
    {
        return (string) $commitments->create($d, $u, ['name' => 'Festbank', 'source' => 'Testfirma', 'quantity' => $qty, 'origin' => $origin, 'family' => $family])['id'];
    }

    /** @return list<array<string, mixed>> */
    private static function activeEinsaetze(Connection $conn, string $commitmentId): array
    {
        return $conn->fetchAllAssociative("SELECT id, qty, status FROM department_grossanlass_einsatz WHERE commitment_id=? AND status<>'returned' ORDER BY id", [$commitmentId]);
    }

    public function testTwoAndThreeOverlappingBookingsAreCappedAtTheStock(): void
    {
        $this->withDb(function (Department $d, User $u, $commitments, GrossanlassUebersichtService $ueb, $movements, $em, Connection $conn): void {
            $id = self::charge($commitments, $d, $u, 10);

            $ueb->createEinsatz($d, $u, self::booking($id, 4, '08:00', '16:00'));
            $ueb->createEinsatz($d, $u, self::booking($id, 4, '09:00', '17:00'));
            // jedes Paar passt (8), alle drei gleichzeitig nicht (12)
            $e = self::expectConflict(fn () => $ueb->createEinsatz($d, $u, self::booking($id, 4, '10:00', '18:00')), 'overbooked');
            self::assertSame(10, $e->toPayload()['conflict']['capacity']);
            self::assertSame(12, $e->toPayload()['conflict']['peak']);
            self::assertCount(2, $e->toPayload()['conflict']['einsatz_ids']);
            self::assertCount(2, self::activeEinsaetze($conn, $id), 'nichts gespeichert');
            // Teilmenge, die noch passt
            $ueb->createEinsatz($d, $u, self::booking($id, 2, '10:00', '18:00'));
            // Zeitfenster ohne Überschneidung (berührend ab 18:00)
            $ueb->createEinsatz($d, $u, self::booking($id, 10, '18:00', '20:00'));
            self::assertCount(4, self::activeEinsaetze($conn, $id));
        });
    }

    public function testPendingApprovalBlocksAndClientConflictFlagDecidesNothing(): void
    {
        $this->withDb(function (Department $d, User $u, $commitments, GrossanlassUebersichtService $ueb, $movements, $em, Connection $conn): void {
            $id = self::charge($commitments, $d, $u, 10);

            $ueb->createEinsatz($d, $u, self::booking($id, 6, '08:00', '12:00', ['pending' => true]));
            self::expectConflict(fn () => $ueb->createEinsatz($d, $u, self::booking($id, 6, '09:00', '11:00')), 'overbooked');
            // `has_conflict` weder erzwingt Freigabe noch hebt es die Sperre auf
            self::expectConflict(fn () => $ueb->createEinsatz($d, $u, self::booking($id, 6, '09:00', '11:00', ['has_conflict' => false, 'pending' => true])), 'overbooked');
            $ueb->createEinsatz($d, $u, self::booking($id, 4, '09:00', '11:00', ['has_conflict' => true]));

            $status = $conn->fetchFirstColumn('SELECT status FROM department_grossanlass_einsatz WHERE commitment_id=? AND qty=4', [$id]);
            self::assertSame(['planned'], $status);
        });
    }

    public function testUniqueAndVehicleAreNeverAssignedTwiceAtOnce(): void
    {
        $this->withDb(function (Department $d, User $u, $commitments, GrossanlassUebersichtService $ueb, $movements, $em, Connection $conn): void {
            $single = self::charge($commitments, $d, $u, 1);
            $vehicle = self::charge($commitments, $d, $u, 3, 'own', 'vehicle');

            foreach ([$single, $vehicle] as $id) {
                $ueb->createEinsatz($d, $u, self::booking($id, 1, '08:00', '12:00', ['chauffeur_user_id' => $u->getId()]));
                self::expectConflict(fn () => $ueb->createEinsatz($d, $u, self::booking($id, 1, '11:00', '13:00', ['chauffeur_user_id' => $u->getId()])), 'unique_overlap');
                $ueb->createEinsatz($d, $u, self::booking($id, 1, '12:00', '13:00', ['chauffeur_user_id' => $u->getId()]));
            }
        });
    }

    public function testChangingABookingIsCheckedAndDoesNotCountItselfTwice(): void
    {
        $this->withDb(function (Department $d, User $u, $commitments, GrossanlassUebersichtService $ueb, $movements, $em, Connection $conn): void {
            $id = self::charge($commitments, $d, $u, 10);
            $ueb->createEinsatz($d, $u, self::booking($id, 6, '08:00', '12:00'));
            $ueb->createEinsatz($d, $u, self::booking($id, 4, '12:00', '14:00'));
            $rows = self::activeEinsaetze($conn, $id);
            $first = (string) $rows[0]['id'];
            $second = (string) $rows[1]['id'];
            if ((int) $rows[0]['qty'] === 4) {
                [$first, $second] = [$second, $first];
            }

            // eigene Menge ändern, nicht doppelt zählen
            $ueb->updateEinsatz($d, $u, $first, ['qty' => 10]);
            // Menge über den Bestand
            self::expectConflict(fn () => $ueb->updateEinsatz($d, $u, $first, ['qty' => 11]), 'overbooked');
            // verschieben in das Fenster der anderen Buchung
            self::expectConflict(fn () => $ueb->updateEinsatz($d, $u, $second, ['from' => self::DAY . 'T09:00:00+00:00', 'to' => self::DAY . 'T13:00:00+00:00']), 'overbooked');
            // reine Pack-/Fahrt-Änderung löst keine Prüfung aus
            $ueb->updateEinsatz($d, $u, $first, ['pack_phase' => 'aufbau']);
            // zurückgenommene Einsätze geben die Menge frei
            $ueb->updateEinsatz($d, $u, $first, ['status' => 'returned']);
            $ueb->updateEinsatz($d, $u, $second, ['from' => self::DAY . 'T09:00:00+00:00', 'to' => self::DAY . 'T13:00:00+00:00']);
            $ueb->createEinsatz($d, $u, self::booking($id, 6, '08:00', '14:00'));
            // Wiederöffnen eines zurückgenommenen Einsatzes prüft erneut
            self::expectConflict(fn () => $ueb->updateEinsatz($d, $u, $first, ['status' => 'planned']), 'overbooked');
        });
    }

    public function testIssueNeedsPhysicallyAvailableQuantityAndCountsIssuedOnce(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassCommitmentService $commitments, GrossanlassUebersichtService $ueb, GrossanlassChargeMovementService $movements, $em, Connection $conn): void {
            $loan = self::charge($commitments, $d, $u, 5, 'loan');
            $own = self::charge($commitments, $d, $u, 5, 'own');

            // Planen gegen die erwartete Menge, Ausgabe erst nach Wareneingang
            $ueb->createEinsatz($d, $u, self::booking($loan, 2, '08:00', '10:00'));
            $ueb->createEinsatz($d, $u, self::booking($loan, 2, '10:00', '12:00'));
            $ids = array_column(self::activeEinsaetze($conn, $loan), 'id');
            $e = self::expectConflict(fn () => $ueb->issueEinsatz($d, $u, (string) $ids[0], []), 'not_on_hand');
            self::assertSame(0, $e->toPayload()['conflict']['on_hand']);

            $movements->record($d, $u, $loan, ['kind' => 'received', 'quantity' => 3]);
            $ueb->issueEinsatz($d, $u, (string) $ids[0], []);
            // schon ausgegeben (2) zählt einmal: 3 vorhanden − 2 ausgegeben = 1 frei, der zweite Einsatz braucht 2
            $e = self::expectConflict(fn () => $ueb->issueEinsatz($d, $u, (string) $ids[1], []), 'not_on_hand');
            self::assertSame(2, $e->toPayload()['conflict']['already_issued']);
            // erneutes Ausgeben des bereits ausgegebenen Einsatzes ändert nichts
            $ueb->issueEinsatz($d, $u, (string) $ids[0], []);
            $movements->record($d, $u, $loan, ['kind' => 'received', 'quantity' => 1]);
            $ueb->issueEinsatz($d, $u, (string) $ids[1], []);
            self::assertSame(['issued', 'issued'], array_column(self::activeEinsaetze($conn, $loan), 'status'));

            // Eigenbestand ist ohne Wareneingang physisch vorhanden
            $ueb->createEinsatz($d, $u, self::booking($own, 5, '08:00', '10:00'));
            $ownId = (string) self::activeEinsaetze($conn, $own)[0]['id'];
            $ueb->issueEinsatz($d, $u, $ownId, []);
            // Ausgabe über den Status-Weg unterliegt derselben Regel
            $ueb->createEinsatz($d, $u, self::booking($loan, 1, '13:00', '14:00'));
            $late = (string) $conn->fetchOne("SELECT id FROM department_grossanlass_einsatz WHERE commitment_id=? AND status='planned'", [$loan]);
            self::expectConflict(fn () => $ueb->updateEinsatz($d, $u, $late, ['status' => 'issued']), 'not_on_hand');
        });
    }

    public function testLoweringTheChargeQuantityCannotGoBelowBookings(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassCommitmentService $commitments, GrossanlassUebersichtService $ueb, $movements, $em, Connection $conn): void {
            $id = self::charge($commitments, $d, $u, 10);
            $ueb->createEinsatz($d, $u, self::booking($id, 6, '08:00', '12:00'));
            $ueb->createEinsatz($d, $u, self::booking($id, 3, '09:00', '11:00'));

            self::expectConflict(fn () => $commitments->update($d, $u, $id, ['quantity' => 8]), 'below_booked');
            $commitments->update($d, $u, $id, ['quantity' => 9]);
            self::assertSame(9, (int) $conn->fetchOne('SELECT quantity FROM department_grossanlass_commitment WHERE id=?', [$id]));
        });
    }

    public function testConflictPreviewUsesTheSameCalculationAsTheBookingLock(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassCommitmentService $commitments, GrossanlassUebersichtService $ueb, $movements, $em, Connection $conn): void {
            $id = self::charge($commitments, $d, $u, 10);
            $ueb->createEinsatz($d, $u, self::booking($id, 4, '08:00', '16:00'));
            $ueb->createEinsatz($d, $u, self::booking($id, 4, '09:00', '17:00'));
            $ueb->createEinsatz($d, $u, self::booking($id, 2, '10:00', '18:00'));
            self::assertSame([], $ueb->overview($d, $u)['conflicts'], 'gebucht ohne Überbuchung: keine Vorschau-Konflikte');

            // Altbestand: Bestand nachträglich gesenkt (an der Sperre vorbei) → Vorschau meldet alle drei gemeinsam
            $conn->executeStatement('UPDATE department_grossanlass_commitment SET quantity=9 WHERE id=?', [$id]);
            $em->clear();
            $department = $em->find(Department::class, 'deptga000001');
            $user = $em->find(User::class, 'uga000000001');
            $conflicts = $ueb->overview($department, $user)['conflicts'];
            self::assertCount(1, $conflicts);
            self::assertSame('quantity_overbook', $conflicts[0]['kind']);
            self::assertCount(3, $conflicts[0]['einsatz_ids']);
        });
    }

    public function testChargeLockIsHeldForTheTransactionSoParallelBookingsQueue(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassCommitmentService $commitments, $ueb, $movements, EntityManagerInterface $em, Connection $conn, GrossanlassAvailabilityService $availability): void {
            $id = self::charge($commitments, $d, $u, 10);
            $commitment = $em->find(\App\Entity\DepartmentGrossanlassCommitment::class, $id);
            self::assertNotNull($commitment);

            $availability->transactional(function () use ($availability, $commitment, $conn, $id): void {
                $availability->lock($commitment);
                $locks = (int) $conn->fetchOne(
                    "SELECT count(*) FROM pg_locks l JOIN pg_class c ON c.oid=l.relation WHERE c.relname='department_grossanlass_commitment' AND l.mode='RowShareLock' AND l.pid=pg_backend_pid()",
                );
                self::assertGreaterThan(0, $locks, 'FOR UPDATE auf die Charge');
                self::assertTrue($conn->isTransactionActive());
            });
        });
    }
}
