<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo-Zusage zsfa7a9edabd (Teleskoplader) mit ihrer Bedarfsposition gp6c8eaa51b7 verknüpfen. Position: Teleskoplader, Fahrzeug, Menge 1, ohne weitere Deckung; gewählte Offerte von Maki Landtechnik.';
    }

    public function up(Schema $schema): void
    {
        // Nur, solange unverknüpft und die Position von keiner anderen Zusage gedeckt wird.
        $this->addSql(<<<'SQL'
            UPDATE department_grossanlass_commitment c
            SET procurement_line_id = 'gp6c8eaa51b7',
                item_details = (c.item_details::jsonb || '{"from_line_id":"gp6c8eaa51b7"}'::jsonb)::json
            WHERE c.id = 'zsfa7a9edabd' AND c.procurement_line_id IS NULL
              AND EXISTS (SELECT 1 FROM activity_grossanlass_procurement_line l
                          WHERE l.id = 'gp6c8eaa51b7' AND l.department_id = c.department_id AND l.label = 'Teleskoplader' AND l.quantity = 1)
              AND NOT EXISTS (SELECT 1 FROM department_grossanlass_commitment o
                              WHERE o.procurement_line_id = 'gp6c8eaa51b7' AND o.id <> c.id)
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Datenkorrektur, nicht umkehrbar.
    }
}
