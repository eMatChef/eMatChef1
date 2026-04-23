<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Referenz-Einkaufspreis pro Stück (Buchhaltung/Übersicht), Pflicht wenn Verbrauchsmaterial oder Esswaren.
 */
final class Version20260404090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'material_item.reference_purchase_unit_chf (Referenz-EK/Stk.)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN IF NOT EXISTS reference_purchase_unit_chf NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS reference_purchase_unit_chf');
    }
}
