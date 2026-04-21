<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203194220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // user_department kann durch Version20260203193528 bereits entfallen sein.
        if ($this->sm->tablesExist(['user_department'])) {
            $this->addSql('ALTER TABLE user_department DROP CONSTRAINT fk_6a7a2766a76ed395');
            $this->addSql('ALTER TABLE user_department DROP CONSTRAINT fk_6a7a2766ae80f5df');
            $this->addSql('DROP TABLE user_department');
        }
        $this->addSql('ALTER TABLE membership ALTER user_id TYPE VARCHAR(12)');
        $this->addSql('ALTER TABLE membership ALTER department_id TYPE VARCHAR(12)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE user_department (user_id VARCHAR(12) NOT NULL, department_id VARCHAR(12) NOT NULL, role VARCHAR(20) NOT NULL, is_primary BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(user_id, department_id))');
        $this->addSql('CREATE INDEX idx_6a7a2766a76ed395 ON user_department (user_id)');
        $this->addSql('CREATE INDEX idx_6a7a2766ae80f5df ON user_department (department_id)');
        $this->addSql('ALTER TABLE user_department ADD CONSTRAINT fk_6a7a2766a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_department ADD CONSTRAINT fk_6a7a2766ae80f5df FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE membership ALTER user_id TYPE CHAR(12)');
        $this->addSql('ALTER TABLE membership ALTER department_id TYPE CHAR(12)');
    }
}
