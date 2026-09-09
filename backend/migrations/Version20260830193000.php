<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Beschaffung: Artikelpositionen (kind) getrennt von groben Kategorien';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity_grossanlass_procurement_category ADD COLUMN IF NOT EXISTS kind VARCHAR(16) DEFAULT 'package' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_category DROP COLUMN IF EXISTS kind');
    }
}
