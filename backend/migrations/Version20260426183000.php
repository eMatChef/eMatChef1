<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add organisation.allowed_languages JSON column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organisation ADD COLUMN IF NOT EXISTS allowed_languages JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organisation DROP COLUMN IF EXISTS allowed_languages');
    }
}
