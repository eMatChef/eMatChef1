<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Formular-Zweck an Runden, Anfragen-Tabelle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity_grossanlass_round ADD COLUMN IF NOT EXISTS form_purpose VARCHAR(32) DEFAULT 'material_wish' NOT NULL");
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_inquiry (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(180) DEFAULT '' NOT NULL,
    place VARCHAR(255) DEFAULT '' NOT NULL,
    category_ids JSON NOT NULL,
    status VARCHAR(20) DEFAULT 'entwurf' NOT NULL,
    tip_wish_id CHAR(12) DEFAULT NULL,
    tip_from VARCHAR(255) DEFAULT NULL,
    thread JSON NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_inquiry_dept ON department_grossanlass_inquiry (department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_inquiry_status ON department_grossanlass_inquiry (status)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_inquiry_tip ON department_grossanlass_inquiry (tip_wish_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_inquiry
        ADD CONSTRAINT fk_ga_inquiry_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_inquiry
        ADD CONSTRAINT fk_ga_inquiry_tip FOREIGN KEY (tip_wish_id) REFERENCES activity_grossanlass_wish_line (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_inquiry');
        $this->addSql('ALTER TABLE activity_grossanlass_round DROP COLUMN IF EXISTS form_purpose');
    }
}
