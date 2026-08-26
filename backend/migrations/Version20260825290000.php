<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825290000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass Gäste: Leihe von Abteilungen und Verkauf an Gäste';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_guest_share (
    id CHARACTER(12) NOT NULL,
    host_department_id CHARACTER(12) NOT NULL,
    guest_department_id CHARACTER(12) NOT NULL,
    kind VARCHAR(16) DEFAULT 'offer' NOT NULL,
    status VARCHAR(16) DEFAULT 'offered' NOT NULL,
    name VARCHAR(255) NOT NULL,
    qty INT DEFAULT 1 NOT NULL,
    family VARCHAR(16) DEFAULT 'material' NOT NULL,
    material_item_id CHARACTER(12) DEFAULT NULL,
    commitment_id CHARACTER(12) DEFAULT NULL,
    starts_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_guest_share_host ON department_grossanlass_guest_share (host_department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_guest_share_guest ON department_grossanlass_guest_share (guest_department_id)');
        $this->addSql('ALTER TABLE department_grossanlass_guest_share DROP CONSTRAINT IF EXISTS fk_ga_guest_share_host');
        $this->addSql('ALTER TABLE department_grossanlass_guest_share ADD CONSTRAINT fk_ga_guest_share_host FOREIGN KEY (host_department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_guest_share DROP CONSTRAINT IF EXISTS fk_ga_guest_share_guest');
        $this->addSql('ALTER TABLE department_grossanlass_guest_share ADD CONSTRAINT fk_ga_guest_share_guest FOREIGN KEY (guest_department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_guest_share DROP CONSTRAINT IF EXISTS fk_ga_guest_share_commitment');
        $this->addSql('ALTER TABLE department_grossanlass_guest_share ADD CONSTRAINT fk_ga_guest_share_commitment FOREIGN KEY (commitment_id) REFERENCES department_grossanlass_commitment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_guest_share');
    }
}
