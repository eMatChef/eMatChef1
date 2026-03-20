<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin join requests table for unmatched department requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE admin_join_request (id CHAR(12) NOT NULL, user_id CHAR(12) NOT NULL, requested_department_name VARCHAR(255) NOT NULL, requested_affiliation VARCHAR(255) DEFAULT NULL, message TEXT DEFAULT NULL, status VARCHAR(16) DEFAULT 'pending' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reviewed_by CHAR(12) DEFAULT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX idx_admin_join_request_user ON admin_join_request (user_id)');
        $this->addSql('CREATE INDEX idx_admin_join_request_status ON admin_join_request (status)');
        $this->addSql('CREATE INDEX idx_admin_join_request_reviewed_by ON admin_join_request (reviewed_by)');
        $this->addSql('ALTER TABLE admin_join_request ADD CONSTRAINT fk_admin_join_request_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE admin_join_request ADD CONSTRAINT fk_admin_join_request_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_join_request DROP CONSTRAINT fk_admin_join_request_reviewed_by');
        $this->addSql('ALTER TABLE admin_join_request DROP CONSTRAINT fk_admin_join_request_user');
        $this->addSql('DROP TABLE admin_join_request');
    }
}
