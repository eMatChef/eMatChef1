<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Display screen: custom subtitle and content toggles (activities/workshop).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_display_screen ADD subtitle_text VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_display_screen ADD show_activities BOOLEAN NOT NULL DEFAULT true');
        $this->addSql('ALTER TABLE department_display_screen ADD show_workshop BOOLEAN NOT NULL DEFAULT true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_display_screen DROP subtitle_text');
        $this->addSql('ALTER TABLE department_display_screen DROP show_activities');
        $this->addSql('ALTER TABLE department_display_screen DROP show_workshop');
    }
}
