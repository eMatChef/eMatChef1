<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 2 – reservation_mode entfernen (end-to-end).
 *
 * Das Feld war wirkungslos (nur gespeichert/angezeigt); Verhalten leitet sich
 * aus Material-Typ + Zusammensetzung ab. Spalten in material_item und
 * material_template werden gedroppt.
 */
final class Version20260529130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop reservation_mode from material_item and material_template.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS reservation_mode');
        $this->addSql('ALTER TABLE material_template DROP COLUMN IF EXISTS reservation_mode');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN reservation_mode VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_template ADD COLUMN reservation_mode VARCHAR(20) DEFAULT NULL');
    }
}
