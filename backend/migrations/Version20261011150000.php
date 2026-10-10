<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Display devices: independent devices per infoscreen with hashed credentials and 90-day approvals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE department_display_device (
    id CHAR(12) NOT NULL,
    screen_id CHAR(12) NOT NULL,
    name VARCHAR(120) NOT NULL,
    credential_hash CHAR(64) NOT NULL,
    previous_credential_hash CHAR(64) DEFAULT NULL,
    credential_rotated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    created_via VARCHAR(16) NOT NULL,
    approval_expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    approved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    last_contact_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_by_user_id CHAR(12) DEFAULT NULL,
    reminder14_for TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    reminder3_for TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX idx_display_device_screen ON department_display_device (screen_id)');
        $this->addSql('CREATE INDEX idx_display_device_expires ON department_display_device (approval_expires_at)');
        $this->addSql('ALTER TABLE display_pairing_request ADD device_name VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE display_pairing_request DROP device_name');
        $this->addSql('DROP TABLE department_display_device');
    }
}
