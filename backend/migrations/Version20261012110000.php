<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'membership.is_helper: Helfer-Flag als Zusatz zur Rolle (keine zusätzlichen Rechte).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership ADD COLUMN IF NOT EXISTS is_helper BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership DROP COLUMN IF EXISTS is_helper');
    }
}
