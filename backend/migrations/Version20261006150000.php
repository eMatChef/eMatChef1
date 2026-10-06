<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Kurzlebige MFA-Challenges für den zweiten Login-Schritt. */
final class Version20261006150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mfa_challenge';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE mfa_challenge (
    id VARCHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    auth_method VARCHAR(32) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    failed_attempts INT DEFAULT 0 NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_mfa_challenge_token ON mfa_challenge (token_hash)');
        $this->addSql('CREATE INDEX idx_mfa_challenge_user ON mfa_challenge (user_id)');
        $this->addSql('ALTER TABLE mfa_challenge ADD CONSTRAINT fk_mfa_challenge_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mfa_challenge');
    }
}
