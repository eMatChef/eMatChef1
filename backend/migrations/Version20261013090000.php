<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Standorte: Zuordnung Standort ↔ Gruppe in department_grossanlass_place_group (Mehrfachnutzung). Die Spalte department_grossanlass_place.group_id bleibt vorerst als Spiegel.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_place_group (
            place_id CHARACTER(12) NOT NULL,
            group_id CHARACTER(12) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (place_id, group_id),
            CONSTRAINT fk_ga_place_group_place FOREIGN KEY (place_id) REFERENCES department_grossanlass_place (id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_place_group_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_place_group_group ON department_grossanlass_place_group (group_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_place_group_place ON department_grossanlass_place_group (place_id)');
        // Verlustfreie Übernahme; ON CONFLICT macht die Migration wiederholbar.
        $this->addSql('INSERT INTO department_grossanlass_place_group (place_id, group_id)
            SELECT p.id, p.group_id FROM department_grossanlass_place p
            WHERE p.group_id IS NOT NULL
            ON CONFLICT DO NOTHING');
    }

    public function down(Schema $schema): void
    {
        // Rückweg: Spiegelspalte aus der Zuordnung nachziehen (nur eindeutige Zuordnungen), dann Tabelle entfernen.
        $this->addSql('UPDATE department_grossanlass_place p SET group_id = (
            SELECT MIN(pg.group_id) FROM department_grossanlass_place_group pg WHERE pg.place_id = p.id
        ) WHERE (SELECT COUNT(*) FROM department_grossanlass_place_group pg WHERE pg.place_id = p.id) = 1');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_place_group');
    }
}
