<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity_issue_report.repair_checklist für Schadensmeldung Zelt-Diagramm (Paket 13).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_issue_report ADD repair_checklist JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_issue_report DROP COLUMN IF EXISTS repair_checklist');
    }
}
