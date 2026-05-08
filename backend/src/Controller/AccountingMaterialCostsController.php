<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Aggregierte Ist-Kosten CHF pro Material (über Buchungen mit material_item_id
 * oder Anschaffungs-Follow-up → Charge → Material).
 */
#[Route('/api/departments/{departmentId}/accounting/material-costs', name: 'api_accounting_material_costs_')]
class AccountingMaterialCostsController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $yearParam = trim((string) $request->query->get('year', ''));
        if ($yearParam === '' || !preg_match('/^\d{4}$/', $yearParam)) {
            return new JsonResponse(['error' => 'Query year (YYYY) erforderlich'], 400);
        }
        $year = (int) $yearParam;
        $yStart = sprintf('%04d-01-01', $year);
        $yEnd = sprintf('%04d-12-31', $year);

        $conn = $this->entityManager->getConnection();
        $sql = <<<'SQL'
            WITH part AS (
                SELECT mi.id AS material_id,
                       mi.name AS material_name,
                       b.amount::numeric(12,2) AS amt,
                       b.id AS bid
                FROM accounting_booking b
                INNER JOIN material_item mi ON mi.id = b.material_item_id
                WHERE b.department_id = :d
                  AND b.booked_at >= CAST(:yStart AS DATE)
                  AND b.booked_at <= CAST(:yEnd AS DATE)

                UNION ALL

                SELECT mi.id,
                       mi.name,
                       b.amount::numeric(12,2),
                       b.id
                FROM accounting_booking b
                INNER JOIN accounting_acquisition_follow_up f ON f.accounting_booking_id = b.id AND f.status = 'recorded'
                INNER JOIN material_batch mb ON mb.id = f.material_batch_id
                INNER JOIN material_item mi ON mi.id = mb.material_item_id
                WHERE b.department_id = :d
                  AND b.booked_at >= CAST(:yStart AS DATE)
                  AND b.booked_at <= CAST(:yEnd AS DATE)
                  AND b.material_item_id IS NULL
            )
            SELECT material_id,
                   material_name,
                   COALESCE(SUM(amt), 0)::numeric(12,2)::text AS total_chf,
                   COUNT(DISTINCT bid)::int AS booking_count
            FROM part
            GROUP BY material_id, material_name
            ORDER BY SUM(amt) DESC, material_name ASC
            SQL;

        $rows = $conn->executeQuery($sql, [
            'd' => $departmentId,
            'yStart' => $yStart,
            'yEnd' => $yEnd,
        ], [
            'd' => ParameterType::STRING,
            'yStart' => ParameterType::STRING,
            'yEnd' => ParameterType::STRING,
        ])->fetchAllAssociative();

        $out = [];
        $sumTotal = '0.00';
        $sumCount = 0;
        foreach ($rows as $r) {
            $out[] = [
                'material_id' => $r['material_id'],
                'material_name' => $r['material_name'],
                'total_chf' => $r['total_chf'],
                'booking_count' => (int) $r['booking_count'],
            ];
            $sumTotal = bcadd($sumTotal, $r['total_chf'], 2);
            $sumCount += (int) $r['booking_count'];
        }

        return new JsonResponse([
            'year' => $year,
            'rows' => $out,
            'totals' => [
                'total_chf' => $sumTotal,
                'booking_count' => $sumCount,
            ],
        ]);
    }
}
