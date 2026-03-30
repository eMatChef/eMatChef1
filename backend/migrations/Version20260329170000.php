<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vorlagen-Komponenten: „1 Stück = serialisiert“ war für Zubehör (Winkel, Zeltstöcke, …) falsch.
 * Außenzelt / Innenzelt / Zelthaut bleiben serialisiert (eine SN pro Haut).
 */
final class Version20260329170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Template components: set tracking=bulk for hardware; keep serialized for tent fabrics (aussenzelt, innenzelt, Zelthaut).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE material_template_component
            SET tracking = 'bulk'
            WHERE tracking = 'serialized'
              AND LOWER(component_type) NOT IN (
                'aussenzelt',
                'innenzelt',
                'zelthaut'
              )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->write('Irreversible: previous serialized/bulk mix cannot be restored automatically.');
    }
}
