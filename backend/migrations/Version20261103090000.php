<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Logistik: Transporttouren mit Bedarfs-Verknüpfung, geordneten Stopps und Änderungsprotokoll.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_tour (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            name VARCHAR(120) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'draft\',
            vehicle_id CHARACTER(12) NULL,
            chauffeur_user_id CHARACTER(12) NULL,
            trailer VARCHAR(80) DEFAULT NULL,
            created_by_user_id CHARACTER(12) NULL,
            confirmed_by_user_id CHARACTER(12) NULL,
            confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_tour_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_tour_dept ON department_grossanlass_tour (department_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_tour_item (
            id CHARACTER(12) NOT NULL,
            tour_id CHARACTER(12) NOT NULL,
            einsatz_id CHARACTER(12) NULL,
            vehicle_need_id CHARACTER(12) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_tour_item_tour FOREIGN KEY (tour_id) REFERENCES department_grossanlass_tour (id) ON DELETE CASCADE,
            CONSTRAINT chk_ga_tour_item_one_need CHECK ((einsatz_id IS NOT NULL) <> (vehicle_need_id IS NOT NULL))
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_tour_item_tour ON department_grossanlass_tour_item (tour_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_ga_tour_item_einsatz ON department_grossanlass_tour_item (einsatz_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_ga_tour_item_vehicle_need ON department_grossanlass_tour_item (vehicle_need_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_tour_stop (
            id CHARACTER(12) NOT NULL,
            tour_id CHARACTER(12) NOT NULL,
            item_id CHARACTER(12) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            kind VARCHAR(10) NOT NULL,
            place_id CHARACTER(12) NULL,
            label VARCHAR(255) NOT NULL DEFAULT \'\',
            planned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_tour_stop_tour FOREIGN KEY (tour_id) REFERENCES department_grossanlass_tour (id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_tour_stop_item FOREIGN KEY (item_id) REFERENCES department_grossanlass_tour_item (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_tour_stop_tour ON department_grossanlass_tour_stop (tour_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_tour_event (
            id CHARACTER(12) NOT NULL,
            tour_id CHARACTER(12) NOT NULL,
            user_id CHARACTER(12) NULL,
            action VARCHAR(24) NOT NULL,
            note TEXT NOT NULL DEFAULT \'\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_tour_event_tour FOREIGN KEY (tour_id) REFERENCES department_grossanlass_tour (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_tour_event_tour ON department_grossanlass_tour_event (tour_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_tour_event');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_tour_stop');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_tour_item');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_tour');
    }
}
