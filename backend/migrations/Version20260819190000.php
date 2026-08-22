<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add google_id on user for Google OAuth login';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS google_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_user_google_id ON "user" (google_id) WHERE google_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_user_google_id');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS google_id');
    }
}
