<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Workshop-Ticket Tabelle erstellen
 * 
 * Werkstatt-Aufträge für Reparatur, Inspektion, Abschreibung, Reinigung.
 * Verknüpft mit MaterialItem, Activity und ActivityIssueReport.
 */
final class Version20260208220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workshop-Ticket Tabelle erstellen für Werkstatt-Verwaltung';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE workshop_ticket (
                id CHARACTER(13) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                material_item_id CHARACTER(12) NOT NULL,
                activity_id CHARACTER(12) DEFAULT NULL,
                issue_report_id CHARACTER(13) DEFAULT NULL,
                type VARCHAR(20) NOT NULL DEFAULT \'repair\',
                priority VARCHAR(20) NOT NULL DEFAULT \'normal\',
                status VARCHAR(20) NOT NULL DEFAULT \'open\',
                title VARCHAR(200) NOT NULL,
                description TEXT DEFAULT NULL,
                assigned_to_user_id CHARACTER(12) DEFAULT NULL,
                estimated_cost DECIMAL(10,2) DEFAULT NULL,
                actual_cost DECIMAL(10,2) DEFAULT NULL,
                parts_used JSON DEFAULT NULL,
                photos JSON DEFAULT NULL,
                started_at TIMESTAMP DEFAULT NULL,
                completed_at TIMESTAMP DEFAULT NULL,
                created_by_user_id CHARACTER(12) DEFAULT NULL,
                resolution_notes TEXT DEFAULT NULL,
                resolution_action VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT fk_workshop_department FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE CASCADE,
                CONSTRAINT fk_workshop_material FOREIGN KEY (material_item_id) REFERENCES material_item(id) ON DELETE CASCADE,
                CONSTRAINT fk_workshop_activity FOREIGN KEY (activity_id) REFERENCES activity(id) ON DELETE SET NULL,
                CONSTRAINT fk_workshop_assigned FOREIGN KEY (assigned_to_user_id) REFERENCES "user"(id) ON DELETE SET NULL,
                CONSTRAINT fk_workshop_creator FOREIGN KEY (created_by_user_id) REFERENCES "user"(id) ON DELETE SET NULL
            )
        ');

        $this->addSql('CREATE INDEX idx_workshop_department ON workshop_ticket(department_id)');
        $this->addSql('CREATE INDEX idx_workshop_status ON workshop_ticket(status)');
        $this->addSql('CREATE INDEX idx_workshop_material ON workshop_ticket(material_item_id)');
        $this->addSql('CREATE INDEX idx_workshop_type ON workshop_ticket(type)');
        $this->addSql('CREATE INDEX idx_workshop_priority ON workshop_ticket(priority)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS workshop_ticket');
    }
}
