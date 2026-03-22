<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create public_code table for public QR lookups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE public_code (
            id CHARACTER(12) NOT NULL,
            entity_type VARCHAR(32) NOT NULL,
            entity_id VARCHAR(20) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            public_code VARCHAR(64) NOT NULL,
            is_public BOOLEAN DEFAULT TRUE NOT NULL,
            is_active BOOLEAN DEFAULT TRUE NOT NULL,
            version INT DEFAULT 1 NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_by_user_id CHARACTER(12) DEFAULT NULL,
            revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_scanned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            scan_count INT DEFAULT 0 NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_public_code_code ON public_code (public_code)');
        $this->addSql('CREATE INDEX idx_public_code_entity ON public_code (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_public_code_department_type ON public_code (department_id, entity_type)');
        $this->addSql('CREATE UNIQUE INDEX uq_public_code_active_per_entity ON public_code (entity_type, entity_id) WHERE is_active = TRUE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public_code');
    }
}

