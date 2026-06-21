<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: group.grossanlass_kind (ressort | teilbereich) für Unterressort vs. Bauprojekt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" ADD COLUMN IF NOT EXISTS grossanlass_kind VARCHAR(20) DEFAULT NULL');

        $this->addSql(<<<'SQL'
UPDATE "group" g
SET grossanlass_kind = CASE WHEN g.parent_id IS NULL THEN 'ressort' ELSE 'teilbereich' END
WHERE grossanlass_kind IS NULL
  AND EXISTS (
    SELECT 1 FROM department d WHERE d.id = g.department_id AND d.is_grossanlass = TRUE
  )
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" DROP COLUMN IF EXISTS grossanlass_kind');
    }
}
