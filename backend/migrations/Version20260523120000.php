<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Department display screens (publicId + access code hash) for infoscreen kiosk.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE department_display_screen (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    name VARCHAR(120) NOT NULL,
    public_id CHAR(12) NOT NULL,
    access_code_hash VARCHAR(255) NOT NULL,
    access_code_hint CHAR(2) DEFAULT NULL,
    code_version INT NOT NULL DEFAULT 1,
    created_by_user_id CHAR(12) DEFAULT NULL,
    revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_department_display_public_id ON department_display_screen (public_id)');
        $this->addSql('CREATE INDEX idx_department_display_department ON department_display_screen (department_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE department_display_screen');
    }
}
