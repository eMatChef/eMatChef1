<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activity: pack_journey_step — zentraler Checkpoint für Material-Journey';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE activity
            ADD pack_journey_step VARCHAR(32) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP pack_journey_step');
    }
}
