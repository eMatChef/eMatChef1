<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Kern-Schema für frische Installationen: fehlte historisch (nur Deltas waren migriert).
 * Muss vor Version20260203193528 (membership) laufen.
 */
final class Version20260203165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Baseline: organisation, profile, department, user (frische DB)';
    }

    public function up(Schema $schema): void
    {
        if ($this->sm->tablesExist(['organisation'])) {
            return;
        }

        $this->addSql('CREATE TABLE organisation (id CHARACTER(12) NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE TABLE profile (id CHARACTER(12) NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, nickname VARCHAR(50) DEFAULT NULL, language VARCHAR(5) DEFAULT \'de\' NOT NULL, roles JSONB NOT NULL, background_color VARCHAR(7) DEFAULT NULL, text_color VARCHAR(7) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D9CFB71A6AC7E5C5 ON profile (email)');

        $this->addSql('CREATE TABLE department (id CHARACTER(12) NOT NULL, organisation_id CHARACTER(12) NOT NULL, name VARCHAR(255) NOT NULL, billing_address_id CHARACTER(12) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_80AF3D6E9E6B1585 ON department (organisation_id)');
        $this->addSql('ALTER TABLE department ADD CONSTRAINT FK_80AF3D6E9E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE "user" (id CHARACTER(12) NOT NULL, state VARCHAR(16) DEFAULT \'active\' NOT NULL, password VARCHAR(255) NOT NULL, profile_id CHARACTER(12) NOT NULL, created_by CHARACTER(12) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9CCFA12B8 ON "user" (profile_id)');
        $this->addSql('CREATE INDEX IDX_USER_CREATED_BY ON "user" (created_by)');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_1483A5E9CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_USER_CREATED_BY FOREIGN KEY (created_by) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        if (!$this->sm->tablesExist(['organisation'])) {
            return;
        }

        if ($this->sm->tablesExist(['membership'])) {
            $this->addSql('DROP TABLE membership');
        }
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS FK_USER_CREATED_BY');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS FK_1483A5E9CCFA12B8');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('ALTER TABLE department DROP CONSTRAINT IF EXISTS FK_80AF3D6E9E6B1585');
        $this->addSql('DROP TABLE department');
        $this->addSql('DROP TABLE profile');
        $this->addSql('DROP TABLE organisation');
    }
}
