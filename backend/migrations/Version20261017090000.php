<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Materialwunsch: Beschaffungszuständigkeit (department|central|NULL = noch nicht festgelegt). Bestehende Wünsche bleiben NULL; self_organized gilt weiterhin als Ressortbeschaffung.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS procurement_responsibility VARCHAR(12) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS procurement_responsibility');
    }
}
