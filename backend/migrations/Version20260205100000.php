<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration für wiederverwendbare Adressen
 * 
 * Jede Adresse gehört zu einem Department (Multi-Tenant mit 900+ Departments!)
 * Lagerplätze = Adressen mit type='storage'
 * Rechnungsadressen = Adressen mit type='billing'
 */
final class Version20260205100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add address table with department_id for multi-tenant support';
    }

    public function up(Schema $schema): void
    {
        // Address Tabelle erstellen
        // WICHTIG: Jede Adresse gehört zu einem Department (Multi-Tenant)
        // type bestimmt den Verwendungszweck: billing, storage, customer, event, etc.
        $this->addSql('CREATE TABLE address (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT \'general\',
            name VARCHAR(255) NULL,
            company VARCHAR(255) NULL,
            address_line2 VARCHAR(255) NULL,
            street VARCHAR(255) NOT NULL,
            street_number VARCHAR(20) NULL,
            postal_code VARCHAR(20) NOT NULL,
            city VARCHAR(255) NOT NULL,
            canton VARCHAR(100) NULL,
            country VARCHAR(100) NOT NULL DEFAULT \'Schweiz\',
            latitude DECIMAL(10, 7) NULL,
            longitude DECIMAL(10, 7) NULL,
            additional_info TEXT NULL,
            is_primary BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_address_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE
        )');
        
        // Indices für schnelle Abfragen
        $this->addSql('CREATE INDEX idx_address_department ON address (department_id)');
        $this->addSql('CREATE INDEX idx_address_department_type ON address (department_id, type)');
        $this->addSql('CREATE INDEX idx_address_postal_code ON address (postal_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_address_postal_code');
        $this->addSql('DROP INDEX IF EXISTS idx_address_department_type');
        $this->addSql('DROP INDEX IF EXISTS idx_address_department');
        $this->addSql('DROP TABLE IF EXISTS address');
    }
}
