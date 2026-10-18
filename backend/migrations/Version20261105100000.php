<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Infoscreen-Studio: eigene Bausteine (Widgets und Gruppen) je Department bzw. Grossanlass. Rein additiv.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_display_block (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    name VARCHAR(80) NOT NULL,
    kind VARCHAR(16) NOT NULL,
    content JSON NOT NULL,
    default_w INT NOT NULL,
    default_h INT NOT NULL,
    created_by_user_id CHAR(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_display_block_dept ON department_display_block (department_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_display_block');
    }
}
