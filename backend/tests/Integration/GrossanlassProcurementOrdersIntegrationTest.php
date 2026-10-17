<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassChargeMovementService;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassOvercoverageConfirmationRequiredException;
use App\Service\Grossanlass\GrossanlassProcurementService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Beschaffung vom Wunsch bis zum Wareneingang gegen die echte Datenbank: mehrere Bestellungen je Position,
 * Absprache ohne Offerte, Überdeckung, Wareneingang, Gegenbuchung, Storno und Löschschutz.
 * Läuft nur mit EMATCHEF_TEST_DB_URL gegen eine isolierte Datenbank (Name `val_*` oder `*_test`); jeder Test endet mit Rollback.
 */
final class GrossanlassProcurementOrdersIntegrationTest extends TestCase
{
    /** @param callable(Department, User, GrossanlassProcurementService, GrossanlassCommitmentService, GrossanlassChargeMovementService, EntityManagerInterface, Connection): void $test */
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
                $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('orgpr0000001','Org GA',now(),now())");
                $conn->executeStatement("INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES ('deptpr000001','orgpr0000001','GA Beschaffung',now(),now(),false,true)");
                $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('ppr000000001','mw-pr@example.org','Mat','Wart','MW','[\"ROLE_USER\"]',now(),now())");
                $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('upr000000001','ppr000000001','active','pw',true,now(),now())");
                $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES ('upr000000001','deptpr000001','mw',false)");
                $conn->executeStatement("INSERT INTO \"group\" (id,department_id,name,created_at,updated_at) VALUES ('grpr00000001','deptpr000001','Ressort Bau',now(),now())");
                $conn->executeStatement("INSERT INTO activity (id,department_id,name,created_at,updated_at) VALUES ('actpr0000001','deptpr000001','Anlass',now(),now())");
                $conn->executeStatement("INSERT INTO activity_grossanlass_round (id,activity_id,name,created_by_user_id,created_at,updated_at) VALUES ('rdpr00000001','actpr0000001','Runde 1','upr000000001',now(),now())");
                $department = $em->find(Department::class, 'deptpr000001');
                $user = $em->find(User::class, 'upr000000001');
                self::assertInstanceOf(Department::class, $department);
                self::assertInstanceOf(User::class, $user);
                $test(
                    $department,
                    $user,
                    $c->get(GrossanlassProcurementService::class),
                    $c->get(GrossanlassCommitmentService::class),
                    $c->get(GrossanlassChargeMovementService::class),
                    $em,
                    $conn,
                );
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassProcurementService::class, GrossanlassCommitmentService::class, GrossanlassChargeMovementService::class]);
    }

    private static function wish(Connection $conn, string $id, int $qty, string $unit = 'Stk'): void
    {
        $conn->executeStatement(
            "INSERT INTO activity_grossanlass_wish_line (id,round_id,group_id,wish_kind,label,quantity,location,valid_from,valid_to,created_by_user_id,created_at,updated_at,quantity_unit,procurement_responsibility)
             VALUES (?, 'rdpr00000001','grpr00000001','material','Festbank',?, 'Platz', '2027-07-01','2027-07-20','upr000000001',now(),now(),?, 'central')",
            [$id, $qty, $unit],
        );
    }

    /** @return array<string, mixed> */
    private static function directLine(GrossanlassProcurementService $p, Department $d, User $u, int $qty): array
    {
        return $p->createLineDirect($d, $u, ['group_id' => 'grpr00000001', 'label' => 'Festbank', 'quantity' => $qty, 'location' => 'Platz', 'wish_kind' => 'material']);
    }

    /** @param array<string, mixed> $line @return array<string, mixed> */
    private static function order(array $line, int $index): array
    {
        return $line['orders'][$index];
    }

    public function testWishCannotBeBundledTwice(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassProcurementService $p, $commitments, $movements, $em, Connection $conn): void {
            self::wish($conn, 'wspr00000001', 10);
            self::wish($conn, 'wspr00000002', 5);

            $line = $p->createLineFromWishes($d, $u, ['wspr00000001', 'wspr00000002'], ['label' => 'Festbänke']);
            self::assertSame(15, $line['quantity']);
            self::assertSame(15, $line['source_quantity_sum']);

            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('bereits einer Beschaffungsposition zugeordnet');
            $p->createLineFromWishes($d, $u, ['wspr00000002'], []);
        });
    }

    public function testTwoSuppliersPartialOrdersPhoneAgreementAndOvercoverage(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassProcurementService $p, $commitments, $movements, $em, Connection $conn): void {
            $line = self::directLine($p, $d, $u, 100);
            $lineId = $line['id'];
            $quote = $p->createQuote($d, $u, $lineId, ['supplier' => 'Firma Offerte AG', 'amount_chf' => 600]);

            // Offerten decken keinen Bedarf.
            $afterQuote = $p->listAllLines($d, $u, null, 'direct');
            self::assertSame(0, self::byId($afterQuote, $lineId)['quantity_covered']);

            $line = $p->createOrder($d, $u, $lineId, ['quote_id' => $quote['id'], 'quantity' => 60, 'cost_chf' => 600]);
            self::assertSame(60, $line['quantity_ordered']);
            self::assertSame(40, $line['quantity_open']);
            self::assertSame('bestellt', $line['status']);

            // Telefonische Absprache mit anderem Lieferanten, ohne Offerte: nimmt den offenen Rest.
            $line = $p->createOrder($d, $u, $lineId, ['supplier' => 'Müller Mietservice', 'cost_chf' => 300]);
            self::assertCount(2, $line['orders']);
            self::assertSame('phone', self::order($line, 1)['agreement_kind']);
            self::assertSame(40, self::order($line, 1)['ordered_quantity']);
            self::assertSame(100, $line['quantity_ordered']);
            self::assertSame(0, $line['quantity_open']);
            self::assertSame(0, $line['quantity_over']);
            self::assertNotSame(self::order($line, 0)['commitment_id'], self::order($line, 1)['commitment_id']);

            // Eine dritte Bestellung würde überdecken: nur mit bewusster Bestätigung.
            try {
                $p->createOrder($d, $u, $lineId, ['supplier' => 'Dritte AG', 'quantity' => 10, 'cost_chf' => 50]);
                self::fail('Überdeckung ohne Bestätigung muss abgelehnt werden');
            } catch (GrossanlassOvercoverageConfirmationRequiredException $e) {
                self::assertSame(100, $e->toPayload()['already_covered']);
            }
            $line = $p->createOrder($d, $u, $lineId, ['supplier' => 'Dritte AG', 'quantity' => 10, 'cost_chf' => 50, 'confirm_overcoverage' => true]);
            self::assertSame(10, $line['quantity_over']);
        });
    }

    public function testLoanPlusPurchaseDefaultsToTheOpenRemainder(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassProcurementService $p, GrossanlassCommitmentService $commitments, $movements, $em, Connection $conn): void {
            $line = self::directLine($p, $d, $u, 100);
            $commitments->create($d, $u, ['name' => 'Festbank', 'source' => 'Leihfirma', 'origin' => 'loan', 'quantity' => 40, 'procurement_line_id' => $line['id']]);

            $line = $p->createOrder($d, $u, $line['id'], ['supplier' => 'Müller', 'cost_chf' => 200]);

            self::assertSame(60, self::order($line, 0)['ordered_quantity']);
            self::assertSame(40, $line['quantity_loaned']);
            self::assertSame(0, $line['quantity_open']);
            self::assertSame(0, $line['quantity_over']);

            // Eine weitere Leih-Zusage über den Bedarf hinaus braucht Bestätigung.
            $this->expectException(GrossanlassOvercoverageConfirmationRequiredException::class);
            $commitments->create($d, $u, ['name' => 'Festbank', 'source' => 'Noch eine Leihfirma', 'origin' => 'loan', 'quantity' => 5, 'procurement_line_id' => $line['id']]);
        });
    }

    public function testReceiptsReversalCancelAndDeleteProtection(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassProcurementService $p, GrossanlassCommitmentService $commitments, GrossanlassChargeMovementService $movements, $em, Connection $conn): void {
            $line = self::directLine($p, $d, $u, 100);
            $lineId = $line['id'];
            $line = $p->createOrder($d, $u, $lineId, ['supplier' => 'A AG', 'quantity' => 60, 'cost_chf' => 600]);
            $line = $p->createOrder($d, $u, $lineId, ['supplier' => 'B AG', 'quantity' => 40, 'cost_chf' => 300]);
            $orderA = self::order($line, 0);
            $orderB = self::order($line, 1);

            // Mehrere Bestellungen: ohne Angabe ist unklar, wohin gebucht wird.
            try {
                $p->recordReceived($d, $u, $lineId, ['quantity' => 10]);
                self::fail('order_id erforderlich');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('mehrere Bestellungen', $e->getMessage());
            }

            $line = $p->recordReceived($d, $u, $lineId, ['order_id' => $orderA['id'], 'quantity' => 25]);
            self::assertSame('teilweise_erhalten', $line['status']);
            self::assertSame(25, $line['quantity_received']);
            self::assertSame(25, self::order($line, 0)['received_quantity']);
            self::assertSame(0, self::order($line, 1)['received_quantity']);
            // Keine automatische Verteilung auf die Wünsche der Position.
            self::assertSame(0, $line['received_quantity_sum']);

            // Überlieferung wird abgelehnt.
            try {
                $p->recordReceived($d, $u, $lineId, ['order_id' => $orderA['id'], 'quantity' => 40]);
                self::fail('Überlieferung');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Überlieferung', $e->getMessage());
            }

            // Gegenbuchung: Grund erforderlich, Historie bleibt.
            $chargeA = $em->find(DepartmentGrossanlassCommitment::class, $orderA['commitment_id']);
            self::assertInstanceOf(DepartmentGrossanlassCommitment::class, $chargeA);
            $result = $movements->record($d, $u, $chargeA->getId(), ['kind' => 'receipt_reversed', 'quantity' => 5, 'note' => 'Falsch gezählt']);
            self::assertSame(20, $result['inbound']['received']);
            self::assertSame(2, (int) $conn->fetchOne('SELECT COUNT(*) FROM department_grossanlass_charge_movement WHERE commitment_id = ?', [$chargeA->getId()]));
            try {
                $movements->record($d, $u, $chargeA->getId(), ['kind' => 'receipt_reversed', 'quantity' => 5]);
                self::fail('Grund erforderlich');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Grund', $e->getMessage());
            }

            // Löschschutz: Charge mit Wareneingang ist nicht löschbar; Storno nur ohne Eingang.
            try {
                $commitments->delete($d, $u, $chargeA->getId());
                self::fail('Charge mit Wareneingang darf nicht gelöscht werden');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Wareneingänge', $e->getMessage());
            }
            try {
                $p->cancelOrder($d, $u, $lineId, $orderA['id'], 'Irrtum');
                self::fail('Storno mit Eingang');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Gegenbuchung', $e->getMessage());
            }
            try {
                $p->deleteLine($d, $u, $lineId);
                self::fail('Position mit Bestellung nicht löschbar');
            } catch (\InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }

            // Storno der Bestellung B: Bedarf wird wieder offen, Bestellung und Charge bleiben als Historie.
            $line = $p->cancelOrder($d, $u, $lineId, $orderB['id'], 'Lieferant fällt aus');
            self::assertSame(60, $line['quantity_ordered']);
            self::assertSame(40, $line['quantity_open']);
            self::assertSame('cancelled', self::order($line, 1)['status']);
            self::assertSame('Lieferant fällt aus', self::order($line, 1)['cancel_reason']);
            self::assertSame(0, (int) $conn->fetchOne('SELECT quantity FROM department_grossanlass_commitment WHERE id = ?', [$orderB['commitment_id']]));

            // Rest von A geliefert: bestellt ist komplett eingegangen.
            $line = $p->recordReceived($d, $u, $lineId, ['order_id' => $orderA['id'], 'full' => true]);
            self::assertSame('erhalten', $line['status']);
            self::assertSame(60, $line['quantity_received']);
        });
    }

    public function testChangedWishNeedsADecisionAndNeverChangesAnOrder(): void
    {
        $this->withDb(function (Department $d, User $u, GrossanlassProcurementService $p, $commitments, $movements, $em, Connection $conn): void {
            self::wish($conn, 'wspr00000011', 10);
            $line = $p->createLineFromWishes($d, $u, ['wspr00000011'], []);
            $lineId = $line['id'];
            $line = $p->createOrder($d, $u, $lineId, ['supplier' => 'A AG', 'cost_chf' => 100]);
            self::assertNull($line['source_change']);

            // Wunsch wächst nach der Bestellung: Auswirkung wird berechnet, Bestellung bleibt.
            $conn->executeStatement("UPDATE activity_grossanlass_wish_line SET quantity = 14 WHERE id = 'wspr00000011'");
            $em->clear();
            $d = $em->find(Department::class, 'deptpr000001');
            $u = $em->find(User::class, 'upr000000001');
            $line = $p->listAllLines($d, $u, null, 'direct');
            $line = self::byId($line, $lineId);
            self::assertTrue($line['source_change']['decision_required']);
            self::assertTrue($line['source_change']['binding']);
            self::assertFalse($line['source_change']['can_adopt']);
            self::assertSame(10, $line['quantity']);
            self::assertSame(10, $line['quantity_ordered']);

            try {
                $p->decideSourceChange($d, $u, $lineId, 'adopt');
                self::fail('Verbindliche Position nicht automatisch ändern');
            } catch (\InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
            $line = $p->decideSourceChange($d, $u, $lineId, 'keep');
            self::assertNull($line['source_change']);
        });
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $lines
     *
     * @return array<string, mixed>
     */
    private static function byId(array $lines, string $id): array
    {
        foreach ($lines as $line) {
            if (is_array($line) && ($line['id'] ?? null) === $id) {
                return $line;
            }
        }
        self::fail('Position nicht gefunden: ' . $id);
    }
}
