<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Einsatz: Zustellung Fahrt/Abholen + Fahrt-Freigabe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_einsatz ADD COLUMN IF NOT EXISTS delivery VARCHAR(16) DEFAULT 'pickup' NOT NULL");
        $this->addSql('ALTER TABLE department_grossanlass_einsatz ADD COLUMN IF NOT EXISTS trip_released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_einsatz DROP COLUMN IF EXISTS trip_released_at');
        $this->addSql('ALTER TABLE department_grossanlass_einsatz DROP COLUMN IF EXISTS delivery');
    }
}
