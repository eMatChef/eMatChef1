<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924211000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Verantwortliche Person an Bauauftrags-Aufgaben';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_task ADD COLUMN IF NOT EXISTS assignee_user_id CHARACTER(12) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_task DROP COLUMN IF EXISTS assignee_user_id');
    }
}
