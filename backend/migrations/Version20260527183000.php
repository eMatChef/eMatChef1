<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\SitePageContentDefaults;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Startseite (landing) in site_page mit aktuellen Launch-Texten abgleichen.
 */
final class Version20260527183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Upsert site_page landing content from SitePageContentDefaults (launch texts).';
    }

    public function up(Schema $schema): void
    {
        $content = (new SitePageContentDefaults())->forSlug('landing');
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
            ['landing', $json]
        );
    }

    public function down(Schema $schema): void
    {
        // Vorheriger Landing-Inhalt war nicht versioniert; kein sinnvolles Zurücksetzen.
    }
}
