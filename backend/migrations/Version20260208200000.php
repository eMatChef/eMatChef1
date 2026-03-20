<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fügt die group_id Spalte zur activity Tabelle hinzu (Sub-Department/Gruppe).
 */
final class Version20260208200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds group_id column to activity table referencing the group table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN group_id CHAR(12) NULL');
        $this->addSql('CREATE INDEX idx_activity_group ON activity (group_id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AFE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AFE54D947');
        $this->addSql('DROP INDEX idx_activity_group');
        $this->addSql('ALTER TABLE activity DROP COLUMN group_id');
    }
}
