<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203193049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Nur bei Altbeständen mit user_department; frische DBs haben die Tabelle erst nach späteren Schritten / nie.
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
