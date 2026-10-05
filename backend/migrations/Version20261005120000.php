<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Serverseitige Login-Sitzungen (UserSession, JWT-Claim `sid`) und Zuordnung der Refresh-Tokens.
 *
 * Rein additiv: refresh_tokens.session_id bleibt NULL für bestehende Tokens; diese werden beim
 * nächsten Refresh einer Legacy-Sitzung zugeordnet. executed_at dieser Version ist der Cutoff
 * für die befristete Übergangsregel (LegacySessionCutoff).
 */
final class Version20261005120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_session and link refresh tokens to their session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE user_session (
    id UUID NOT NULL,
    user_id CHAR(12) NOT NULL,
    auth_method VARCHAR(32) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    mfa_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    step_up_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    revoked_reason VARCHAR(32) DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX idx_user_session_user ON user_session (user_id, revoked_at)');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT fk_user_session_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE refresh_tokens ADD session_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT fk_refresh_tokens_session FOREIGN KEY (session_id) REFERENCES user_session (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_refresh_tokens_session ON refresh_tokens (session_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT fk_refresh_tokens_session');
        $this->addSql('DROP INDEX idx_refresh_tokens_session');
        $this->addSql('ALTER TABLE refresh_tokens DROP COLUMN session_id');
        $this->addSql('DROP TABLE user_session');
    }
}
