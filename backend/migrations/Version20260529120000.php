<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 1 – Entwurfs-Flag für Kombos.
 *
 * Fügt material_item.combo_status (draft | ready) hinzu. Einzelartikel und
 * bestehende Kombos starten als 'ready'; neue Kombos werden im Code als
 * 'draft' angelegt und im Detail-Tab fertiggestellt.
 */
final class Version20260529120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add combo_status (draft|ready) to material_item.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE material_item ADD COLUMN combo_status VARCHAR(20) NOT NULL DEFAULT 'ready'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS combo_status');
    }
}
