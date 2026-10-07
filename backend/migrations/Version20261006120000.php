<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** TOTP-Faktor (verschlüsseltes Secret) und gehashte Recovery Codes. */
final class Version20261006120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_totp and user_recovery_code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE user_totp (
    id VARCHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    secret_encrypted TEXT DEFAULT NULL,
    pending_secret_encrypted TEXT DEFAULT NULL,
    pending_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    activated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    last_used_step BIGINT DEFAULT NULL,
    failed_attempts INT DEFAULT 0 NOT NULL,
    locked_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_totp_user ON user_totp (user_id)');
        $this->addSql('ALTER TABLE user_totp ADD CONSTRAINT fk_user_totp_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
CREATE TABLE user_recovery_code (
    id VARCHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    code_hash VARCHAR(64) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX idx_user_recovery_code_user ON user_recovery_code (user_id)');
        $this->addSql('ALTER TABLE user_recovery_code ADD CONSTRAINT fk_user_recovery_code_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_recovery_code');
        $this->addSql('DROP TABLE user_totp');
    }
}
