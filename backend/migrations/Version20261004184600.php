<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004184600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add provider-scoped external structure mappings to departments or groups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE external_structure_identity (
    id CHAR(12) NOT NULL,
    provider VARCHAR(32) NOT NULL,
    external_group_id VARCHAR(255) NOT NULL,
    external_type VARCHAR(128) DEFAULT NULL,
    external_name VARCHAR(255) DEFAULT NULL,
    external_parent_id VARCHAR(255) DEFAULT NULL,
    department_id CHAR(12) DEFAULT NULL,
    group_id CHAR(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT chk_external_structure_identity_target CHECK (
        (department_id IS NOT NULL AND group_id IS NULL)
        OR (department_id IS NULL AND group_id IS NOT NULL)
    )
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_external_structure_provider_group ON external_structure_identity (provider, external_group_id)');
        $this->addSql('CREATE INDEX idx_external_structure_department ON external_structure_identity (department_id)');
        $this->addSql('CREATE INDEX idx_external_structure_group ON external_structure_identity (group_id)');
        $this->addSql('ALTER TABLE external_structure_identity ADD CONSTRAINT fk_external_structure_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE external_structure_identity ADD CONSTRAINT fk_external_structure_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_structure_identity DROP CONSTRAINT fk_external_structure_department');
        $this->addSql('ALTER TABLE external_structure_identity DROP CONSTRAINT fk_external_structure_group');
        $this->addSql('DROP TABLE external_structure_identity');
    }
}
