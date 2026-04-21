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
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
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
