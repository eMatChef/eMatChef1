<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Logistik Phase 3: Protokoll der Disposition, Änderungswünsche der Ressorts, Tourabschluss mit nicht ausgeführten Fahrten.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_dispatch_event (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            subject_type VARCHAR(16) NOT NULL,
            subject_id VARCHAR(40) NOT NULL,
            user_id CHARACTER(12) NULL,
            action VARCHAR(32) NOT NULL,
            note TEXT NOT NULL DEFAULT \'\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_dispatch_event_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_dispatch_event_subject ON department_grossanlass_dispatch_event (department_id, subject_type, subject_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_change_request (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            einsatz_id CHARACTER(12) NOT NULL,
            requested_by_user_id CHARACTER(12) NULL,
            note TEXT NOT NULL DEFAULT \'\',
            proposed_starts_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            proposed_ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            proposed_destination_place_id CHARACTER(12) NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'open\',
            decision_note TEXT NOT NULL DEFAULT \'\',
            decided_by_user_id CHARACTER(12) NULL,
            decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_change_request_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_change_request_einsatz ON department_grossanlass_change_request (einsatz_id)');

        // Abschluss: nicht ausgeführte Fahrten lösen sich aus der Tour (Historie bleibt), der Bedarf kann neu disponiert werden.
        $this->addSql('ALTER TABLE department_grossanlass_tour ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_tour ADD COLUMN IF NOT EXISTS closed_by_user_id CHARACTER(12) NULL');
        $this->addSql('ALTER TABLE department_grossanlass_tour_item ADD COLUMN IF NOT EXISTS outcome VARCHAR(16) DEFAULT NULL');
        $this->addSql('DROP INDEX IF EXISTS uq_ga_tour_item_einsatz');
        $this->addSql('DROP INDEX IF EXISTS uq_ga_tour_item_vehicle_need');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_ga_tour_item_einsatz ON department_grossanlass_tour_item (einsatz_id) WHERE outcome IS NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_ga_tour_item_vehicle_need ON department_grossanlass_tour_item (vehicle_need_id) WHERE outcome IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uq_ga_tour_item_einsatz');
        $this->addSql('DROP INDEX IF EXISTS uq_ga_tour_item_vehicle_need');
        $this->addSql('DELETE FROM department_grossanlass_tour_item WHERE outcome IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_ga_tour_item_einsatz ON department_grossanlass_tour_item (einsatz_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_ga_tour_item_vehicle_need ON department_grossanlass_tour_item (vehicle_need_id)');
        $this->addSql('ALTER TABLE department_grossanlass_tour_item DROP COLUMN IF EXISTS outcome');
        $this->addSql('ALTER TABLE department_grossanlass_tour DROP COLUMN IF EXISTS closed_by_user_id, DROP COLUMN IF EXISTS closed_at');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_change_request');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_dispatch_event');
    }
}
