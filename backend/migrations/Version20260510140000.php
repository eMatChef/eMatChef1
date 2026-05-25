<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nachbuchung (is_replenishment): activity_pack_item.quantity_ordered wurde erhöht,
 * quantity_issued/quantity_packed aber nicht — Packliste zeigte z. B. «1/22» statt «1/37».
 */
final class Version20260510140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align pack issued/packed with ordered for consumables after replenishment activity lines';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
UPDATE activity_pack_item pi
SET quantity_issued = pi.quantity_ordered,
    quantity_packed = CASE
        WHEN pi.quantity_packed < pi.quantity_ordered THEN pi.quantity_ordered
        ELSE pi.quantity_packed
    END
FROM material_item m, activity a
WHERE pi.material_item_id = m.id
  AND pi.activity_id = a.id
  AND m.is_consumable = true
  AND a.status IN ('issued', 'returned', 'completed')
  AND EXISTS (
    SELECT 1 FROM activity_item ai
    WHERE ai.activity_id = pi.activity_id
      AND ai.material_item_id = pi.material_item_id
      AND ai.is_replenishment = true
  )
  AND pi.quantity_ordered > pi.quantity_issued
SQL);
    }

    public function down(Schema $schema): void
    {
        // Kein sinnvolles Revert ohne Snapshot
    }
}
