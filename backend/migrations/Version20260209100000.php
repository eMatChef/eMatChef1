<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Erweitert den Activity-Workflow:
 * - Neue Status-Werte: submitted, approved, packing, packed, issued, returned
 * - Workflow-Timestamps: submitted_at, approved_at, issued_at, returned_at, completed_at
 * - rejection_comment für Zurückweisungen
 * - Migriert bestehende Status: confirmed → submitted, active → issued
 * 
 * Neuer Flow: draft → submitted → approved → packing → packed → issued → returned → completed
 */
final class Version20260209100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activity Workflow erweitern: neue Status-Werte + Workflow-Timestamps + Packlisten/Rückgabe/Meldungen Tabellen';
    }

    public function up(Schema $schema): void
    {
        // 1. Neue Spalten zur activity-Tabelle hinzufügen
        $this->addSql('ALTER TABLE activity ADD COLUMN submitted_at TIMESTAMP DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD COLUMN approved_at TIMESTAMP DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD COLUMN issued_at TIMESTAMP DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD COLUMN returned_at TIMESTAMP DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD COLUMN completed_at TIMESTAMP DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD COLUMN rejection_comment TEXT DEFAULT NULL');

        // 2. Bestehende Status migrieren (alte → neue Bezeichnungen)
        // confirmed → submitted (wurde vom Leader freigegeben)
        $this->addSql("UPDATE activity SET status = 'submitted', submitted_at = updated_at WHERE status = 'confirmed'");
        // active → issued (Material wurde ausgegeben)
        $this->addSql("UPDATE activity SET status = 'issued', issued_at = updated_at WHERE status = 'active'");
        // completed → completed (bleibt, aber completed_at setzen)
        $this->addSql("UPDATE activity SET completed_at = updated_at WHERE status = 'completed'");

        // 3. Neue Tabelle: activity_pack_item (Packliste)
        $this->addSql('
            CREATE TABLE activity_pack_item (
                id CHARACTER(13) NOT NULL,
                activity_id CHARACTER(12) NOT NULL,
                material_item_id CHARACTER(12) NOT NULL,
                quantity_ordered INTEGER NOT NULL DEFAULT 0,
                quantity_packed INTEGER NOT NULL DEFAULT 0,
                condition_out VARCHAR(50) DEFAULT \'ok\',
                batch_numbers TEXT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                packed_by_user_id CHARACTER(12) DEFAULT NULL,
                packed_at TIMESTAMP DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT fk_pack_item_activity FOREIGN KEY (activity_id) REFERENCES activity(id) ON DELETE CASCADE,
                CONSTRAINT fk_pack_item_material FOREIGN KEY (material_item_id) REFERENCES material_item(id) ON DELETE CASCADE,
                CONSTRAINT fk_pack_item_user FOREIGN KEY (packed_by_user_id) REFERENCES "user"(id) ON DELETE SET NULL
            )
        ');
        $this->addSql('CREATE INDEX idx_pack_item_activity ON activity_pack_item(activity_id)');
        $this->addSql('CREATE INDEX idx_pack_item_material ON activity_pack_item(material_item_id)');

        // 4. Neue Tabelle: activity_return_item (Rückgabeliste)
        $this->addSql('
            CREATE TABLE activity_return_item (
                id CHARACTER(13) NOT NULL,
                activity_id CHARACTER(12) NOT NULL,
                material_item_id CHARACTER(12) NOT NULL,
                quantity_returned INTEGER NOT NULL DEFAULT 0,
                quantity_damaged INTEGER NOT NULL DEFAULT 0,
                quantity_missing INTEGER NOT NULL DEFAULT 0,
                condition_in VARCHAR(50) DEFAULT \'ok\',
                notes TEXT DEFAULT NULL,
                returned_by_user_id CHARACTER(12) DEFAULT NULL,
                returned_at TIMESTAMP DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT fk_return_item_activity FOREIGN KEY (activity_id) REFERENCES activity(id) ON DELETE CASCADE,
                CONSTRAINT fk_return_item_material FOREIGN KEY (material_item_id) REFERENCES material_item(id) ON DELETE CASCADE,
                CONSTRAINT fk_return_item_user FOREIGN KEY (returned_by_user_id) REFERENCES "user"(id) ON DELETE SET NULL
            )
        ');
        $this->addSql('CREATE INDEX idx_return_item_activity ON activity_return_item(activity_id)');
        $this->addSql('CREATE INDEX idx_return_item_material ON activity_return_item(material_item_id)');

        // 5. Neue Tabelle: activity_issue_report (Meldungen während Ausleihe)
        $this->addSql('
            CREATE TABLE activity_issue_report (
                id CHARACTER(13) NOT NULL,
                activity_id CHARACTER(12) NOT NULL,
                material_item_id CHARACTER(12) DEFAULT NULL,
                type VARCHAR(20) NOT NULL DEFAULT \'damage\',
                quantity INTEGER NOT NULL DEFAULT 1,
                description TEXT DEFAULT NULL,
                reported_by_user_id CHARACTER(12) DEFAULT NULL,
                reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                photo_url VARCHAR(500) DEFAULT NULL,
                resolved BOOLEAN NOT NULL DEFAULT FALSE,
                resolved_at TIMESTAMP DEFAULT NULL,
                resolved_by_user_id CHARACTER(12) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT fk_issue_activity FOREIGN KEY (activity_id) REFERENCES activity(id) ON DELETE CASCADE,
                CONSTRAINT fk_issue_material FOREIGN KEY (material_item_id) REFERENCES material_item(id) ON DELETE SET NULL,
                CONSTRAINT fk_issue_reporter FOREIGN KEY (reported_by_user_id) REFERENCES "user"(id) ON DELETE SET NULL,
                CONSTRAINT fk_issue_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES "user"(id) ON DELETE SET NULL
            )
        ');
        $this->addSql('CREATE INDEX idx_issue_report_activity ON activity_issue_report(activity_id)');
        $this->addSql('CREATE INDEX idx_issue_report_type ON activity_issue_report(type)');

        // 6. FK für workshop_ticket → activity_issue_report nachträglich hinzufügen
        // (workshop_ticket wurde in 20260208220000 erstellt, aber activity_issue_report existierte da noch nicht)
        $this->addSql('ALTER TABLE workshop_ticket ADD CONSTRAINT fk_workshop_issue FOREIGN KEY (issue_report_id) REFERENCES activity_issue_report(id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Tabellen entfernen
        $this->addSql('DROP TABLE IF EXISTS activity_issue_report');
        $this->addSql('DROP TABLE IF EXISTS activity_return_item');
        $this->addSql('DROP TABLE IF EXISTS activity_pack_item');

        // Status zurück migrieren
        $this->addSql("UPDATE activity SET status = 'confirmed' WHERE status = 'submitted'");
        $this->addSql("UPDATE activity SET status = 'active' WHERE status = 'issued'");
        $this->addSql("UPDATE activity SET status = 'draft' WHERE status IN ('approved', 'packing', 'packed', 'returned')");

        // Spalten entfernen
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS submitted_at');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS approved_at');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS issued_at');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS returned_at');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS completed_at');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS rejection_comment');
    }
}
