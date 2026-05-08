<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields and join request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ADD email_verified BOOLEAN DEFAULT FALSE NOT NULL");
        $this->addSql("ALTER TABLE \"user\" ADD email_verification_token VARCHAR(64) DEFAULT NULL");
        $this->addSql("ALTER TABLE \"user\" ADD email_verification_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        $this->addSql("CREATE UNIQUE INDEX uniq_user_email_verification_token ON \"user\" (email_verification_token)");

        $this->addSql('CREATE TABLE join_request (id CHAR(12) NOT NULL, user_id CHAR(12) NOT NULL, department_id CHAR(12) NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reviewed_by CHAR(12) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_join_request_user ON join_request (user_id)');
        $this->addSql('CREATE INDEX idx_join_request_department ON join_request (department_id)');
        $this->addSql('CREATE INDEX idx_join_request_status ON join_request (status)');
        $this->addSql('CREATE INDEX idx_join_request_reviewed_by ON join_request (reviewed_by)');
        $this->addSql('ALTER TABLE join_request ADD CONSTRAINT fk_join_request_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE join_request ADD CONSTRAINT fk_join_request_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE join_request ADD CONSTRAINT fk_join_request_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE join_request DROP CONSTRAINT fk_join_request_reviewed_by');
        $this->addSql('ALTER TABLE join_request DROP CONSTRAINT fk_join_request_department');
        $this->addSql('ALTER TABLE join_request DROP CONSTRAINT fk_join_request_user');
        $this->addSql('DROP TABLE join_request');

        $this->addSql('DROP INDEX uniq_user_email_verification_token');
        $this->addSql('ALTER TABLE "user" DROP email_verification_expires_at');
        $this->addSql('ALTER TABLE "user" DROP email_verification_token');
        $this->addSql('ALTER TABLE "user" DROP email_verified');
    }
}
