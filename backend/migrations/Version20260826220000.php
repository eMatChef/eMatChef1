<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fixe Daten: Uhrzeit von/bis am Kalenderzeitraum';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_calendar_period ADD COLUMN IF NOT EXISTS start_time TIME NOT NULL DEFAULT \'00:00:00\'');
        $this->addSql('ALTER TABLE department_calendar_period ADD COLUMN IF NOT EXISTS end_time TIME NOT NULL DEFAULT \'23:59:00\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_calendar_period DROP COLUMN IF EXISTS start_time');
        $this->addSql('ALTER TABLE department_calendar_period DROP COLUMN IF EXISTS end_time');
    }
}
