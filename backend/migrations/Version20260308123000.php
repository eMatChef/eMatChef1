<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add structured storage location fields (rack/slot) to material_batch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_batch ADD COLUMN location_rack VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_batch ADD COLUMN location_slot VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_batch DROP COLUMN location_slot');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN location_rack');
    }
}

