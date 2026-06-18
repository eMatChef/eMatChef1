<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity.participant_count für J+S-Dotation (Camp/Event)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS participant_count INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS participant_count');
    }
}
