<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Helferbedarf: department_grossanlass_group_helper_need (Gesamtbedarf label = \'\', optionale Funktionen je Gruppe).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_group_helper_need (
            group_id CHARACTER(12) NOT NULL,
            label VARCHAR(120) NOT NULL DEFAULT \'\',
            required_count INT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (group_id, label),
            CONSTRAINT fk_ga_helper_need_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_group_helper_need');
    }
}
