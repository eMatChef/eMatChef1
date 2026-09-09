<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass Beschaffung: freie Kategorien/Subkategorien für Bedarfspositionen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_grossanlass_procurement_category (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    parent_id CHAR(12) DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gpc_dept ON activity_grossanlass_procurement_category (department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gpc_parent ON activity_grossanlass_procurement_category (parent_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE activity_grossanlass_procurement_category
        ADD CONSTRAINT fk_gpc_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE activity_grossanlass_procurement_category
        ADD CONSTRAINT fk_gpc_parent FOREIGN KEY (parent_id) REFERENCES activity_grossanlass_procurement_category (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);

        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS category_id CHAR(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gpl_category ON activity_grossanlass_procurement_line (category_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE activity_grossanlass_procurement_line
        ADD CONSTRAINT fk_gpl_category FOREIGN KEY (category_id) REFERENCES activity_grossanlass_procurement_category (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP CONSTRAINT IF EXISTS fk_gpl_category');
        $this->addSql('DROP INDEX IF EXISTS idx_gpl_category');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS category_id');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_procurement_category');
    }
}
