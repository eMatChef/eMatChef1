<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'material_item.rental_calc_params JSON for Vermiet-Amortisationsrechner';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD rental_calc_params JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP rental_calc_params');
    }
}
