<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Onboarding Hybrid-Sandbox: Flags + Registry-State.
 * Spec: docs/onboarding/sandboxtoolactivities/
 */
final class Version20260816180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add onboarding_sandbox flags and onboarding_sandbox_state registry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS onboarding_sandbox BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE address ADD COLUMN IF NOT EXISTS onboarding_sandbox BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE material_item ADD COLUMN IF NOT EXISTS onboarding_sandbox BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE department_vehicle ADD COLUMN IF NOT EXISTS onboarding_sandbox BOOLEAN NOT NULL DEFAULT false');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_onboarding_sandbox ON activity (department_id, onboarding_sandbox)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_address_onboarding_sandbox ON address (department_id, onboarding_sandbox)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_material_onboarding_sandbox ON material_item (department_id, onboarding_sandbox)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_vehicle_onboarding_sandbox ON department_vehicle (department_id, onboarding_sandbox)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS onboarding_sandbox_state (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    activity_id CHAR(12) DEFAULT NULL,
    camp_id CHAR(12) DEFAULT NULL,
    venue_id CHAR(12) DEFAULT NULL,
    last_for_tour VARCHAR(64) DEFAULT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_onboarding_sandbox_dept_user ON onboarding_sandbox_state (department_id, user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_onboarding_sandbox_state_dept ON onboarding_sandbox_state (department_id)');

        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE onboarding_sandbox_state
    ADD CONSTRAINT fk_onboarding_sandbox_department
    FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE onboarding_sandbox_state
    ADD CONSTRAINT fk_onboarding_sandbox_user
    FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE onboarding_sandbox_state
    ADD CONSTRAINT fk_onboarding_sandbox_activity
    FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE onboarding_sandbox_state
    ADD CONSTRAINT fk_onboarding_sandbox_camp
    FOREIGN KEY (camp_id) REFERENCES activity (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE onboarding_sandbox_state
    ADD CONSTRAINT fk_onboarding_sandbox_venue
    FOREIGN KEY (venue_id) REFERENCES address (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS onboarding_sandbox_state');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS onboarding_sandbox');
        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS onboarding_sandbox');
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS onboarding_sandbox');
        $this->addSql('ALTER TABLE department_vehicle DROP COLUMN IF EXISTS onboarding_sandbox');
    }
}
