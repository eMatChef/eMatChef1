<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308270000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending email fields to profile table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD pending_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD pending_email_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD pending_email_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_profile_pending_email_token ON profile (pending_email_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_profile_pending_email_token');
        $this->addSql('ALTER TABLE profile DROP pending_email');
        $this->addSql('ALTER TABLE profile DROP pending_email_token');
        $this->addSql('ALTER TABLE profile DROP pending_email_expires_at');
    }
}
