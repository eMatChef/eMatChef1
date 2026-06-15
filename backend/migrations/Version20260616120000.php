<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity.wants_js_material — frühe J+S-Entscheidung (Camp/Event)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS wants_js_material BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS wants_js_material');
    }
}
