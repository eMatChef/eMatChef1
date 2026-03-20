<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308280000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move pending email workflow from profile to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD pending_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('UPDATE "user" u SET pending_email = p.pending_email FROM profile p WHERE p.id = u.profile_id AND p.pending_email IS NOT NULL');
        $this->addSql('UPDATE "user" u SET email_verification_token = p.pending_email_token FROM profile p WHERE p.id = u.profile_id AND p.pending_email_token IS NOT NULL AND u.email_verification_token IS NULL');
        $this->addSql('UPDATE "user" u SET email_verification_expires_at = p.pending_email_expires_at FROM profile p WHERE p.id = u.profile_id AND p.pending_email_expires_at IS NOT NULL AND u.email_verification_expires_at IS NULL');
        $this->addSql('DROP INDEX IF EXISTS uniq_profile_pending_email_token');
        $this->addSql('ALTER TABLE profile DROP pending_email');
        $this->addSql('ALTER TABLE profile DROP pending_email_token');
        $this->addSql('ALTER TABLE profile DROP pending_email_expires_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD pending_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD pending_email_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD pending_email_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_profile_pending_email_token ON profile (pending_email_token)');
        $this->addSql('UPDATE profile p SET pending_email = u.pending_email FROM "user" u WHERE u.profile_id = p.id AND u.pending_email IS NOT NULL');
        $this->addSql('ALTER TABLE "user" DROP pending_email');
    }
}
