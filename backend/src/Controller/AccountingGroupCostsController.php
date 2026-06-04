<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\User;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ist-Kosten pro Gruppe (Lesesicht für MW/DC/L1–L3 und Gruppenchefs).
 */
#[Route('/api/departments/{departmentId}/accounting/group-costs', name: 'api_accounting_group_costs_')]
class AccountingGroupCostsController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingGroupReportAccess($this->entityManager, $departmentId);
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

        $scope = $this->accountingGroupReportScope($this->entityManager, $departmentId);
        $allowedGroupIds = null;
        if ($scope === 'leader_limited') {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
            }
            $allowedGroupIds = $this->ledGroupIdsInDepartment($this->entityManager, $user, $departmentId);
            if ($allowedGroupIds === []) {
                return new JsonResponse([
                    'year' => $year,
                    'scope' => $scope,
                    'rows' => [],
                    'totals' => ['ist_chf' => '0.00', 'booking_count' => 0, 'open_chf' => '0.00'],
                ]);
            }
        }

        $conn = $this->entityManager->getConnection();
        $params = [
            'd' => $departmentId,
            'yStart' => $yStart,
            'yEnd' => $yEnd,
        ];
        $types = [
            'd' => ParameterType::STRING,
            'yStart' => ParameterType::STRING,
            'yEnd' => ParameterType::STRING,
        ];

        $groupFilter = '';
        if ($allowedGroupIds !== null) {
            $placeholders = [];
            foreach ($allowedGroupIds as $i => $gid) {
                $key = 'g'.$i;
                $placeholders[] = ':'.$key;
                $params[$key] = $gid;
                $types[$key] = ParameterType::STRING;
            }
            $groupFilter = ' AND g.id IN ('.implode(', ', $placeholders).')';
        }

        $sql = <<<SQL
            SELECT g.id AS group_id,
                   g.name AS group_name,
                   COALESCE(SUM(b.amount), 0)::numeric(12,2)::text AS total_chf,
                   COALESCE(SUM(CASE WHEN b.payment_status = 'open' THEN b.amount ELSE 0 END), 0)::numeric(12,2)::text AS open_chf,
                   COUNT(b.id)::int AS booking_count
            FROM "group" g
            LEFT JOIN accounting_booking b ON b.group_id = g.id
                AND b.department_id = :d
                AND b.booked_at >= CAST(:yStart AS DATE)
                AND b.booked_at <= CAST(:yEnd AS DATE)
            WHERE g.department_id = :d
            {$groupFilter}
            GROUP BY g.id, g.name
            HAVING COUNT(b.id) > 0
            ORDER BY SUM(b.amount) DESC, g.name ASC
            SQL;

        $rows = $conn->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $totalIst = '0.00';
        $totalOpen = '0.00';
        $totalCount = 0;
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'group_id' => $row['group_id'],
                'group_name' => $row['group_name'],
                'total_chf' => $row['total_chf'],
                'open_chf' => $row['open_chf'],
                'booking_count' => (int) $row['booking_count'],
            ];
            $totalIst = bcadd($totalIst, $row['total_chf'], 2);
            $totalOpen = bcadd($totalOpen, $row['open_chf'], 2);
            $totalCount += (int) $row['booking_count'];
        }

        return new JsonResponse([
            'year' => $year,
            'scope' => $scope,
            'rows' => $out,
            'totals' => [
                'ist_chf' => $totalIst,
                'open_chf' => $totalOpen,
                'booking_count' => $totalCount,
            ],
        ]);
    }
}
