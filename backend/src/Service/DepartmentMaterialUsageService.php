<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class DepartmentMaterialUsageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return list<array{material_item_id: string, material_name: string, move_count: int, total_quantity: int}>
     */
    public function topMaterials(
        string $departmentId,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        int $limit = 20,
    ): array {
        $conn = $this->entityManager->getConnection();
        $params = ['deptId' => $departmentId];
        $dateFilter = '';

        if ($from !== null) {
            $dateFilter .= ' AND ah.created_at >= :fromDate';
            $params['fromDate'] = $from->format('Y-m-d H:i:s');
        }
        if ($to !== null) {
            $dateFilter .= ' AND ah.created_at <= :toDate';
            $params['toDate'] = $to->format('Y-m-d H:i:s');
        }

        $sql = <<<SQL
SELECT
    ah.changes->>'material_item_id' AS material_item_id,
    MAX(ah.changes->>'material_name') AS material_name,
    COUNT(*)::int AS move_count,
    COALESCE(SUM((ah.changes->>'quantity')::int), 0)::int AS total_quantity
FROM activity_history ah
INNER JOIN activity a ON a.id = ah.activity_id
WHERE a.department_id = :deptId
  AND ah.action IN ('pack_move', 'pack_moveback')
  AND ah.changes->>'material_item_id' IS NOT NULL
  {$dateFilter}
GROUP BY ah.changes->>'material_item_id'
ORDER BY move_count DESC, total_quantity DESC
LIMIT :lim
SQL;

        $params['lim'] = max(1, min($limit, 100));

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        return array_map(static fn (array $row) => [
            'material_item_id' => (string) ($row['material_item_id'] ?? ''),
            'material_name' => (string) ($row['material_name'] ?? ''),
            'move_count' => (int) ($row['move_count'] ?? 0),
            'total_quantity' => (int) ($row['total_quantity'] ?? 0),
        ], $rows);
    }
}
