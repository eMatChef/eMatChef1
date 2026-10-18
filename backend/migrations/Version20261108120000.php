<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo-Grossanlass: Leih-Charge Dachdecker Meier an Bitumen-Bedarfsposition verknüpfen.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE department_grossanlass_commitment c
            SET procurement_line_id = l.id,
                item_details = (COALESCE(c.item_details::jsonb, '{}'::jsonb) || jsonb_build_object(
                    'from_line_id', l.id,
                    'inbound_mode', 'pickup',
                    'inbound_status', 'expected'
                ))::json
            FROM activity_grossanlass_procurement_line l
            WHERE c.origin = 'loan'
              AND c.source = 'Dachdecker Meier'
              AND c.procurement_line_id IS NULL
              AND l.department_id = c.department_id
              AND l.label = 'Bitumenschindeln anthrazit'
              AND NOT EXISTS (
                  SELECT 1 FROM department_grossanlass_commitment o
                  WHERE o.procurement_line_id = l.id AND o.id <> c.id AND o.origin = 'loan'
              )
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Datenkorrektur, nicht umkehrbar.
    }
}
