<?php

namespace App\Service\Accounting;

use App\Entity\WorkshopTicket;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * #7 Phase 5: Read-only «Kosten folgen» — offene Werkstatt-Tickets mit Aktivitätsbezug.
 * Keine Buchung, kein Follow-up — nur Erwartung bis Ticket abgeschlossen + actual_cost.
 */
final class AccountingExpectedCostsService
{
    private const OPEN_STATUSES = [
        WorkshopTicket::STATUS_OPEN,
        WorkshopTicket::STATUS_IN_PROGRESS,
        WorkshopTicket::STATUS_WAITING_PARTS,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{workshop_open_count: int, workshop_open_activity_count: int, items: list<array<string, mixed>>}
     */
    public function listForDepartment(string $departmentId): array
    {
        $conn = $this->entityManager->getConnection();
        $statusList = implode(',', array_map(
            static fn (string $s): string => $conn->quote($s),
            self::OPEN_STATUSES,
        ));

        // Billing-Dept wie syncWorkshopFollowUps: Material-Owner, sonst Aktivitäts-Dept.
        $sql = <<<SQL
            SELECT wt.id AS ticket_id,
                   wt.title AS ticket_title,
                   wt.status AS ticket_status,
                   wt.estimated_cost::text AS estimated_cost_chf,
                   wt.activity_id AS activity_id,
                   a.name AS activity_name,
                   mi.id AS material_item_id,
                   mi.name AS material_name,
                   COALESCE(mi.department_id, a.department_id) AS billing_department_id,
                   d.name AS billing_department_name
            FROM workshop_ticket wt
            INNER JOIN activity a ON a.id = wt.activity_id
            INNER JOIN material_item mi ON mi.id = wt.material_item_id
            LEFT JOIN department d ON d.id = COALESCE(mi.department_id, a.department_id)
            WHERE COALESCE(mi.department_id, a.department_id) = :d
              AND COALESCE(a.onboarding_sandbox, false) = false
              AND wt.status IN ({$statusList})
            ORDER BY a.name ASC, wt.title ASC, wt.id ASC
            SQL;

        $rows = $conn->executeQuery(
            $sql,
            ['d' => $departmentId],
            ['d' => ParameterType::STRING],
        )->fetchAllAssociative();

        $items = [];
        $activityIds = [];
        foreach ($rows as $row) {
            $activityId = $row['activity_id'] ?? null;
            if (is_string($activityId) && $activityId !== '') {
                $activityIds[$activityId] = true;
            }
            $est = $row['estimated_cost_chf'] ?? null;
            $items[] = [
                'kind' => 'workshop_open',
                'ticket_id' => $row['ticket_id'],
                'ticket_title' => $row['ticket_title'],
                'ticket_status' => $row['ticket_status'],
                'activity_id' => $activityId,
                'activity_name' => $row['activity_name'],
                'material_item_id' => $row['material_item_id'],
                'material_name' => $row['material_name'],
                'estimated_cost_chf' => $est !== null && $est !== '' ? $est : null,
                'estimated_cost_is_estimate' => true,
                'billing_department_id' => $row['billing_department_id'],
                'billing_department_name' => $row['billing_department_name'],
            ];
        }

        return [
            'workshop_open_count' => count($items),
            'workshop_open_activity_count' => count($activityIds),
            'items' => $items,
        ];
    }

    /**
     * Offene Werkstatt-Tickets einer Aktivität (alle Billing-Depts).
     *
     * @return list<array<string, mixed>>
     */
    public function listForActivity(string $activityId): array
    {
        if ($activityId === '') {
            return [];
        }

        $conn = $this->entityManager->getConnection();
        $statusList = implode(',', array_map(
            static fn (string $s): string => $conn->quote($s),
            self::OPEN_STATUSES,
        ));

        $sql = <<<SQL
            SELECT wt.id AS ticket_id,
                   wt.title AS ticket_title,
                   wt.status AS ticket_status,
                   wt.estimated_cost::text AS estimated_cost_chf,
                   wt.activity_id AS activity_id,
                   a.name AS activity_name,
                   mi.id AS material_item_id,
                   mi.name AS material_name,
                   COALESCE(mi.department_id, a.department_id) AS billing_department_id,
                   d.name AS billing_department_name
            FROM workshop_ticket wt
            INNER JOIN activity a ON a.id = wt.activity_id
            INNER JOIN material_item mi ON mi.id = wt.material_item_id
            LEFT JOIN department d ON d.id = COALESCE(mi.department_id, a.department_id)
            WHERE wt.activity_id = :act
              AND wt.status IN ({$statusList})
            ORDER BY wt.title ASC, wt.id ASC
            SQL;

        $rows = $conn->executeQuery(
            $sql,
            ['act' => $activityId],
            ['act' => ParameterType::STRING],
        )->fetchAllAssociative();

        $items = [];
        foreach ($rows as $row) {
            $est = $row['estimated_cost_chf'] ?? null;
            $items[] = [
                'kind' => 'workshop_open',
                'ticket_id' => $row['ticket_id'],
                'ticket_title' => $row['ticket_title'],
                'ticket_status' => $row['ticket_status'],
                'activity_id' => $row['activity_id'],
                'activity_name' => $row['activity_name'],
                'material_item_id' => $row['material_item_id'],
                'material_name' => $row['material_name'],
                'estimated_cost_chf' => $est !== null && $est !== '' ? $est : null,
                'estimated_cost_is_estimate' => true,
                'billing_department_id' => $row['billing_department_id'],
                'billing_department_name' => $row['billing_department_name'],
            ];
        }

        return $items;
    }

    /**
     * @return array{workshop_open_count: int, workshop_open_activity_count: int}
     */
    public function countsForDepartment(string $departmentId): array
    {
        $data = $this->listForDepartment($departmentId);

        return [
            'workshop_open_count' => $data['workshop_open_count'],
            'workshop_open_activity_count' => $data['workshop_open_activity_count'],
        ];
    }
}
