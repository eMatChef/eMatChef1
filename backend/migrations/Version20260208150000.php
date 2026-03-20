<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Activity-Tabelle erstellen
 * Zentrale Entität für Aktivitäten/Events/Vermietungen/Ausleihen
 */
final class Version20260208150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activity table for activities/events/rentals management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            no INTEGER DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            color VARCHAR(7) DEFAULT NULL,
            type VARCHAR(20) NOT NULL DEFAULT \'activity\',
            status VARCHAR(20) NOT NULL DEFAULT \'draft\',
            planning_start TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            planning_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            usage_start TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            usage_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            customer_name VARCHAR(255) DEFAULT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            customer_phone VARCHAR(50) DEFAULT NULL,
            address_id CHARACTER(12) DEFAULT NULL,
            responsible_user_id CHARACTER(12) DEFAULT NULL,
            created_by_user_id CHARACTER(12) DEFAULT NULL,
            total_price NUMERIC(12, 2) DEFAULT NULL,
            deposit_amount NUMERIC(12, 2) DEFAULT NULL,
            deposit_paid BOOLEAN NOT NULL DEFAULT FALSE,
            is_paid BOOLEAN NOT NULL DEFAULT FALSE,
            item_count INTEGER NOT NULL DEFAULT 0,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_activity_department FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE CASCADE,
            CONSTRAINT fk_activity_address FOREIGN KEY (address_id) REFERENCES address(id) ON DELETE SET NULL,
            CONSTRAINT fk_activity_responsible_user FOREIGN KEY (responsible_user_id) REFERENCES "user"(id) ON DELETE SET NULL,
            CONSTRAINT fk_activity_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES "user"(id) ON DELETE SET NULL
        )');

        $this->addSql('CREATE INDEX idx_activity_department ON activity (department_id)');
        $this->addSql('CREATE INDEX idx_activity_status ON activity (status)');
        $this->addSql('CREATE INDEX idx_activity_type ON activity (type)');
        $this->addSql('CREATE INDEX idx_activity_usage_start ON activity (usage_start)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS activity');
    }
}
