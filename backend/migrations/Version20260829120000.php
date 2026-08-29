<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Anfragen: Koordinaten für Firmenkarte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS latitude');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS longitude');
    }
}
