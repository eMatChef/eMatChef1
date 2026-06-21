<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630120000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'Grossanlass Formular-Builder: round_form, form_field, wish_response, response_value';
    }

    public function up(Schema $schema): void
    {
        if ($this->prepareNewTable($schema, 'activity_grossanlass_round_form')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_round_form (
    id CHARACTER(12) NOT NULL,
    round_id CHARACTER(12) NOT NULL,
    intro_text VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE UNIQUE INDEX uq_grossanlass_round_form_round ON activity_grossanlass_round_form (round_id)');
        }

        if ($this->prepareNewTable($schema, 'activity_grossanlass_round_form_field')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_round_form_field (
    id CHARACTER(12) NOT NULL,
    form_id CHARACTER(12) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    role VARCHAR(10) NOT NULL,
    system_key VARCHAR(32) DEFAULT NULL,
    custom_type VARCHAR(20) DEFAULT NULL,
    label VARCHAR(255) NOT NULL,
    help_text TEXT DEFAULT NULL,
    required BOOLEAN NOT NULL DEFAULT FALSE,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    options_json JSON DEFAULT NULL,
    config_json JSON DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE INDEX idx_grossanlass_form_field_form ON activity_grossanlass_round_form_field (form_id, sort_order)');
        }

        if ($this->prepareNewTable($schema, 'activity_grossanlass_wish_response')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_wish_response (
    id CHARACTER(12) NOT NULL,
    round_id CHARACTER(12) NOT NULL,
    form_id CHARACTER(12) NOT NULL,
    group_id CHARACTER(12) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'requested',
    created_by_user_id CHARACTER(12) NOT NULL,
    updated_by_user_id CHARACTER(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE INDEX idx_grossanlass_wish_response_round ON activity_grossanlass_wish_response (round_id)');
            $this->addSql('CREATE INDEX idx_grossanlass_wish_response_group ON activity_grossanlass_wish_response (group_id)');
        }

        if ($this->prepareNewTable($schema, 'activity_grossanlass_wish_response_value')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_wish_response_value (
    id CHARACTER(12) NOT NULL,
    response_id CHARACTER(12) NOT NULL,
    field_id CHARACTER(12) NOT NULL,
    value_text TEXT DEFAULT NULL,
    value_number NUMERIC(18, 4) DEFAULT NULL,
    value_json JSON DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE UNIQUE INDEX uq_wish_response_value_field ON activity_grossanlass_wish_response_value (response_id, field_id)');
        }

        if (!$schema->getTable('activity_grossanlass_wish_line')->hasColumn('response_id')) {
            $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN response_id CHARACTER(12) DEFAULT NULL');
            $this->addSql('CREATE UNIQUE INDEX uq_grossanlass_wish_line_response ON activity_grossanlass_wish_line (response_id)');
        }

        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_round_form_round') THEN
        ALTER TABLE activity_grossanlass_round_form ADD CONSTRAINT fk_grossanlass_round_form_round
            FOREIGN KEY (round_id) REFERENCES activity_grossanlass_round (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_form_field_form') THEN
        ALTER TABLE activity_grossanlass_round_form_field ADD CONSTRAINT fk_grossanlass_form_field_form
            FOREIGN KEY (form_id) REFERENCES activity_grossanlass_round_form (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_response_round') THEN
        ALTER TABLE activity_grossanlass_wish_response ADD CONSTRAINT fk_grossanlass_wish_response_round
            FOREIGN KEY (round_id) REFERENCES activity_grossanlass_round (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_response_form') THEN
        ALTER TABLE activity_grossanlass_wish_response ADD CONSTRAINT fk_grossanlass_wish_response_form
            FOREIGN KEY (form_id) REFERENCES activity_grossanlass_round_form (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_response_group') THEN
        ALTER TABLE activity_grossanlass_wish_response ADD CONSTRAINT fk_grossanlass_wish_response_group
            FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_response_created_by') THEN
        ALTER TABLE activity_grossanlass_wish_response ADD CONSTRAINT fk_grossanlass_wish_response_created_by
            FOREIGN KEY (created_by_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_response_updated_by') THEN
        ALTER TABLE activity_grossanlass_wish_response ADD CONSTRAINT fk_grossanlass_wish_response_updated_by
            FOREIGN KEY (updated_by_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_response_value_response') THEN
        ALTER TABLE activity_grossanlass_wish_response_value ADD CONSTRAINT fk_grossanlass_wish_response_value_response
            FOREIGN KEY (response_id) REFERENCES activity_grossanlass_wish_response (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_response_value_field') THEN
        ALTER TABLE activity_grossanlass_wish_response_value ADD CONSTRAINT fk_grossanlass_wish_response_value_field
            FOREIGN KEY (field_id) REFERENCES activity_grossanlass_round_form_field (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_line_response') THEN
        ALTER TABLE activity_grossanlass_wish_line ADD CONSTRAINT fk_grossanlass_wish_line_response
            FOREIGN KEY (response_id) REFERENCES activity_grossanlass_wish_response (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_line_response');
        $this->addSql('DROP INDEX IF EXISTS uq_grossanlass_wish_line_response');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS response_id');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_response_value DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_response_value_field');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_response_value DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_response_value_response');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_wish_response_value');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_response DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_response_updated_by');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_response DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_response_created_by');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_response DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_response_group');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_response DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_response_form');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_response DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_response_round');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_wish_response');
        $this->addSql('ALTER TABLE activity_grossanlass_round_form_field DROP CONSTRAINT IF EXISTS fk_grossanlass_form_field_form');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_round_form_field');
        $this->addSql('ALTER TABLE activity_grossanlass_round_form DROP CONSTRAINT IF EXISTS fk_grossanlass_round_form_round');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_round_form');
    }
}
