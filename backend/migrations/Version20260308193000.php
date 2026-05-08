<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill existing users as email verified when they have no verification token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE "user" SET email_verified = TRUE WHERE email_verification_token IS NULL AND email_verification_expires_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        // no-op
    }
}
