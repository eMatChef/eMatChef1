<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004191140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow external structure identities to map to an organisation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_structure_identity ADD organisation_id CHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE external_structure_identity DROP CONSTRAINT chk_external_structure_identity_target');
        $this->addSql(<<<'SQL'
ALTER TABLE external_structure_identity
    ADD CONSTRAINT chk_external_structure_identity_target CHECK (
        (organisation_id IS NOT NULL AND department_id IS NULL AND group_id IS NULL)
        OR (organisation_id IS NULL AND department_id IS NOT NULL AND group_id IS NULL)
        OR (organisation_id IS NULL AND department_id IS NULL AND group_id IS NOT NULL)
    )
SQL);
        $this->addSql('ALTER TABLE external_structure_identity ADD CONSTRAINT fk_external_structure_organisation FOREIGN KEY (organisation_id) REFERENCES organisation (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_external_structure_organisation ON external_structure_identity (organisation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM external_structure_identity
        WHERE organisation_id IS NOT NULL
    ) THEN
        RAISE EXCEPTION 'Cannot roll back organisation mappings without losing data';
    END IF;
END
$$
SQL);
        $this->addSql('ALTER TABLE external_structure_identity DROP CONSTRAINT chk_external_structure_identity_target');
        $this->addSql('ALTER TABLE external_structure_identity DROP CONSTRAINT fk_external_structure_organisation');
        $this->addSql('DROP INDEX idx_external_structure_organisation');
        $this->addSql('ALTER TABLE external_structure_identity DROP COLUMN organisation_id');
        $this->addSql(<<<'SQL'
ALTER TABLE external_structure_identity
    ADD CONSTRAINT chk_external_structure_identity_target CHECK (
        (department_id IS NOT NULL AND group_id IS NULL)
        OR (department_id IS NULL AND group_id IS NOT NULL)
    )
SQL);
    }
}
