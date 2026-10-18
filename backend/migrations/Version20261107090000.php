<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Logistik: Fahrzeugwunsch unterscheidet Einsatz auf der Baustelle (site_use) von echtem Transportbedarf (transport). Bestehende Wünsche bleiben site_use. Rein additiv.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_vehicle_need ADD COLUMN IF NOT EXISTS purpose VARCHAR(16) NOT NULL DEFAULT 'site_use'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_vehicle_need DROP COLUMN IF EXISTS purpose');
    }
}
