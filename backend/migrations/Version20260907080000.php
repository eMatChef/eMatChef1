<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Offerte: Liefertermin und Lieferzeit in Tagen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote ADD COLUMN IF NOT EXISTS delivery_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote ADD COLUMN IF NOT EXISTS lead_days INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP COLUMN IF EXISTS delivery_at');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP COLUMN IF EXISTS lead_days');
    }
}
