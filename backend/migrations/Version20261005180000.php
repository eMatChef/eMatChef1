<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Zusätzliche E-Mail-Adressen: Verifizierungsstatus, Login-Freigabe und Bestätigungs-Token.
 * Bestehende Aliase sind per Einladungslink bewiesen und gelten als verifiziert.
 */
final class Version20261005180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add verification and login flags to user_email_alias';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_email_alias ADD verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE user_email_alias ADD login_enabled BOOLEAN DEFAULT TRUE NOT NULL');
        $this->addSql('ALTER TABLE user_email_alias ADD verification_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_email_alias ADD verification_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE user_email_alias ADD verification_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE user_email_alias SET verified_at = created_at WHERE verified_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email_alias_verification_token ON user_email_alias (verification_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM user_email_alias WHERE verified_at IS NULL) THEN
        RAISE EXCEPTION 'Cannot roll back: unverified email addresses would become verified aliases';
    END IF;
END
$$
SQL);
        $this->addSql('DROP INDEX uniq_user_email_alias_verification_token');
        $this->addSql('ALTER TABLE user_email_alias DROP COLUMN verification_sent_at');
        $this->addSql('ALTER TABLE user_email_alias DROP COLUMN verification_expires_at');
        $this->addSql('ALTER TABLE user_email_alias DROP COLUMN verification_token_hash');
        $this->addSql('ALTER TABLE user_email_alias DROP COLUMN login_enabled');
        $this->addSql('ALTER TABLE user_email_alias DROP COLUMN verified_at');
    }
}
