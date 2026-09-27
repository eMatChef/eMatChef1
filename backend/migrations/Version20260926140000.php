<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Wunsch kann selbst organisiert sein und fällt dann aus der Beschaffung';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS self_organized BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS self_organized');
    }
}
