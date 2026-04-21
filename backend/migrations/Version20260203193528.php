<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203193528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'membership-Tabelle; legt bei Bedarf Kern-Schema (user/department/…) für frische DB an';
    }

    public function up(Schema $schema): void
    {
        // Frische DB: Kern-Tabellen fehlen oft (historisch nur Deltas migriert). Muss vor membership-FKs existieren.
        // Duplikat der Logik in Version20260203165000 — bei Deploy ohne diese Datei greift nur dieser Block.
        if (!$this->sm->tablesExist(['user'])) {
            if ($this->sm->tablesExist(['organisation'])) {
                throw new \RuntimeException(
                    'Inkonsistente Datenbank: organisation ohne user. Bitte DB leeren (docker compose down -v) oder manuell reparieren.'
                );
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

        $this->addSql('CREATE TABLE membership (user_id CHARACTER(12) NOT NULL, department_id CHARACTER(12) NOT NULL, role VARCHAR(20) NOT NULL, is_primary BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(user_id, department_id))');
        $this->addSql('CREATE INDEX IDX_86FFD285A76ED395 ON membership (user_id)');
        $this->addSql('CREATE INDEX IDX_86FFD285AE80F5DF ON membership (department_id)');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285AE80F5DF FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Migriere Daten von user_department nach membership (nur Altbestand; frische DB ohne user_department)
        if ($this->sm->tablesExist(['user_department'])) {
            $this->addSql("INSERT INTO membership (user_id, department_id, role, is_primary) 
                       SELECT user_id, department_id, role, is_primary 
                       FROM user_department 
                       WHERE NOT EXISTS (
                           SELECT 1 FROM membership m 
                           WHERE m.user_id = user_department.user_id 
                           AND m.department_id = user_department.department_id
                       )");
        }

        // Lösche alte Tabelle user_department
        $this->addSql('DROP TABLE IF EXISTS user_department CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Erstelle user_department Tabelle wieder (für Rollback)
        $this->addSql('CREATE TABLE user_department (user_id CHARACTER(12) NOT NULL, department_id CHARACTER(12) NOT NULL, role VARCHAR(16) NOT NULL, is_primary BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(user_id, department_id))');
        $this->addSql('CREATE INDEX IDX_user_department_user_id ON user_department (user_id)');
        $this->addSql('CREATE INDEX IDX_user_department_department_id ON user_department (department_id)');
        $this->addSql('ALTER TABLE user_department ADD CONSTRAINT FK_user_department_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_department ADD CONSTRAINT FK_user_department_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        
        // Migriere Daten zurück
        $this->addSql("INSERT INTO user_department (user_id, department_id, role, is_primary) 
                       SELECT user_id, department_id, role, is_primary 
                       FROM membership");
        
        $this->addSql('ALTER TABLE membership DROP CONSTRAINT FK_86FFD285A76ED395');
        $this->addSql('ALTER TABLE membership DROP CONSTRAINT FK_86FFD285AE80F5DF');
        $this->addSql('DROP TABLE membership');
    }
}
