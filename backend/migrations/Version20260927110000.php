<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Art folgt der Eltern-Kind-Struktur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE "group" AS child
            SET grossanlass_kind = \'ressort\'
            FROM "group" AS parent
            WHERE child.parent_id = parent.id
              AND parent.parent_id IS NULL
              AND child.grossanlass_kind IS DISTINCT FROM \'ressort\'');
        $this->addSql('UPDATE "group" AS child
            SET grossanlass_kind = \'teilbereich\'
            FROM "group" AS parent
            WHERE child.parent_id = parent.id
              AND parent.parent_id IS NOT NULL
              AND child.grossanlass_kind IS DISTINCT FROM \'teilbereich\'');
    }

    public function down(Schema $schema): void
    {
    }
}
