<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass User-Karten: darf fahren und Druckstatus';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_user_card (
    department_id CHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    may_drive BOOLEAN DEFAULT false NOT NULL,
    card_printed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    public_code VARCHAR(32) NOT NULL,
    PRIMARY KEY (department_id, user_id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_user_card_code ON department_grossanlass_user_card (public_code)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_user_card
        ADD CONSTRAINT fk_ga_ucard_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_user_card
        ADD CONSTRAINT fk_ga_ucard_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_user_card');
    }
}
