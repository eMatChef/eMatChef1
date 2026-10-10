<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassAvailabilityConflict;
use App\Service\Grossanlass\GrossanlassChargeMovementService;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassGaesteService;
use App\Service\Grossanlass\GrossanlassUebersichtService;
use App\Service\MaterialPeriodAvailability;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Gast-Material im Grossanlass gegen die echte Datenbank: Freigaben sind durch den freien Bestand des Gast-Departments
 * begrenzt (Aktivitäten, andere Anlässe, Zeitfenster), angenommene Zusagen blockieren dort, Sperre und Transaktion.
 * Nur mit EMATCHEF_TEST_DB_URL gegen eine isolierte Datenbank (`val_*` oder `*_test`); jeder Test endet mit Rollback.
 */
final class GrossanlassGuestStockIntegrationTest extends TestCase
{
    private const GUEST = 'deptguest001';
    private const GA1 = 'deptgahost01';
    private const GA2 = 'deptgahost02';
    private const ITEM = 'itemguest001';

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
                $this->seed($conn);
                $ctx = new class($em, $conn, $c->get(GrossanlassGaesteService::class), $c->get(GrossanlassCommitmentService::class), $c->get(GrossanlassUebersichtService::class), $c->get(GrossanlassChargeMovementService::class), $c->get(MaterialPeriodAvailability::class)) {
                    public function __construct(
                        public EntityManagerInterface $em,
                        public Connection $conn,
                        public GrossanlassGaesteService $gaeste,
                        public GrossanlassCommitmentService $commitments,
                        public GrossanlassUebersichtService $uebersicht,
                        public GrossanlassChargeMovementService $movements,
                        public MaterialPeriodAvailability $stock,
                    ) {
                    }

                    public function dept(string $id): Department
                    {
                        $d = $this->em->find(Department::class, $id);
                        TestCase::assertInstanceOf(Department::class, $d);

                        return $d;
                    }

                    public function user(string $id): User
                    {
                        $u = $this->em->find(User::class, $id);
                        TestCase::assertInstanceOf(User::class, $u);

                        return $u;
                    }
                };
                $test($ctx);
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassGaesteService::class, GrossanlassCommitmentService::class, GrossanlassUebersichtService::class, GrossanlassChargeMovementService::class, MaterialPeriodAvailability::class]);
    }

    private function seed(Connection $conn): void
    {
        $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('orggst000001','Org',now(),now())");
        foreach ([[self::GUEST, 'Gast Abteilung', 'false'], [self::GA1, 'GA Eins', 'true'], [self::GA2, 'GA Zwei', 'true'], ['deptother001', 'Fremd', 'false']] as [$id, $name, $ga]) {
            $conn->executeStatement("INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES (?,?,?,now(),now(),false,$ga)", [$id, 'orggst000001', $name]);
        }
        foreach ([['ugstmgr00001', self::GUEST], ['ugamw0000001', self::GA1], ['ugamw0000002', self::GA2], ['uother000001', 'deptother001']] as $n => [$uid, $dept]) {
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES (?,?, 'T','T','T','[\"ROLE_USER\"]',now(),now())", ['pgst0000000' . $n, "g$n@example.org"]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES (?,?, 'active','pw',true,now(),now())", [$uid, 'pgst0000000' . $n]);
            $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES (?,?,'mw',false)", [$uid, $dept]);
        }
        foreach ([self::GA1 => 'pgstpart0001', self::GA2 => 'pgstpart0002'] as $host => $pid) {
            $conn->executeStatement("INSERT INTO department_grossanlass_participant (id,host_department_id,guest_department_id,status,created_at) VALUES (?,?,?,'accepted',now())", [$pid, $host, self::GUEST]);
        }
        $conn->executeStatement("INSERT INTO material_item (id,department_id,name,created_at,updated_at) VALUES (?,?, 'Festbankgarnitur',now(),now())", [self::ITEM, self::GUEST]);
        $conn->executeStatement("INSERT INTO material_batch (id,material_item_id,acquired_on,qty,created_at) VALUES ('batchgst0001',?,'2026-01-01',10,now())", [self::ITEM]);
    }

    private static function activity(Connection $conn, string $id, int $qty, string $from, string $to, string $status = 'approved'): void
    {
        $conn->executeStatement('INSERT INTO activity (id,department_id,name,status,planning_start,planning_end,created_at,updated_at) VALUES (?,?,?,?,?,?,now(),now())', [$id, self::GUEST, 'Lager', $status, $from, $to]);
        $conn->executeStatement('INSERT INTO activity_item (id,activity_id,material_item_id,quantity,created_at,updated_at) VALUES (?,?,?,?,now(),now())', ['ai' . substr($id, 2), $id, self::ITEM, $qty]);
    }

    /** @return array<string, mixed> */
    private static function release(object $c, string $host, int $qty, string $from, string $to): array
    {
        $result = $c->gaeste->releaseAsGuest($c->dept(self::GUEST), $c->user('ugstmgr00001'), $host, ['material_item_id' => self::ITEM, 'qty' => $qty, 'from' => $from, 'to' => $to]);
        foreach ($result['releases'] as $r) {
            if ($r['qty'] === $qty && substr($r['from'] ?? '', 0, 10) === $from) {
                return $r;
            }
        }
        self::fail('Freigabe nicht gefunden');
    }

    private static function expectStockConflict(callable $fn, string $kind = 'guest_stock'): GrossanlassAvailabilityConflict
    {
        try {
            $fn();
        } catch (GrossanlassAvailabilityConflict $e) {
            self::assertSame($kind, $e->toPayload()['conflict']['kind']);

            return $e;
        }
        self::fail("Erwartet: Konflikt «$kind»");
    }

    private static function free(object $c, string $from, string $to): int
    {
        return $c->stock->availableForIds([self::ITEM], new \DateTime($from), new \DateTime($to))[self::ITEM];
    }

    public function testOfferIsLimitedByActivitiesAndTheTimeWindow(): void
    {
        $this->withDb(function (object $c): void {
            self::activity($c->conn, 'acteigen0001', 6, '2027-06-01 00:00:00', '2027-06-03 00:00:00');

            $e = self::expectStockConflict(fn () => self::release($c, self::GA1, 5, '2027-06-02', '2027-06-04'));
            self::assertSame(4, $e->toPayload()['conflict']['available']);
            self::release($c, self::GA1, 4, '2027-06-02', '2027-06-04');
            // Teilmenge und anderes Fenster ohne Überschneidung: voller Bestand frei
            $c->gaeste->releaseAsGuest($c->dept(self::GUEST), $c->user('ugstmgr00001'), self::GA2, ['material_item_id' => self::ITEM, 'qty' => 10, 'from' => '2027-07-01', 'to' => '2027-07-03']);
        });
    }

    public function testAcceptedShareBlocksTheGuestAndOtherEventsAndOffersAreNotDoubled(): void
    {
        $this->withDb(function (object $c): void {
            $first = self::release($c, self::GA1, 6, '2027-06-01', '2027-06-05');
            // offenes Angebot zählt bei neuen Angeboten (kein doppeltes Anbieten desselben Materials)
            self::expectStockConflict(fn () => self::release($c, self::GA2, 5, '2027-06-02', '2027-06-04'));
            self::assertSame(10, self::free($c, '2027-06-02', '2027-06-04'), 'ein offenes Angebot blockiert die Aktivitäten noch nicht');

            $c->gaeste->accept($c->dept(self::GA1), $c->user('ugamw0000001'), $first['id']);
            // angenommen: im Gast-Department blockiert (Aktivitäten, Verfügbarkeits-API) und für andere Anlässe nicht mehr frei
            self::assertSame(4, self::free($c, '2027-06-02', '2027-06-04'));
            self::assertSame(10, self::free($c, '2027-07-01', '2027-07-02'), 'ausserhalb des Fensters frei');
            self::expectStockConflict(fn () => self::release($c, self::GA2, 5, '2027-06-02', '2027-06-04'));
            self::release($c, self::GA2, 4, '2027-06-02', '2027-06-04');
        });
    }

    public function testAcceptChecksAgainAndIsIdempotent(): void
    {
        $this->withDb(function (object $c): void {
            $share = self::release($c, self::GA1, 4, '2027-06-01', '2027-06-05');
            // inzwischen plant eine eigene Aktivität das Material
            self::activity($c->conn, 'acteigen0002', 8, '2027-06-02 00:00:00', '2027-06-03 00:00:00');
            $e = self::expectStockConflict(fn () => $c->gaeste->accept($c->dept(self::GA1), $c->user('ugamw0000001'), $share['id']));
            self::assertSame(2, $e->toPayload()['conflict']['available']);
            self::assertSame('offered', $c->conn->fetchOne('SELECT status FROM department_grossanlass_guest_share WHERE id=?', [$share['id']]));
            self::assertSame(0, (int) $c->conn->fetchOne('SELECT count(*) FROM department_grossanlass_commitment'));

            $c->conn->executeStatement('DELETE FROM activity_item WHERE activity_id=?', ['acteigen0002']);
            $c->gaeste->accept($c->dept(self::GA1), $c->user('ugamw0000001'), $share['id']);
            $c->gaeste->accept($c->dept(self::GA1), $c->user('ugamw0000001'), $share['id']);
            self::assertSame(1, (int) $c->conn->fetchOne('SELECT count(*) FROM department_grossanlass_commitment'));
        });
    }

    public function testGuestChargeKeepsOriginOwnerAndReturnObligation(): void
    {
        $this->withDb(function (object $c): void {
            $share = self::release($c, self::GA1, 4, '2027-06-01', '2027-06-05');
            $c->gaeste->accept($c->dept(self::GA1), $c->user('ugamw0000001'), $share['id']);

            $row = $c->conn->fetchAssociative('SELECT origin, owner_kind, owner_department_id, return_required, quantity, source FROM department_grossanlass_commitment');
            self::assertSame('loan', $row['origin']);
            self::assertSame('department', $row['owner_kind']);
            self::assertSame(self::GUEST, trim((string) $row['owner_department_id']));
            self::assertTrue((bool) $row['return_required']);
            self::assertSame(4, (int) $row['quantity']);
            self::assertSame('Gast Abteilung', $row['source']);
        });
    }

    public function testReducingCancelingAndReturningReleaseTheReservation(): void
    {
        $this->withDb(function (object $c): void {
            $guest = $c->dept(self::GUEST);
            $mgr = $c->user('ugstmgr00001');
            $share = self::release($c, self::GA1, 6, '2027-06-01', '2027-06-05');
            $c->gaeste->accept($c->dept(self::GA1), $c->user('ugamw0000001'), $share['id']);
            self::assertSame(4, self::free($c, '2027-06-02', '2027-06-03'));

            // Verringern gibt Menge frei und passt die Charge an; Zeitfenster einer angenommenen Freigabe bleibt
            $c->gaeste->updateRelease($guest, $mgr, self::GA1, $share['id'], ['qty' => 3]);
            self::assertSame(7, self::free($c, '2027-06-02', '2027-06-03'));
            self::assertSame(3, (int) $c->conn->fetchOne('SELECT quantity FROM department_grossanlass_commitment'));
            try {
                $c->gaeste->updateRelease($guest, $mgr, self::GA1, $share['id'], ['from' => '2027-06-02']);
                self::fail('Zeitfenster darf sich nicht ändern');
            } catch (\InvalidArgumentException) {
            }
            // Erhöhen wird wieder gegen den Bestand geprüft
            self::activity($c->conn, 'acteigen0003', 6, '2027-06-02 00:00:00', '2027-06-03 00:00:00');
            self::expectStockConflict(fn () => $c->gaeste->updateRelease($guest, $mgr, self::GA1, $share['id'], ['qty' => 5]));

            // Rückgabe an das Gast-Department (returned_to_firm) gibt die Menge frei
            $c->conn->executeStatement('UPDATE department_grossanlass_commitment SET returned_to_firm = TRUE');
            self::assertSame(4, self::free($c, '2027-06-02', '2027-06-03'));
        });
    }

    public function testWithdrawWorksOnlyForUnusedChargesAndIsIdempotent(): void
    {
        $this->withDb(function (object $c): void {
            $guest = $c->dept(self::GUEST);
            $mgr = $c->user('ugstmgr00001');
            $ga = $c->dept(self::GA1);
            $mw = $c->user('ugamw0000001');

            // angeboten → zurückgezogen, danach erneut anbietbar
            $offer = self::release($c, self::GA1, 5, '2027-06-01', '2027-06-05');
            $c->gaeste->withdrawRelease($guest, $mgr, self::GA1, $offer['id']);
            $c->gaeste->withdrawRelease($guest, $mgr, self::GA1, $offer['id']);
            self::assertSame('declined', $c->conn->fetchOne('SELECT status FROM department_grossanlass_guest_share WHERE id=?', [$offer['id']]));
            $second = self::release($c, self::GA1, 5, '2027-06-01', '2027-06-05');
            self::assertSame($offer['id'], $second['id'], 'die zurückgezogene Freigabe wird wiederverwendet');

            // angenommen und ungenutzt → Charge entfernt, Menge frei
            $c->gaeste->accept($ga, $mw, $second['id']);
            self::assertSame(5, self::free($c, '2027-06-02', '2027-06-03'));
            $c->gaeste->withdrawRelease($guest, $mgr, self::GA1, $second['id']);
            self::assertSame(0, (int) $c->conn->fetchOne('SELECT count(*) FROM department_grossanlass_commitment'));
            self::assertSame(10, self::free($c, '2027-06-02', '2027-06-03'));

            // angenommen und gebucht → bleibt, Rückgabe läuft im Grossanlass
            $again = self::release($c, self::GA1, 5, '2027-06-01', '2027-06-05');
            $c->gaeste->accept($ga, $mw, $again['id']);
            $commitmentId = (string) $c->conn->fetchOne('SELECT id FROM department_grossanlass_commitment');
            $c->uebersicht->createEinsatz($ga, $mw, ['commitment_id' => $commitmentId, 'qty' => 2, 'from' => '2027-06-02T08:00:00+00:00', 'to' => '2027-06-02T12:00:00+00:00', 'delivery' => 'pickup']);
            self::expectStockConflict(fn () => $c->gaeste->withdrawRelease($guest, $mgr, self::GA1, $again['id']), 'in_use');
            self::assertSame('accepted', $c->conn->fetchOne('SELECT status FROM department_grossanlass_guest_share WHERE id=?', [$again['id']]));
            self::assertSame(1, (int) $c->conn->fetchOne('SELECT count(*) FROM department_grossanlass_commitment'));
            // Wareneingang und Buchungen bleiben bestehen
            self::assertSame(1, (int) $c->conn->fetchOne('SELECT count(*) FROM department_grossanlass_einsatz'));
        });
    }

    public function testOnlyTheOwnGuestDepartmentChangesItsReleasesAndTheGrossanlassCannotRaiseThem(): void
    {
        $this->withDb(function (object $c): void {
            $share = self::release($c, self::GA1, 4, '2027-06-01', '2027-06-05');
            $ga = $c->dept(self::GA1);
            $mw = $c->user('ugamw0000001');
            $c->gaeste->accept($ga, $mw, $share['id']);

            // ein anderes Department (mit eigener Verwaltungsrolle) findet fremde Freigaben nicht
            try {
                $c->gaeste->updateRelease($c->dept('deptother001'), $c->user('uother000001'), self::GA1, $share['id'], ['qty' => 1]);
                self::fail('Fremde Freigabe darf nicht änderbar sein');
            } catch (\InvalidArgumentException) {
            }
            try {
                $c->gaeste->withdrawRelease($c->dept('deptother001'), $c->user('uother000001'), self::GA1, $share['id']);
                self::fail('Fremde Freigabe darf nicht zurückgezogen werden');
            } catch (\InvalidArgumentException) {
            }
            // ein Benutzer ohne Rolle im Gast-Department darf nichts
            try {
                $c->gaeste->withdrawRelease($c->dept(self::GUEST), $c->user('uother000001'), self::GA1, $share['id']);
                self::fail('Ohne Mitgliedschaft nicht erlaubt');
            } catch (\RuntimeException) {
            }
            // der Grossanlass erhöht die Menge der Gast-Charge nicht über die Freigabe
            $commitmentId = (string) $c->conn->fetchOne('SELECT id FROM department_grossanlass_commitment');
            try {
                $c->commitments->update($ga, $mw, $commitmentId, ['quantity' => 9]);
                self::fail('Menge gehört dem Gast-Department');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Gast-Department', $e->getMessage());
            }
            self::assertSame('accepted', $c->conn->fetchOne('SELECT status FROM department_grossanlass_guest_share WHERE id=?', [$share['id']]));
            self::assertSame(4, (int) $c->conn->fetchOne('SELECT quantity FROM department_grossanlass_commitment'));
        });
    }
}
