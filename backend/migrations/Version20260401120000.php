<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\SitePageContentDefaults;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * FAQ-Seite in site_page mit den aktuellen Standardtexten aus SitePageContentDefaults abgleichen.
 */
final class Version20260401120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Upsert site_page faq content from SitePageContentDefaults.';
    }

    public function up(Schema $schema): void
    {
        $content = (new SitePageContentDefaults())->forSlug('faq');
        if ($content === []) {
            return;
        }

        $json = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO site_page (slug, content, updated_at)
            VALUES (?, ?::json, CURRENT_TIMESTAMP)
            ON CONFLICT (slug) DO UPDATE SET
                content = EXCLUDED.content,
                updated_at = EXCLUDED.updated_at
            SQL,
            ['faq', $json]
        );
    }

    public function down(Schema $schema): void
    {
        // Vorheriger FAQ-Inhalt war nicht versioniert; kein sinnvolles Zurücksetzen.
    }
}
