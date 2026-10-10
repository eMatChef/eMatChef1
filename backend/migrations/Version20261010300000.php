<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Display screens: tombstones for permanently deleted screen ids (never reuse dsp/dsi ids).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE display_deleted_id (
    id CHAR(12) NOT NULL,
    kind VARCHAR(8) NOT NULL,
    deleted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    deleted_by_user_id CHAR(12) DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE display_deleted_id');
    }
}
