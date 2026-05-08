<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428210500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add material_item.rental_calc_params JSON column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE material_item ADD COLUMN IF NOT EXISTS rental_calc_params JSON DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE material_item DROP COLUMN IF EXISTS rental_calc_params");
    }
}

