<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Freigabe der Ersteinrichtung eines Grossanlasses. Bestehende Anlässe gelten als freigegeben, damit produktive Grossanlässe
 * durch den neuen Status nicht gesperrt werden; nur neu angelegte starten mit offener Einrichtung (NULL).
 */
final class Version20261011100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add department_grossanlass_config.setup_released_at and setup_released_by_user_id; release existing Grossanlass departments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config ADD setup_released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_config ADD setup_released_by_user_id CHAR(12) DEFAULT NULL');
        $this->addSql('UPDATE department_grossanlass_config SET setup_released_at = COALESCE(published_at, NOW())');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config DROP setup_released_by_user_id');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP setup_released_at');
    }
}
