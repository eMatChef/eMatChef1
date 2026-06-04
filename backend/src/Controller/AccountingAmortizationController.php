<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingBooking;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Abschreibungs-Vorschläge aus Anschaffungswerten (Material-Batches).
 */
#[Route('/api/departments/{departmentId}/accounting/amortization', name: 'api_accounting_amortization_')]
class AccountingAmortizationController extends AbstractController
{
    use AccountingMwOrDcTrait;

    private const DEFAULT_YEARS = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/suggestions', name: 'suggestions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function suggestions(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $yearParam = trim((string) $request->query->get('year', ''));
        $year = (int) date('Y');
        if ($yearParam !== '' && preg_match('/^\d{4}$/', $yearParam)) {
            $year = (int) $yearParam;
        }

        $years = max(1, min(30, (int) $request->query->get('useful_life_years', self::DEFAULT_YEARS)));
        $yStart = sprintf('%04d-01-01', $year);
        $yEnd = sprintf('%04d-12-31', $year);

        $conn = $this->entityManager->getConnection();

        $sqlValue = <<<'SQL'
            SELECT mi.id AS material_id,
                   mi.name AS material_name,
                   COALESCE(SUM(mb.qty * COALESCE(mb.unit_price, 0)), 0)::numeric(12,2)::text AS acquisition_value_chf
            FROM material_item mi
            INNER JOIN material_batch mb ON mb.material_item_id = mi.id
            WHERE mi.department_id = :d
              AND mb.batch_type IN ('initial', 'purchase')
              AND mb.status = 'active'
              AND mb.unit_price IS NOT NULL
              AND mb.unit_price::numeric > 0
            GROUP BY mi.id, mi.name
            HAVING SUM(mb.qty * COALESCE(mb.unit_price, 0)) > 0
            ORDER BY SUM(mb.qty * COALESCE(mb.unit_price, 0)) DESC, mi.name ASC
            SQL;

        $valueRows = $conn->executeQuery($sqlValue, ['d' => $departmentId], ['d' => ParameterType::STRING])
            ->fetchAllAssociative();

        $sqlBooked = <<<'SQL'
            SELECT b.material_item_id,
                   COALESCE(SUM(b.amount), 0)::numeric(12,2)::text AS booked_chf
            FROM accounting_booking b
            WHERE b.department_id = :d
              AND b.entry_type = 'amortization'
              AND b.booked_at >= CAST(:yStart AS DATE)
              AND b.booked_at <= CAST(:yEnd AS DATE)
              AND b.material_item_id IS NOT NULL
            GROUP BY b.material_item_id
            SQL;

        $bookedRows = $conn->executeQuery($sqlBooked, [
            'd' => $departmentId,
            'yStart' => $yStart,
            'yEnd' => $yEnd,
        ], [
            'd' => ParameterType::STRING,
            'yStart' => ParameterType::STRING,
            'yEnd' => ParameterType::STRING,
        ])->fetchAllAssociative();

        $bookedMap = [];
        foreach ($bookedRows as $row) {
            $bookedMap[(string) $row['material_item_id']] = $row['booked_chf'];
        }

        $suggestions = [];
        foreach ($valueRows as $row) {
            $acq = (float) $row['acquisition_value_chf'];
            $annual = round($acq / $years, 2);
            if ($annual <= 0) {
                continue;
            }
            $mid = (string) $row['material_id'];
            $already = $bookedMap[$mid] ?? '0.00';
            $suggestions[] = [
                'material_item_id' => $mid,
                'material_name' => $row['material_name'],
                'acquisition_value_chf' => $row['acquisition_value_chf'],
                'useful_life_years' => $years,
                'suggested_annual_chf' => number_format($annual, 2, '.', ''),
                'booked_amortization_chf' => $already,
                'remaining_suggestion_chf' => number_format(max(0, $annual - (float) $already), 2, '.', ''),
            ];
        }

        return new JsonResponse([
            'year' => $year,
            'useful_life_years' => $years,
            'suggestions' => $suggestions,
        ]);
    }
}
