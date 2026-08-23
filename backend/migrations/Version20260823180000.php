<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass Beschaffung: Budget-Rahmen gesamt und pro Kategorie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_grossanlass_procurement_finance (
    department_id CHAR(12) NOT NULL,
    rahmen_chf NUMERIC(12, 2) DEFAULT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (department_id)
)
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE activity_grossanlass_procurement_finance
        ADD CONSTRAINT fk_gpf_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_category ADD COLUMN IF NOT EXISTS rahmen_chf NUMERIC(12, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_category DROP COLUMN IF EXISTS rahmen_chf');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_procurement_finance');
    }
}
