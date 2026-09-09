<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Teilnehmer-Abteilungen (planned → pending → accepted/rejected)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_participant (
    id CHARACTER(12) NOT NULL,
    host_department_id CHARACTER(12) NOT NULL,
    guest_department_id CHARACTER(12) NOT NULL,
    status VARCHAR(20) DEFAULT 'planned' NOT NULL,
    guest_group_id CHARACTER(12) DEFAULT NULL,
    guest_activity_id CHARACTER(12) DEFAULT NULL,
    invited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    decided_by_user_id CHARACTER(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_participant_host_guest ON department_grossanlass_participant (host_department_id, guest_department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_participant_guest ON department_grossanlass_participant (guest_department_id)');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_host');
        $this->addSql('ALTER TABLE department_grossanlass_participant ADD CONSTRAINT fk_ga_participant_host FOREIGN KEY (host_department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_guest');
        $this->addSql('ALTER TABLE department_grossanlass_participant ADD CONSTRAINT fk_ga_participant_guest FOREIGN KEY (guest_department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_group');
        $this->addSql('ALTER TABLE department_grossanlass_participant ADD CONSTRAINT fk_ga_participant_group FOREIGN KEY (guest_group_id) REFERENCES "group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_activity');
        $this->addSql('ALTER TABLE department_grossanlass_participant ADD CONSTRAINT fk_ga_participant_activity FOREIGN KEY (guest_activity_id) REFERENCES activity (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_host');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_guest');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_group');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_activity');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_participant');
    }
}
