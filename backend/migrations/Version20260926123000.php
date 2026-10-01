<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Fahrzeugwunsch am Bauauftrag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_vehicle_need (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            group_id CHARACTER(12) NOT NULL,
            vehicle_label VARCHAR(255) NOT NULL DEFAULT \'\',
            task_label VARCHAR(255) NOT NULL DEFAULT \'\',
            sort_order INT NOT NULL DEFAULT 0,
            starts_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            duration_minutes INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_vehicle_need_group ON department_grossanlass_vehicle_need (group_id)');
        $this->addSql('DO $$ BEGIN
            ALTER TABLE department_grossanlass_vehicle_need
                ADD CONSTRAINT fk_ga_vehicle_need_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
        EXCEPTION WHEN duplicate_object THEN NULL; END $$');
        $this->addSql('DO $$ BEGIN
            ALTER TABLE department_grossanlass_vehicle_need
                ADD CONSTRAINT fk_ga_vehicle_need_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE;
        EXCEPTION WHEN duplicate_object THEN NULL; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_vehicle_need');
    }
}
