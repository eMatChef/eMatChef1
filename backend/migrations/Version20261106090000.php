<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Logistik: Fahrauftrag verweist direkt auf den Fahrzeugwunsch, aus dem er überführt wurde (höchstens einer je Wunsch). Rein additiv.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_einsatz ADD COLUMN IF NOT EXISTS vehicle_need_id CHAR(12) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_ga_einsatz_vehicle_need ON department_grossanlass_einsatz (vehicle_need_id) WHERE vehicle_need_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uq_ga_einsatz_vehicle_need');
        $this->addSql('ALTER TABLE department_grossanlass_einsatz DROP COLUMN IF EXISTS vehicle_need_id');
    }
}
