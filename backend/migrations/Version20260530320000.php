<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Medien Paket 2 — Issue-Report-Fotos als JSON-Array. */
final class Version20260530320000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activity_issue_report.photos JSON column (media Paket 2).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_issue_report ADD photos JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_issue_report DROP COLUMN photos');
    }
}
