<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add assigned department tracking for admin join requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_join_request ADD assigned_department_id CHAR(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_admin_join_request_assigned_department ON admin_join_request (assigned_department_id)');
        $this->addSql('ALTER TABLE admin_join_request ADD CONSTRAINT fk_admin_join_request_assigned_department FOREIGN KEY (assigned_department_id) REFERENCES department (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_join_request DROP CONSTRAINT fk_admin_join_request_assigned_department');
        $this->addSql('DROP INDEX idx_admin_join_request_assigned_department');
        $this->addSql('ALTER TABLE admin_join_request DROP assigned_department_id');
    }
}
