<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308260000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar_initials column to profile table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD avatar_initials VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP avatar_initials');
    }
}
