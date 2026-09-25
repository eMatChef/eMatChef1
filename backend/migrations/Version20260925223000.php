<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Beschrieb an Bauauftrags-Aufgaben, bisheriger Titel bleibt der Beschrieb';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_task ADD COLUMN IF NOT EXISTS description TEXT NULL');
        $this->addSql("UPDATE department_grossanlass_task SET description = title WHERE description IS NULL AND title <> ''");
        $this->addSql("UPDATE department_grossanlass_task SET title = '' WHERE description IS NOT NULL AND description = title");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE department_grossanlass_task SET title = description WHERE title = '' AND description IS NOT NULL AND description <> ''");
        $this->addSql('ALTER TABLE department_grossanlass_task DROP COLUMN IF EXISTS description');
    }
}
