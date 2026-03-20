<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Material History Log - Speichert Änderungshistorie für Materialien
 */
final class Version20260208100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create material_history table for tracking material changes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE material_history (
            id CHARACTER(13) NOT NULL,
            material_item_id CHARACTER(12) NOT NULL,
            user_id CHARACTER(12) NULL,
            action VARCHAR(20) NOT NULL DEFAULT \'updated\',
            snapshot JSON NOT NULL,
            changes JSON NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_material_history_material ON material_history (material_item_id)');
        $this->addSql('CREATE INDEX idx_material_history_created ON material_history (created_at)');

        $this->addSql('ALTER TABLE material_history ADD CONSTRAINT FK_material_history_material 
            FOREIGN KEY (material_item_id) REFERENCES material_item (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE material_history ADD CONSTRAINT FK_material_history_user 
            FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS material_history');
    }
}
