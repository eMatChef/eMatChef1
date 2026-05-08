<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pro User: zuletzt gewählte Abteilung für Login-Vorschlag und UI-Wiederherstellung.
 */
final class Version20260329120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.last_used_department_id (FK department, ON DELETE SET NULL).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD last_used_department_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_USER_LAST_USED_DEPARTMENT FOREIGN KEY (last_used_department_id) REFERENCES department (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_USER_LAST_USED_DEPARTMENT');
        $this->addSql('ALTER TABLE "user" DROP COLUMN last_used_department_id');
    }
}
