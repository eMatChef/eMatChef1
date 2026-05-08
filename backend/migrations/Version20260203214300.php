<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add parent_id to department table for hierarchical structure
 */
final class Version20260203214300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent_id column to department table for hierarchical department structure';
    }

    public function up(Schema $schema): void
    {
        // Add parent_id column to department table
        $this->addSql('ALTER TABLE department ADD COLUMN parent_id CHARACTER(12) NULL');
        
        // Add foreign key constraint
        $this->addSql('ALTER TABLE department ADD CONSTRAINT fk_department_parent FOREIGN KEY (parent_id) REFERENCES department (id) ON DELETE SET NULL');
        
        // Add index for better performance
        $this->addSql('CREATE INDEX idx_department_parent_id ON department (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove index
        $this->addSql('DROP INDEX IF EXISTS idx_department_parent_id');
        
        // Remove foreign key constraint
        $this->addSql('ALTER TABLE department DROP CONSTRAINT IF EXISTS fk_department_parent');
        
        // Remove column
        $this->addSql('ALTER TABLE department DROP COLUMN IF EXISTS parent_id');
    }
}
