<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: mittlere Ebene als Bereich speichern';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE "group" AS child
            SET grossanlass_kind = \'bereich\'
            FROM "group" AS parent
            WHERE child.parent_id = parent.id
              AND parent.parent_id IS NULL
              AND child.grossanlass_kind = \'ressort\'');
    }

    public function down(Schema $schema): void
    {
    }
}
