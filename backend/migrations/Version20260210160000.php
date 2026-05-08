<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Zentrale Vorlagen – department_id nullable + scope Feld
 * 
 * Ermöglicht globale Vorlagen (department_id=NULL) die für alle Departments sichtbar sind.
 * Bestehende Vorlagen werden auf scope='global' und department_id=NULL gesetzt.
 */
final class Version20260210160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make material_template.department_id nullable, add scope column, convert existing templates to global.';
    }

    public function up(Schema $schema): void
    {
        // 1. scope Spalte hinzufügen
        $this->addSql("ALTER TABLE material_template ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'global'");

        // 2. department_id nullable machen (PostgreSQL-Syntax)
        $this->addSql("ALTER TABLE material_template ALTER COLUMN department_id DROP NOT NULL");

        // 3. Index für scope
        $this->addSql("CREATE INDEX idx_template_scope ON material_template (scope)");

        // 4. Bestehende Hersteller-Vorlagen zu globalen Vorlagen machen
        $this->addSql("UPDATE material_template SET scope = 'global', department_id = NULL WHERE source IS NOT NULL AND source != 'custom'");
    }

    public function down(Schema $schema): void
    {
        // Scope-Index entfernen
        $this->addSql("DROP INDEX idx_template_scope");

        // Scope-Spalte entfernen
        $this->addSql("ALTER TABLE material_template DROP COLUMN scope");

        // department_id zurück auf NOT NULL (Vorsicht: NULL-Werte müssen vorher gefüllt werden!)
        $this->addSql("UPDATE material_template SET department_id = '' WHERE department_id IS NULL");
        $this->addSql("ALTER TABLE material_template ALTER COLUMN department_id SET NOT NULL");
    }
}
