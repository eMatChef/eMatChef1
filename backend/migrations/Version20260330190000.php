<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Öffentliche Webseiten-Inhalte (Landing, Blog, FAQ, AGB/Datenschutz, Impressum), bearbeitbar per Admin-API.
 */
final class Version20260330190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create site_page for editable public website content.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['site_page'])) {
            return;
        }

        $this->addSql('CREATE TABLE site_page (
            slug VARCHAR(64) NOT NULL,
            content JSON NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_by_id CHARACTER(12) DEFAULT NULL,
            PRIMARY KEY(slug)
        )');
        $this->addSql('CREATE INDEX idx_site_page_updated_by ON site_page (updated_by_id)');
        $this->addSql('ALTER TABLE site_page ADD CONSTRAINT fk_site_page_updated_by FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_page DROP CONSTRAINT fk_site_page_updated_by');
        $this->addSql('DROP TABLE site_page');
    }
}
