<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy material_batch location_rack/location_slot columns (no fallback mode)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS location_rack');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS location_slot');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_batch ADD location_rack VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_batch ADD location_slot VARCHAR(80) DEFAULT NULL');
    }
}

