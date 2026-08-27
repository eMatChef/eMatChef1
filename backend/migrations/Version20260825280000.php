<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825280000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Druckvorlagen zentral per SHA-256 (geteilte PDFs, Duplikat-Hinweis)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE print_layout ADD COLUMN IF NOT EXISTS template_sha256 VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_print_layout_template_sha ON print_layout (template_sha256)');
    }

    public function postUp(Schema $schema): void
    {
        $root = dirname(__DIR__, 2);
        $legacyBase = $root . '/var/uploads/print-layouts';
        $central = $root . '/var/uploads/print-templates';
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, template_filename FROM print_layout WHERE template_filename IS NOT NULL AND (template_sha256 IS NULL OR template_sha256 = \'\')',
        );
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $legacy = $legacyBase . '/' . $id . '/template.pdf';
            if (!is_file($legacy)) {
                continue;
            }
            $sha = hash_file('sha256', $legacy);
            if (!\is_string($sha) || $sha === '') {
                continue;
            }
            if (!is_dir($central) && !mkdir($central, 0775, true) && !is_dir($central)) {
                continue;
            }
            $dest = $central . '/' . $sha . '.pdf';
            if (!is_file($dest)) {
                copy($legacy, $dest);
            }
            $this->connection->executeStatement(
                'UPDATE print_layout SET template_sha256 = ?, template_filename = ? WHERE id = ?',
                [$sha, $sha . '.pdf', $id],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_print_layout_template_sha');
        $this->addSql('ALTER TABLE print_layout DROP COLUMN IF EXISTS template_sha256');
    }
}
