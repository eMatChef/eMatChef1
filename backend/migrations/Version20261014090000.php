<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Standorte: Unique-Index auf department_grossanlass_place_group.group_id durch normalen Index ersetzen (mehrere Standorte pro Gruppe).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_ga_place_group_group');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_place_group_group ON department_grossanlass_place_group (group_id)');
    }

    public function down(Schema $schema): void
    {
        // Nur möglich, solange keine Gruppe mehrere Standorte hat; sonst bricht der Unique-Index bewusst ab.
        $this->addSql('DROP INDEX IF EXISTS idx_ga_place_group_group');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_place_group_group ON department_grossanlass_place_group (group_id)');
    }
}
