<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Gmail-Konto, Mailvorlagen, Zusagen, Anfrage-Gmail-IDs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS gmail_draft_id VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS gmail_thread_id VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS gmail_message_id VARCHAR(128) DEFAULT NULL');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_gmail_account (
    department_id CHAR(12) NOT NULL,
    email VARCHAR(180) NOT NULL,
    refresh_token_enc TEXT NOT NULL,
    access_token_enc TEXT DEFAULT NULL,
    access_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    label_map JSON NOT NULL,
    connected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    connected_by_user_id CHAR(12) DEFAULT NULL,
    PRIMARY KEY (department_id)
)
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_gmail_account
        ADD CONSTRAINT fk_ga_gmail_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_mail_template (
    department_id CHAR(12) NOT NULL,
    kind VARCHAR(32) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (department_id, kind)
)
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_mail_template
        ADD CONSTRAINT fk_ga_mail_tpl_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_commitment (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    inquiry_id CHAR(12) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    family VARCHAR(16) DEFAULT 'material' NOT NULL,
    origin VARCHAR(16) DEFAULT 'loan' NOT NULL,
    source VARCHAR(255) NOT NULL,
    plate VARCHAR(32) DEFAULT NULL,
    barcode VARCHAR(64) DEFAULT NULL,
    category_id VARCHAR(64) DEFAULT NULL,
    released BOOLEAN DEFAULT FALSE NOT NULL,
    present_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    present_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    handover_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    handover_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    return_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    return_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    wish_label VARCHAR(255) DEFAULT NULL,
    wish_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    wish_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    services JSON NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_commitment_dept ON department_grossanlass_commitment (department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_commitment_inquiry ON department_grossanlass_commitment (inquiry_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_commitment
        ADD CONSTRAINT fk_ga_commitment_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_commitment
        ADD CONSTRAINT fk_ga_commitment_inquiry FOREIGN KEY (inquiry_id) REFERENCES department_grossanlass_inquiry (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_commitment');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_mail_template');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_gmail_account');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS gmail_draft_id');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS gmail_thread_id');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS gmail_message_id');
    }
}
