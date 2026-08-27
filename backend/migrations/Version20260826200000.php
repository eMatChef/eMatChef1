<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Teilnehmer-Abteilungen Flag und Einladungs-Gruppen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS has_guest_departments BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS invite_group_ids JSON DEFAULT '[]' NOT NULL");
        $this->addSql(<<<'SQL'
UPDATE department_grossanlass_config c
SET has_guest_departments = true
WHERE EXISTS (
    SELECT 1 FROM department_grossanlass_participant p
    WHERE p.host_department_id = c.department_id
)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS invite_group_ids');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS has_guest_departments');
    }
}
