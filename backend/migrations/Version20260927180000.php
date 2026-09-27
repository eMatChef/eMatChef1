<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Anfrage merkt Kanal (Anruf/Mail) und die Positionen, die dabei angefragt wurden';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS line_ids JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS asked_via VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS asked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS asked_lines JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS asked_lines');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS asked_at');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS asked_via');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS line_ids');
    }
}
