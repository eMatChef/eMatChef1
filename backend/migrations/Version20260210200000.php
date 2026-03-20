<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete material_item.serial_number column (serials are tracked on material_batch)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN serial_number');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN serial_number VARCHAR(100) DEFAULT NULL');
    }
}

