<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Planung: Ort und Notizen am Config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS location_text VARCHAR(255) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS location_text');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS notes');
    }
}
