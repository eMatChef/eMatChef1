<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701120000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'Grossanlass Beschaffung Phase 5: procurement_line + wish junction';
    }

    public function up(Schema $schema): void
    {
        if ($this->prepareNewTable($schema, 'activity_grossanlass_procurement_line')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_procurement_line (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    group_id CHARACTER(12) NOT NULL,
    wish_kind VARCHAR(20) NOT NULL,
    label VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    location VARCHAR(255) NOT NULL,
    notes TEXT DEFAULT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'bedarf',
    created_by_user_id CHARACTER(12) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE INDEX idx_grossanlass_procurement_dept ON activity_grossanlass_procurement_line (department_id)');
            $this->addSql('CREATE INDEX idx_grossanlass_procurement_group ON activity_grossanlass_procurement_line (group_id)');
            $this->addSql('CREATE INDEX idx_grossanlass_procurement_status ON activity_grossanlass_procurement_line (status)');
        }

        if ($this->prepareNewTable($schema, 'activity_grossanlass_procurement_line_wish')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_procurement_line_wish (
    procurement_line_id CHARACTER(12) NOT NULL,
    wish_line_id CHARACTER(12) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(procurement_line_id, wish_line_id)
)
SQL);
            $this->addSql('CREATE UNIQUE INDEX uq_grossanlass_procurement_wish ON activity_grossanlass_procurement_line_wish (wish_line_id)');
        }

        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_procurement_dept') THEN
        ALTER TABLE activity_grossanlass_procurement_line ADD CONSTRAINT fk_grossanlass_procurement_dept
            FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_procurement_group') THEN
        ALTER TABLE activity_grossanlass_procurement_line ADD CONSTRAINT fk_grossanlass_procurement_group
            FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_procurement_created_by') THEN
        ALTER TABLE activity_grossanlass_procurement_line ADD CONSTRAINT fk_grossanlass_procurement_created_by
            FOREIGN KEY (created_by_user_id) REFERENCES "user" (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_procurement_wish_line') THEN
        ALTER TABLE activity_grossanlass_procurement_line_wish ADD CONSTRAINT fk_grossanlass_procurement_wish_line
            FOREIGN KEY (procurement_line_id) REFERENCES activity_grossanlass_procurement_line (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_procurement_wish_wish') THEN
        ALTER TABLE activity_grossanlass_procurement_line_wish ADD CONSTRAINT fk_grossanlass_procurement_wish_wish
            FOREIGN KEY (wish_line_id) REFERENCES activity_grossanlass_wish_line (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line_wish DROP CONSTRAINT IF EXISTS fk_grossanlass_procurement_wish_wish');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line_wish DROP CONSTRAINT IF EXISTS fk_grossanlass_procurement_wish_line');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_procurement_line_wish');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP CONSTRAINT IF EXISTS fk_grossanlass_procurement_created_by');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP CONSTRAINT IF EXISTS fk_grossanlass_procurement_group');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP CONSTRAINT IF EXISTS fk_grossanlass_procurement_dept');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_procurement_line');
    }
}
