<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AdminJoinRequest: requested_parent_department_name für übergeordnete Abteilung.
 */
final class Version20260308250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add requested_parent_department_name to admin_join_request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_join_request ADD requested_parent_department_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_join_request DROP requested_parent_department_name');
    }
}
