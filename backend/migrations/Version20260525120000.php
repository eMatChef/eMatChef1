<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Display screen: per-screen activity type filter (activity_types JSON).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE department_display_screen
            ADD activity_types JSON NOT NULL DEFAULT '["activity","camp","event","external"]'::json
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_display_screen DROP activity_types');
    }
}
