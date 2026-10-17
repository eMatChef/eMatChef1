<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Bauauftrag: formelle Gesamtabnahme (department_grossanlass_order_event, Verlauf mit Person, Zeitpunkt und Begründung). Bestehende Daten bleiben unverändert; kein Bauauftrag gilt rückwirkend als abgenommen.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_order_event (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            group_id CHARACTER(12) NOT NULL,
            seq INT NOT NULL,
            type VARCHAR(16) NOT NULL,
            user_id CHARACTER(12) NOT NULL,
            note TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_order_event_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_order_event_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_order_event_seq ON department_grossanlass_order_event (group_id, seq)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_order_event');
    }
}
