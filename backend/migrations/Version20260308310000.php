<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308310000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invited_departments JSON column to activity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD invited_departments JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP invited_departments');
    }
}

