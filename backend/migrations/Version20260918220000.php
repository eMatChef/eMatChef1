<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: GA-Ort kind (bauprojekt/unterlager/matplatz/poi)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_place ADD COLUMN IF NOT EXISTS kind VARCHAR(16) NOT NULL DEFAULT 'poi'");
        $this->addSql("UPDATE department_grossanlass_place SET kind = 'unterlager' WHERE unterlager_id IS NOT NULL");
        $this->addSql("UPDATE department_grossanlass_place SET kind = 'bauprojekt' WHERE group_id IN (SELECT id FROM \"group\" WHERE grossanlass_kind = 'teilbereich')");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_place_kind ON department_grossanlass_place (kind)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_ga_place_kind');
        $this->addSql('ALTER TABLE department_grossanlass_place DROP COLUMN IF EXISTS kind');
    }
}
