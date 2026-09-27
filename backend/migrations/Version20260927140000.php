<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Fahrzeugwunsch kann einer Beschaffungsposition zugeordnet werden';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_vehicle_need ADD COLUMN IF NOT EXISTS procurement_line_id CHARACTER(12) NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_vehicle_need_line ON department_grossanlass_vehicle_need (procurement_line_id)');
        $this->addSql('ALTER TABLE department_grossanlass_vehicle_need DROP CONSTRAINT IF EXISTS fk_ga_vehicle_need_line');
        $this->addSql('ALTER TABLE department_grossanlass_vehicle_need ADD CONSTRAINT fk_ga_vehicle_need_line FOREIGN KEY (procurement_line_id) REFERENCES activity_grossanlass_procurement_line (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_vehicle_need DROP CONSTRAINT IF EXISTS fk_ga_vehicle_need_line');
        $this->addSql('DROP INDEX IF EXISTS idx_ga_vehicle_need_line');
        $this->addSql('ALTER TABLE department_grossanlass_vehicle_need DROP COLUMN IF EXISTS procurement_line_id');
    }
}
