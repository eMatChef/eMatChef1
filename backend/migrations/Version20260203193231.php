<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203193231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Siehe Version20260203193049: nur wenn user_department (Altbestand) existiert.
        if ($this->sm->tablesExist(['user_department'])) {
            $this->addSql('ALTER TABLE user_department ALTER role TYPE VARCHAR(20)');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        if ($this->sm->tablesExist(['user_department'])) {
            $this->addSql('ALTER TABLE user_department ALTER role TYPE VARCHAR(16)');
        }
    }
}
