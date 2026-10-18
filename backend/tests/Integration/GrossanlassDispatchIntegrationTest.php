<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassDispatchService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Lesemodell der Logistik-Disposition gegen die echte Datenbank: Fahraufträge, Packs, Fahrzeugwünsche, Status und
 * Begrenzung auf das Department. Läuft nur mit EMATCHEF_TEST_DB_URL; jeder Test endet mit Rollback.
 */
final class GrossanlassDispatchIntegrationTest extends TestCase
{
    private const D = 'deptsa000001';
    private const OTHER = 'deptsa000002';
    private const MW = 'ushsa00000mw';
    private const HELPER = 'ushsa000000u';

    /** @param callable(GrossanlassDispatchService, EntityManagerInterface, Department): void $test */
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
                $em->clear();
                $test($c->get(GrossanlassDispatchService::class), $em, $em->find(Department::class, self::D));
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassDispatchService::class]);
    }

    private static function trip(\Doctrine\DBAL\Connection $conn, string $id, string $dept, string $status, ?string $chauffeur = null, bool $released = false, ?string $dest = null): void
    {
        $conn->executeStatement(
            "INSERT INTO department_grossanlass_einsatz (id, department_id, kind, qty, starts_at, ends_at, status, place, who, delivery, trip_released_at, chauffeur_user_id, destination_place_id, created_at, updated_at)
             VALUES (?, ?, 'einsatz', 1, '2027-06-04 08:00', '2027-06-04 10:00', ?, 'lager', 'Festzelt', 'trip', ?, ?, ?, now(), now())",
            [$id, $dept, $status, $released ? '2027-06-01 08:00' : null, $chauffeur, $dest],
        );
    }

    public function testTripStatusesAndDepartmentScope(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $department): void {
            $conn = $em->getConnection();
            $conn->executeStatement("INSERT INTO department_grossanlass_place (id, department_id, name, public_code, created_at) VALUES ('plsa00000001', ?, 'Festgelände Süd', 'plsacode1', now())", [self::D]);
            self::trip($conn, 'etsa00000001', self::D, 'planned');
            self::trip($conn, 'etsa00000002', self::D, 'planned', self::MW, false, 'plsa00000001');
            self::trip($conn, 'etsa00000003', self::D, 'issued', self::MW, true);
            self::trip($conn, 'etsa00000004', self::D, 'returned', self::MW, true);
            self::trip($conn, 'etsa00000005', self::OTHER, 'planned');
            $conn->executeStatement("INSERT INTO department_grossanlass_einsatz (id, department_id, kind, qty, starts_at, ends_at, status, place, who, delivery, created_at, updated_at) VALUES ('etsa00000006', ?, 'einsatz', 1, '2027-06-04 08:00', '2027-06-04 10:00', 'planned', 'lager', 'Abholung', 'pickup', now(), now())", [self::D]);

            $mw = $em->find(User::class, self::MW);
            $data = $service->overview($department, $mw);

            $byId = [];
            foreach ($data['needs'] as $need) {
                $byId[$need['id']] = $need;
            }
            self::assertSame(['etsa00000001', 'etsa00000002', 'etsa00000003', 'etsa00000004'], array_keys($byId));
            self::assertSame('open', $byId['etsa00000001']['status']);
            self::assertSame('planned', $byId['etsa00000002']['status']);
            self::assertSame('Festgelände Süd', $byId['etsa00000002']['to']);
            self::assertSame('underway', $byId['etsa00000003']['status']);
            self::assertTrue($byId['etsa00000003']['released']);
            self::assertFalse($byId['etsa00000001']['released']);
            self::assertSame('done', $byId['etsa00000004']['status']);
            self::assertSame([], $data['tours']);
            self::assertArrayHasKey('now', $data);
        });
    }

    public function testPackStatusDrivesNeedStatusAndReadiness(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $department): void {
            $conn = $em->getConnection();
            self::trip($conn, 'etsa00000001', self::D, 'planned');
            self::trip($conn, 'etsa00000002', self::D, 'planned');
            $pack = static fn (string $id, string $einsatz, string $status) => $conn->executeStatement(
                "INSERT INTO department_grossanlass_pack (id, department_id, einsatz_id, public_code, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, now(), now())",
                [$id, self::D, $einsatz, 'code' . $id, $status],
            );
            $pack('pksa00000001', 'etsa00000001', 'in_transit');
            $pack('pksa00000002', 'etsa00000002', 'at_place');

            $mw = $em->find(User::class, self::MW);
            $byId = [];
            foreach ($service->overview($department, $mw)['needs'] as $need) {
                $byId[$need['id']] = $need;
            }
            self::assertSame('underway', $byId['etsa00000001']['status']);
            self::assertSame('done', $byId['etsa00000002']['status']);
            self::assertSame(['pksa00000001'], $byId['etsa00000001']['pack_ids']);
        });
    }

    public function testVehicleNeedsAppearWithoutTrip(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $department): void {
            $conn = $em->getConnection();
            $conn->executeStatement("INSERT INTO department_grossanlass_vehicle_need (id, department_id, group_id, vehicle_label, task_label, starts_at, duration_minutes, created_at) VALUES ('vnsa00000001', ?, 'grpsa0000001', 'Stapler', 'Bühne laden', '2027-06-03 07:00', 60, now())", [self::D]);

            $mw = $em->find(User::class, self::MW);
            $needs = $service->overview($department, $mw)['needs'];
            self::assertCount(1, $needs);
            self::assertSame('vn:vnsa00000001', $needs[0]['id']);
            self::assertSame('vnsa00000001', $needs[0]['vehicle_need_id']);
            self::assertSame('open', $needs[0]['status']);
            self::assertSame('Bühne', $needs[0]['target']);
            self::assertSame('Stapler · Bühne laden', $needs[0]['cargo']);
        });
    }

    public function testHelperIsDenied(): void
    {
        $this->withService(function (GrossanlassDispatchService $service, EntityManagerInterface $em, Department $department): void {
            $this->expectException(\RuntimeException::class);
            $service->overview($department, $em->find(User::class, self::HELPER));
        });
    }
}
