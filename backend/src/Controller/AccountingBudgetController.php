<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingBudgetLine;
use App\Entity\AccountingCostCenter;
use App\Entity\Department;
use App\Util\IdGenerator;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/accounting/budget', name: 'api_accounting_budget_')]
class AccountingBudgetController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/comparison/{year}', name: 'comparison', methods: ['GET'], requirements: ['year' => '\d{4}'])]
    #[IsGranted('ROLE_USER')]
    public function comparison(string $departmentId, int $year, Request $request): Response
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        if ($request->query->get('format') === 'csv') {
            return $this->comparisonCsv($departmentId, $year);
        }

        return new JsonResponse($this->buildComparisonPayload($departmentId, $year));
    }

    private function comparisonCsv(string $departmentId, int $year): StreamedResponse
    {
        $data = $this->buildComparisonPayload($departmentId, $year);
        $filename = sprintf('budget-ist-%s-%d.csv', $departmentId, $year);

        $response = new StreamedResponse(function () use ($data): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kostenstelle', 'Jahr', 'Soll CHF', 'Ist CHF', 'Rest (Soll−Ist)', 'Buchungen'], ';');
            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['cost_center_name'],
                    (string) $data['year'],
                    $row['budget_amount_chf'] ?? '',
                    $row['ist_amount_chf'],
                    $row['remaining_chf'] ?? '',
                    (string) $row['booking_count'],
                ], ';');
            }
            fputcsv($out, [
                'Summe',
                (string) $data['year'],
                $data['totals']['budget_chf'] ?? '',
                $data['totals']['ist_chf'],
                $data['totals']['remaining_chf'] ?? '',
                '',
            ], ';');
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComparisonPayload(string $departmentId, int $year): array
    {
        $yStart = sprintf('%04d-01-01', $year);
        $yEnd = sprintf('%04d-12-31', $year);
        $conn = $this->entityManager->getConnection();

        $sql = <<<'SQL'
            SELECT cc.id AS cost_center_id,
                   cc.name AS cost_center_name,
                   cc.sort_order,
                   bl.id AS budget_line_id,
                   bl.amount_chf::text AS budget_amount_chf,
                   bl.notes AS budget_notes,
                   COALESCE(SUM(b.amount), 0)::numeric(12,2)::text AS ist_amount_chf,
                   COUNT(b.id)::int AS booking_count
            FROM accounting_cost_center cc
            LEFT JOIN accounting_budget_line bl ON bl.cost_center_id = cc.id
                AND bl.department_id = :d
                AND bl.calendar_year = :cy
            LEFT JOIN accounting_booking b ON b.cost_center_id = cc.id
                AND b.department_id = :d
                AND b.booked_at >= CAST(:yStart AS DATE)
                AND b.booked_at <= CAST(:yEnd AS DATE)
            WHERE cc.department_id = :d
            GROUP BY cc.id, cc.name, cc.sort_order, bl.id, bl.amount_chf, bl.notes
            ORDER BY cc.sort_order ASC, cc.name ASC
            SQL;

        $rows = $conn->executeQuery($sql, [
            'd' => $departmentId,
            'cy' => $year,
            'yStart' => $yStart,
            'yEnd' => $yEnd,
        ], [
            'd' => ParameterType::STRING,
            'cy' => ParameterType::INTEGER,
            'yStart' => ParameterType::STRING,
            'yEnd' => ParameterType::STRING,
        ])->fetchAllAssociative();

        $outRows = [];
        $sumBudget = '0.00';
        $sumIst = '0.00';
        $sumRemaining = '0.00';
        $hasAnyBudget = false;

        foreach ($rows as $r) {
            $ist = $r['ist_amount_chf'];
            $budget = $r['budget_amount_chf'];
            $remaining = null;
            if ($budget !== null && $budget !== '') {
                $hasAnyBudget = true;
                $remaining = bcsub($budget, $ist, 2);
                $sumBudget = bcadd($sumBudget, $budget, 2);
                $sumRemaining = bcadd($sumRemaining, $remaining, 2);
            }
            $sumIst = bcadd($sumIst, $ist, 2);

            $outRows[] = [
                'cost_center_id' => $r['cost_center_id'],
                'cost_center_name' => $r['cost_center_name'],
                'sort_order' => (int) $r['sort_order'],
                'budget_line_id' => $r['budget_line_id'],
                'budget_amount_chf' => $budget,
                'budget_notes' => $r['budget_notes'],
                'ist_amount_chf' => $ist,
                'remaining_chf' => $remaining,
                'booking_count' => (int) $r['booking_count'],
            ];
        }

        $totals = [
            'ist_chf' => $sumIst,
            'budget_chf' => $hasAnyBudget ? $sumBudget : null,
            'remaining_chf' => $hasAnyBudget ? $sumRemaining : null,
        ];

        return [
            'year' => $year,
            'rows' => $outRows,
            'totals' => $totals,
        ];
    }

    #[Route('/lines', name: 'lines_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listLines(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $year = trim((string) $request->query->get('year', ''));
        if ($year === '' || !preg_match('/^\d{4}$/', $year)) {
            return new JsonResponse(['error' => 'Query year (YYYY) erforderlich'], 400);
        }

        $dept = $this->entityManager->getReference(Department::class, $departmentId);
        $lines = $this->entityManager->getRepository(AccountingBudgetLine::class)
            ->createQueryBuilder('bl')
            ->where('bl.department = :d')
            ->andWhere('bl.calendarYear = :y')
            ->setParameter('d', $dept)
            ->setParameter('y', (int) $year)
            ->orderBy('bl.id', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($lines as $line) {
            $out[] = $this->serializeLine($line);
        }

        return new JsonResponse($out);
    }

    #[Route('/lines', name: 'lines_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createLine(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $dept = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $ccId = trim((string) ($data['cost_center_id'] ?? ''));
        $cy = (int) ($data['calendar_year'] ?? 0);
        $amountRaw = $data['amount_chf'] ?? null;

        if ($ccId === '' || $cy < 2000 || $cy > 2100) {
            return new JsonResponse(['error' => 'cost_center_id und calendar_year (2000–2100) erforderlich'], 400);
        }

        $amount = $this->normalizeAmount($amountRaw);
        if ($amount === null) {
            return new JsonResponse(['error' => 'amount_chf ungültig'], 400);
        }

        $cc = $this->entityManager->getRepository(AccountingCostCenter::class)->find($ccId);
        if (!$cc || $cc->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Kostenstelle nicht gefunden'], 400);
        }

        $dup = $this->entityManager->getRepository(AccountingBudgetLine::class)->findOneBy([
            'department' => $dept,
            'costCenter' => $cc,
            'calendarYear' => $cy,
        ]);
        if ($dup) {
            return new JsonResponse(['error' => 'Budget für diese Kostenstelle und Jahr existiert bereits'], 409);
        }

        $line = new AccountingBudgetLine();
        $line->setId(IdGenerator::generate13Unique($this->entityManager, AccountingBudgetLine::class, 'bg'));
        $line->setDepartment($dept);
        $line->setCostCenter($cc);
        $line->setCalendarYear($cy);
        $line->setAmountChf($amount);
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
        $line->setNotes($notes === '' ? null : $notes);

        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeLine($line), 201);
    }

    #[Route('/lines/{id}', name: 'lines_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateLine(string $departmentId, string $id, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $line = $this->entityManager->getRepository(AccountingBudgetLine::class)->find($id);
        if (!$line || $line->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Budgetzeile nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('amount_chf', $data)) {
            $amount = $this->normalizeAmount($data['amount_chf']);
            if ($amount === null) {
                return new JsonResponse(['error' => 'amount_chf ungültig'], 400);
            }
            $line->setAmountChf($amount);
        }
        if (array_key_exists('notes', $data)) {
            $n = trim((string) $data['notes']);
            $line->setNotes($n === '' ? null : $n);
        }
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeLine($line));
    }

    #[Route('/lines/{id}', name: 'lines_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteLine(string $departmentId, string $id): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $line = $this->entityManager->getRepository(AccountingBudgetLine::class)->find($id);
        if (!$line || $line->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Budgetzeile nicht gefunden'], 404);
        }

        $this->entityManager->remove($line);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function normalizeAmount(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = str_replace([' ', ','], ['', '.'], (string) $raw);
        if (!is_numeric($s)) {
            return null;
        }
        $n = (float) $s;
        if ($n < 0) {
            return null;
        }

        return number_format($n, 2, '.', '');
    }

    private function serializeLine(AccountingBudgetLine $line): array
    {
        return [
            'id' => $line->getId(),
            'department_id' => $line->getDepartment()->getId(),
            'cost_center_id' => $line->getCostCenter()->getId(),
            'cost_center_name' => $line->getCostCenter()->getName(),
            'calendar_year' => $line->getCalendarYear(),
            'amount_chf' => $line->getAmountChf(),
            'notes' => $line->getNotes(),
            'created_at' => $line->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $line->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
