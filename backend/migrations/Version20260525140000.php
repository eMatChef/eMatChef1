<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Display screen: activity/workshop status filters and statistics toggle.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE department_display_screen
            ADD activity_statuses JSON NOT NULL DEFAULT '["submitted","approved","packing","packed","at_event"]'::json,
            ADD workshop_statuses JSON NOT NULL DEFAULT '["open","in_progress","waiting_parts"]'::json,
            ADD show_statistics BOOLEAN NOT NULL DEFAULT false
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_display_screen DROP activity_statuses');
        $this->addSql('ALTER TABLE department_display_screen DROP workshop_statuses');
        $this->addSql('ALTER TABLE department_display_screen DROP show_statistics');
    }
}
