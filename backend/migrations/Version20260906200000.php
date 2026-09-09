<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Bestellung: Liefertermin für Wareneingang';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS delivery_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order DROP COLUMN IF EXISTS delivery_at');
    }
}
