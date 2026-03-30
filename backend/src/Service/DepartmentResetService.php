<?php

namespace App\Service;

use App\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Setzt alle Daten eines Departments zurück (für Dev/Test).
 * Löscht: Aktivitäten, Materialien, Adressen, Kategorien, Gruppen, etc.
 * Behält: Department, Memberships, Organisation
 */
class DepartmentResetService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function resetDepartment(string $departmentId): array
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            throw new \InvalidArgumentException('Department nicht gefunden');
        }

        $conn = $this->entityManager->getConnection();
        $deleted = [];

        $conn->beginTransaction();
        try {
            // Reihenfolge beachten: zuerst abhängige Tabellen, dann Haupttabellen

            // 1. Activity-abhängige Tabellen (CASCADE würde greifen, aber explizit für Zählung)
            $deleted['activity_item'] = $conn->executeStatement(
                'DELETE FROM activity_item WHERE activity_id IN (SELECT id FROM activity WHERE department_id = ?)',
                [$departmentId]
            );
            $deleted['activity_return_item'] = $conn->executeStatement(
                'DELETE FROM activity_return_item WHERE activity_id IN (SELECT id FROM activity WHERE department_id = ?)',
                [$departmentId]
            );
            $deleted['activity_pack_item'] = $conn->executeStatement(
                'DELETE FROM activity_pack_item WHERE activity_id IN (SELECT id FROM activity WHERE department_id = ?)',
                [$departmentId]
            );
            $deleted['activity_pack_container_item'] = $conn->executeStatement(
                'DELETE FROM activity_pack_container_item WHERE pack_container_id IN (SELECT id FROM activity_pack_container WHERE activity_id IN (SELECT id FROM activity WHERE department_id = ?))',
                [$departmentId]
            );
            $deleted['activity_pack_container'] = $conn->executeStatement(
                'DELETE FROM activity_pack_container WHERE activity_id IN (SELECT id FROM activity WHERE department_id = ?)',
                [$departmentId]
            );
            $deleted['activity_issue_report'] = $conn->executeStatement(
                'DELETE FROM activity_issue_report WHERE activity_id IN (SELECT id FROM activity WHERE department_id = ?)',
                [$departmentId]
            );
            $deleted['activity_history'] = $conn->executeStatement(
                'DELETE FROM activity_history WHERE activity_id IN (SELECT id FROM activity WHERE department_id = ?)',
                [$departmentId]
            );

            // 2. Activities
            $deleted['activity'] = $conn->executeStatement('DELETE FROM activity WHERE department_id = ?', [$departmentId]);

            // 3. Workshop (referenziert material_item und activity)
            $deleted['workshop_ticket_history'] = $conn->executeStatement(
                'DELETE FROM workshop_ticket_history WHERE workshop_ticket_id IN (SELECT id FROM workshop_ticket WHERE department_id = ?)',
                [$departmentId]
            );
            $deleted['workshop_ticket'] = $conn->executeStatement('DELETE FROM workshop_ticket WHERE department_id = ?', [$departmentId]);

            // 4. BatchStorageAllocation (referenziert material_batch)
            $deleted['batch_storage_allocation'] = $conn->executeStatement(
                'DELETE FROM batch_storage_allocation WHERE department_id = ?',
                [$departmentId]
            );

            // 5. MaterialBatch (referenziert material_item)
            $deleted['material_batch'] = $conn->executeStatement(
                'DELETE FROM material_batch WHERE material_item_id IN (SELECT id FROM material_item WHERE department_id = ?)',
                [$departmentId]
            );

            // 6. MaterialHistory
            $deleted['material_history'] = $conn->executeStatement(
                'DELETE FROM material_history WHERE material_item_id IN (SELECT id FROM material_item WHERE department_id = ?)',
                [$departmentId]
            );

            // 6b. MaterialComboComponent (referenziert material_item)
            $deleted['material_combo_component'] = $conn->executeStatement(
                'DELETE FROM material_combo_component WHERE parent_material_id IN (SELECT id FROM material_item WHERE department_id = ?) OR component_material_id IN (SELECT id FROM material_item WHERE department_id = ?)',
                [$departmentId, $departmentId]
            );

            // 7. MaterialItem
            $deleted['material_item'] = $conn->executeStatement('DELETE FROM material_item WHERE department_id = ?', [$departmentId]);

            // 8. StorageSlot (referenziert storage_rack)
            $deleted['storage_slot'] = $conn->executeStatement(
                'DELETE FROM storage_slot WHERE rack_id IN (SELECT id FROM storage_rack WHERE department_id = ?)',
                [$departmentId]
            );

            // 9. StorageRack (referenziert address – muss VOR address gelöscht werden)
            $deleted['storage_rack'] = $conn->executeStatement('DELETE FROM storage_rack WHERE department_id = ?', [$departmentId]);

            // 10. Address (wird von storage_rack referenziert)
            $deleted['address'] = $conn->executeStatement('DELETE FROM address WHERE department_id = ?', [$departmentId]);

            // 11. Category
            $deleted['category'] = $conn->executeStatement('DELETE FROM category WHERE department_id = ?', [$departmentId]);

            // 12. GroupMembership
            $deleted['group_membership'] = $conn->executeStatement(
                'DELETE FROM group_membership WHERE group_id IN (SELECT id FROM "group" WHERE department_id = ?)',
                [$departmentId]
            );

            // 13. Group
            $deleted['group'] = $conn->executeStatement('DELETE FROM "group" WHERE department_id = ?', [$departmentId]);

            // 14. MaterialTemplate (department-eigene)
            $deleted['material_template_component'] = $conn->executeStatement(
                'DELETE FROM material_template_component WHERE template_id IN (SELECT id FROM material_template WHERE department_id = ?)',
                [$departmentId]
            );
            $deleted['material_template'] = $conn->executeStatement(
                'DELETE FROM material_template WHERE department_id = ?',
                [$departmentId]
            );

            // 15a. Öffentliche QR-Kontaktnachrichten (In-App)
            $deleted['public_found_item_message'] = $conn->executeStatement(
                'DELETE FROM public_found_item_message WHERE department_id = ?',
                [$departmentId]
            );

            // 15. DepartmentSetting
            $deleted['department_setting'] = $conn->executeStatement(
                'DELETE FROM department_setting WHERE department_id = ?',
                [$departmentId]
            );

            // 16. AuditEvent
            $deleted['audit_event'] = $conn->executeStatement(
                'DELETE FROM audit_event WHERE department_id = ?',
                [$departmentId]
            );

            // 17. JoinRequest
            $deleted['join_request'] = $conn->executeStatement(
                'DELETE FROM join_request WHERE department_id = ?',
                [$departmentId]
            );

            $conn->commit();
            return $deleted;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw new \RuntimeException('DB-Reset fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
    }
}
