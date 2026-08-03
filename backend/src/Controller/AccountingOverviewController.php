<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingAcquisitionFollowUp;
use App\Service\Accounting\AccountingExpectedCostsService;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Aggregierte Kennzahlen für die Buchhaltungs-Übersicht (Ist-Summen, keine Budget-Soll).
 */
#[Route('/api/departments/{departmentId}/accounting/overview', name: 'api_accounting_overview_')]
class AccountingOverviewController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingExpectedCostsService $expectedCosts,
    ) {
    }

    #[Route('', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function overview(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $yearParam = trim((string) $request->query->get('year', ''));
        $selectedYear = (int) date('Y');
        if ($yearParam !== '' && preg_match('/^\d{4}$/', $yearParam)) {
            $selectedYear = (int) $yearParam;
        }

        $conn = $this->entityManager->getConnection();
        $types = ['d' => ParameterType::STRING];

        $sqlYears = <<<'SQL'
            SELECT EXTRACT(YEAR FROM booked_at)::int AS y,
                   COALESCE(SUM(amount), 0)::numeric(12,2)::text AS total_chf,
                   COUNT(*)::int AS booking_count
            FROM accounting_booking
            WHERE department_id = :d
            GROUP BY 1
            ORDER BY 1 DESC
            SQL;
        $yearRows = $conn->executeQuery($sqlYears, ['d' => $departmentId], $types)->fetchAllAssociative();

        $years = [];
        foreach ($yearRows as $row) {
            $years[] = [
                'year' => (int) $row['y'],
                'total_chf' => $row['total_chf'],
                'booking_count' => (int) $row['booking_count'],
            ];
        }

        $yStart = sprintf('%04d-01-01', $selectedYear);
        $yEnd = sprintf('%04d-12-31', $selectedYear);

        $sqlByCc = <<<'SQL'
            SELECT cc.id AS cost_center_id,
                   cc.name AS name,
                   COALESCE(SUM(b.amount), 0)::numeric(12,2)::text AS total_chf,
                   COUNT(b.id)::int AS booking_count
            FROM accounting_cost_center cc
            LEFT JOIN accounting_booking b ON b.cost_center_id = cc.id
                AND b.department_id = :d
                AND b.booked_at >= CAST(:yStart AS DATE)
                AND b.booked_at <= CAST(:yEnd AS DATE)
            WHERE cc.department_id = :d
            GROUP BY cc.id, cc.name, cc.sort_order
            ORDER BY cc.sort_order ASC, cc.name ASC
            SQL;
        $ccRows = $conn->executeQuery($sqlByCc, [
            'd' => $departmentId,
            'yStart' => $yStart,
            'yEnd' => $yEnd,
        ], [
            'd' => ParameterType::STRING,
            'yStart' => ParameterType::STRING,
            'yEnd' => ParameterType::STRING,
        ])->fetchAllAssociative();

        $byCostCenter = [];
        $selectedTotal = '0.00';
        $selectedCount = 0;
        foreach ($ccRows as $row) {
            $byCostCenter[] = [
                'cost_center_id' => $row['cost_center_id'],
                'name' => $row['name'],
                'total_chf' => $row['total_chf'],
                'booking_count' => (int) $row['booking_count'],
            ];
            $selectedTotal = bcadd($selectedTotal, $row['total_chf'], 2);
            $selectedCount += (int) $row['booking_count'];
        }

        $sqlEntry = <<<'SQL'
            SELECT b.entry_type AS entry_type,
                   COALESCE(SUM(b.amount), 0)::numeric(12,2)::text AS total_chf,
                   COUNT(*)::int AS booking_count
            FROM accounting_booking b
            WHERE b.department_id = :d
              AND b.booked_at >= CAST(:yStart AS DATE)
              AND b.booked_at <= CAST(:yEnd AS DATE)
            GROUP BY b.entry_type
            ORDER BY SUM(b.amount) DESC
            SQL;
        $entryRows = $conn->executeQuery($sqlEntry, [
            'd' => $departmentId,
            'yStart' => $yStart,
            'yEnd' => $yEnd,
        ], [
            'd' => ParameterType::STRING,
            'yStart' => ParameterType::STRING,
            'yEnd' => ParameterType::STRING,
        ])->fetchAllAssociative();

        $byEntryType = [];
        foreach ($entryRows as $row) {
            $byEntryType[] = [
                'entry_type' => $row['entry_type'],
                'total_chf' => $row['total_chf'],
                'booking_count' => (int) $row['booking_count'],
            ];
        }

        $pendingFollowups = (int) $conn->executeQuery(
            'SELECT COUNT(*)::int FROM accounting_acquisition_follow_up WHERE department_id = :d AND status = :st',
            [
                'd' => $departmentId,
                'st' => AccountingAcquisitionFollowUp::STATUS_PENDING,
            ],
            ['d' => ParameterType::STRING, 'st' => ParameterType::STRING]
        )->fetchOne();

        $costCenterCount = (int) $conn->executeQuery(
            'SELECT COUNT(*)::int FROM accounting_cost_center WHERE department_id = :d',
            ['d' => $departmentId],
            ['d' => ParameterType::STRING]
        )->fetchOne();

        $expected = $this->expectedCosts->countsForDepartment($departmentId);

        return new JsonResponse([
            'years' => $years,
            'selected_year' => $selectedYear,
            'selected_year_total_chf' => $selectedTotal,
            'selected_year_booking_count' => $selectedCount,
            'by_cost_center' => $byCostCenter,
            'by_entry_type' => $byEntryType,
            'pending_followup_count' => $pendingFollowups,
            'expected_workshop_open_count' => $expected['workshop_open_count'],
            'expected_workshop_activity_count' => $expected['workshop_open_activity_count'],
            'cost_center_count' => $costCenterCount,
        ]);
    }
}
