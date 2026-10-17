<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Infoscreen-Layouts: Entwurf am Screen, unveränderliche veröffentlichte Revisionen, Bildschirmprofil je Gerät. Rein additiv.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_display_screen ADD COLUMN IF NOT EXISTS layout_draft JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE department_display_screen ADD COLUMN IF NOT EXISTS layout_draft_version INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE department_display_screen ADD COLUMN IF NOT EXISTS layout_draft_origin JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE department_display_screen ADD COLUMN IF NOT EXISTS layout_draft_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE department_display_screen ADD COLUMN IF NOT EXISTS layout_published_number INT DEFAULT NULL');
        $this->addSql('ALTER TABLE department_display_device ADD COLUMN IF NOT EXISTS display_profile JSON DEFAULT NULL');
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_display_layout_revision (
    id CHAR(12) NOT NULL,
    screen_id CHAR(12) NOT NULL,
    number INT NOT NULL,
    layout JSON NOT NULL,
    published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    published_by_user_id CHAR(12) DEFAULT NULL,
    restored_from_number INT DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_display_layout_revision ON department_display_layout_revision (screen_id, number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_display_layout_revision');
        $this->addSql('ALTER TABLE department_display_device DROP COLUMN IF EXISTS display_profile');
        $this->addSql('ALTER TABLE department_display_screen DROP COLUMN IF EXISTS layout_published_number');
        $this->addSql('ALTER TABLE department_display_screen DROP COLUMN IF EXISTS layout_draft_updated_at');
        $this->addSql('ALTER TABLE department_display_screen DROP COLUMN IF EXISTS layout_draft_origin');
        $this->addSql('ALTER TABLE department_display_screen DROP COLUMN IF EXISTS layout_draft_version');
        $this->addSql('ALTER TABLE department_display_screen DROP COLUMN IF EXISTS layout_draft');
    }
}
