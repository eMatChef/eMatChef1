<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password reset security columns to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD password_reset_code_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_last_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_window_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_request_count SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_attempt_count SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_locked_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_user_password_reset_expires ON "user" (password_reset_expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_user_password_reset_expires');
        $this->addSql('ALTER TABLE "user" DROP password_reset_code_hash');
        $this->addSql('ALTER TABLE "user" DROP password_reset_expires_at');
        $this->addSql('ALTER TABLE "user" DROP password_reset_last_requested_at');
        $this->addSql('ALTER TABLE "user" DROP password_reset_window_started_at');
        $this->addSql('ALTER TABLE "user" DROP password_reset_request_count');
        $this->addSql('ALTER TABLE "user" DROP password_reset_attempt_count');
        $this->addSql('ALTER TABLE "user" DROP password_reset_locked_until');
    }
}
