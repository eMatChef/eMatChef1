<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\Department;
use App\Service\DepartmentResetService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Dev-only: Demo-Grossanlass-Department inkl. aller Fachdaten entfernen.
 */
final class DemoGrossanlassWipeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentResetService $departmentReset,
    ) {
    }

    /**
     * @return array{department_id: string, deleted: array<string, int>}
     */
    public function wipeByName(string $name = DemoGrossanlassSeedService::DEPARTMENT_NAME): array
    {
        $department = $this->entityManager->getRepository(Department::class)->findOneBy(['name' => $name]);
        if (!$department instanceof Department) {
            throw new \InvalidArgumentException(sprintf('Department «%s» nicht gefunden.', $name));
        }

        return $this->wipeDepartment($department->getId());
    }

    /**
     * @return array{department_id: string, deleted: array<string, int>}
     */
    public function wipeDepartment(string $departmentId): array
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            throw new \InvalidArgumentException('Department nicht gefunden: ' . $departmentId);
        }
        if (!$department->isGrossanlass()) {
            throw new \InvalidArgumentException(
                sprintf('Department «%s» ist kein Grossanlass.', $department->getName()),
            );
        }

        $departmentName = $department->getName();

        $conn = $this->entityManager->getConnection();
        $deleted = [];

        try {
            $deleted = array_merge($deleted, $this->deleteGrossanlassDomain($conn, $departmentId));
            $deleted = array_merge($deleted, $this->departmentReset->resetDepartment($departmentId));

            $deleted['department_calendar_period'] = $conn->executeStatement(
                'DELETE FROM department_calendar_period WHERE department_id = ?',
                [$departmentId],
            );
            $deleted['department_grossanlass_config'] = $conn->executeStatement(
                'DELETE FROM department_grossanlass_config WHERE department_id = ?',
                [$departmentId],
            );
            $deleted['inbox_message'] = ($deleted['inbox_message'] ?? 0) + $conn->executeStatement(
                'DELETE FROM inbox_message WHERE department_id = ?',
                [$departmentId],
            );
            $deleted['membership'] = $conn->executeStatement(
                'DELETE FROM membership WHERE department_id = ?',
                [$departmentId],
            );
            $deleted['user_last_used_department'] = $conn->executeStatement(
                'UPDATE "user" SET last_used_department_id = NULL WHERE last_used_department_id = ?',
                [$departmentId],
            );
            $deleted['department'] = $conn->executeStatement(
                'DELETE FROM department WHERE id = ?',
                [$departmentId],
            );

            $this->entityManager->clear();

            return [
                'department_id' => $departmentId,
                'department_name' => $departmentName,
                'deleted' => $deleted,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Demo-Grossanlass-Wipe fehlgeschlagen: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function deleteGrossanlassDomain(Connection $conn, string $departmentId): array
    {
        $deleted = [];
        $params = [$departmentId];

        $deleted['department_grossanlass_pack_line'] = $conn->executeStatement(
            'DELETE FROM department_grossanlass_pack_line WHERE pack_id IN (
                SELECT p.id FROM department_grossanlass_pack p
                INNER JOIN department_grossanlass_einsatz e ON e.id = p.einsatz_id
                WHERE e.department_id = ?
            )',
            $params,
        );
        $deleted['department_grossanlass_pack'] = $conn->executeStatement(
            'DELETE FROM department_grossanlass_pack WHERE einsatz_id IN (
                SELECT id FROM department_grossanlass_einsatz WHERE department_id = ?
            )',
            $params,
        );
        $deleted['department_grossanlass_einsatz'] = $conn->executeStatement(
            'DELETE FROM department_grossanlass_einsatz WHERE department_id = ?',
            $params,
        );

        $roundScope = 'SELECT id FROM activity_grossanlass_round WHERE activity_id IN (
            SELECT id FROM activity WHERE department_id = ?
        )';

        $deleted['activity_grossanlass_procurement_line_wish'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_procurement_line_wish WHERE procurement_line_id IN (
                SELECT id FROM activity_grossanlass_procurement_line WHERE department_id = ?
            ) OR wish_line_id IN (
                SELECT w.id FROM activity_grossanlass_wish_line w
                WHERE w.round_id IN (' . $roundScope . ')
            )',
            [$departmentId, $departmentId],
        );
        $deleted['activity_grossanlass_wish_line'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_wish_line WHERE round_id IN (' . $roundScope . ')',
            [$departmentId],
        );
        $deleted['activity_grossanlass_wish_response_value'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_wish_response_value WHERE response_id IN (
                SELECT id FROM activity_grossanlass_wish_response WHERE round_id IN (' . $roundScope . ')
            )',
            [$departmentId],
        );
        $deleted['activity_grossanlass_wish_response'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_wish_response WHERE round_id IN (' . $roundScope . ')',
            [$departmentId],
        );
        $deleted['activity_grossanlass_procurement_quote'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_procurement_quote WHERE procurement_line_id IN (
                SELECT id FROM activity_grossanlass_procurement_line WHERE department_id = ?
            )',
            $params,
        );
        $deleted['activity_grossanlass_procurement_order'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_procurement_order WHERE procurement_line_id IN (
                SELECT id FROM activity_grossanlass_procurement_line WHERE department_id = ?
            )',
            $params,
        );
        $deleted['activity_grossanlass_procurement_line'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_procurement_line WHERE department_id = ?',
            $params,
        );
        $deleted['activity_grossanlass_procurement_finance'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_procurement_finance WHERE department_id = ?',
            $params,
        );
        $deleted['activity_grossanlass_procurement_category'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_procurement_category WHERE department_id = ?',
            $params,
        );

        $deleted['activity_grossanlass_round_form_field'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_round_form_field WHERE form_id IN (
                SELECT f.id FROM activity_grossanlass_round_form f
                WHERE f.round_id IN (' . $roundScope . ')
            )',
            [$departmentId],
        );
        $deleted['activity_grossanlass_round_form'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_round_form WHERE round_id IN (' . $roundScope . ')',
            [$departmentId],
        );
        $deleted['activity_grossanlass_round'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_round WHERE activity_id IN (
                SELECT id FROM activity WHERE department_id = ?
            )',
            $params,
        );

        foreach ([
            'department_grossanlass_task',
            'department_grossanlass_user_card',
            'department_grossanlass_place',
            'department_grossanlass_map',
            'department_grossanlass_inquiry',
            'department_grossanlass_commitment',
            'department_grossanlass_cost',
            'department_grossanlass_budget',
            'department_grossanlass_workshop_case',
            'department_grossanlass_gmail_unmatched',
            'department_grossanlass_gmail_account',
            'department_grossanlass_mail_template',
        ] as $table) {
            $deleted[$table] = $conn->executeStatement(
                "DELETE FROM {$table} WHERE department_id = ?",
                $params,
            );
        }

        $deleted['department_grossanlass_participant'] = $conn->executeStatement(
            'DELETE FROM department_grossanlass_participant WHERE host_department_id = ? OR guest_department_id = ?',
            [$departmentId, $departmentId],
        );
        $deleted['department_grossanlass_guest_share'] = $conn->executeStatement(
            'DELETE FROM department_grossanlass_guest_share WHERE host_department_id = ? OR guest_department_id = ?',
            [$departmentId, $departmentId],
        );
        $deleted['department_grossanlass_unterlager'] = $conn->executeStatement(
            'DELETE FROM department_grossanlass_unterlager WHERE host_department_id = ?',
            $params,
        );

        $deleted['activity_grossanlass_config'] = $conn->executeStatement(
            'DELETE FROM activity_grossanlass_config WHERE activity_id IN (
                SELECT id FROM activity WHERE department_id = ?
            )',
            $params,
        );

        return $deleted;
    }
}
