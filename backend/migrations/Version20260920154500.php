<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920154500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Bereich/Bauprojekt mit anderem Ressort teilen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE department_grossanlass_group_share (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    group_id CHARACTER(12) NOT NULL,
    target_group_id CHARACTER(12) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id),
    CONSTRAINT uniq_ga_group_share UNIQUE (group_id, target_group_id),
    CONSTRAINT fk_ga_group_share_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT fk_ga_group_share_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT fk_ga_group_share_target FOREIGN KEY (target_group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
)
SQL);
        $this->addSql('CREATE INDEX idx_ga_group_share_dept ON department_grossanlass_group_share (department_id)');
        $this->addSql('CREATE INDEX idx_ga_group_share_target ON department_grossanlass_group_share (target_group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE department_grossanlass_group_share');
    }
}
