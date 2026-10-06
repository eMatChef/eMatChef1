<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Trusted Devices (Login-MFA-Vertrauen) und deren Zuordnung zu Sitzungen. */
final class Version20261006180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add trusted_device and user_session.mfa_source/trusted_device_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE trusted_device (
    id VARCHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    credential_hash VARCHAR(64) NOT NULL,
    label VARCHAR(120) NOT NULL,
    trusted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    revoked_reason VARCHAR(32) DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_trusted_device_credential ON trusted_device (credential_hash)');
        $this->addSql('CREATE INDEX idx_trusted_device_user ON trusted_device (user_id, revoked_at)');
        $this->addSql('ALTER TABLE trusted_device ADD CONSTRAINT fk_trusted_device_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE user_session ADD mfa_source VARCHAR(24) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_session ADD trusted_device_id VARCHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT fk_user_session_trusted_device FOREIGN KEY (trusted_device_id) REFERENCES trusted_device (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_user_session_trusted_device ON user_session (trusted_device_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_session DROP CONSTRAINT fk_user_session_trusted_device');
        $this->addSql('DROP INDEX idx_user_session_trusted_device');
        $this->addSql('ALTER TABLE user_session DROP COLUMN trusted_device_id');
        $this->addSql('ALTER TABLE user_session DROP COLUMN mfa_source');
        $this->addSql('DROP TABLE trusted_device');
    }
}
