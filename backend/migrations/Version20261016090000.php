<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Materialwunsch: optionale Beschaffungskategorie (activity_grossanlass_wish_line.category_id). Bestehende Wünsche bleiben ohne Kategorie.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS category_id CHARACTER(12) NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD CONSTRAINT fk_grossanlass_wish_category FOREIGN KEY (category_id) REFERENCES activity_grossanlass_procurement_category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_grossanlass_wish_category ON activity_grossanlass_wish_line (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_grossanlass_wish_category');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_category');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS category_id');
    }
}
