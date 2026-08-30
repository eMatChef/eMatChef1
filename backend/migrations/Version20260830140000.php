<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Beschaffung: fixe System-Kategorie J+S (system_key)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_category ADD COLUMN IF NOT EXISTS system_key VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_gpc_dept_system_key ON activity_grossanlass_procurement_category (department_id, system_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_gpc_dept_system_key');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_category DROP COLUMN IF EXISTS system_key');
    }
}
