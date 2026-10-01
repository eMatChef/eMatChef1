<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Geländeplan-Deckkraft (overlay_opacity) am Map-Datensatz';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_map ADD COLUMN IF NOT EXISTS overlay_opacity DOUBLE PRECISION DEFAULT 0.92 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_map DROP COLUMN IF EXISTS overlay_opacity');
    }
}
