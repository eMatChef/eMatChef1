<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Data\RepairTemplatePlatformSeeds;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 22 — Plattform-Seed: Spatz, Phönix, Hajk, Wico.
 */
final class Version20260605170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed repair_template platform stubs: spatz, phoenix, hajk, wico (Paket 22).';
    }

    public function up(Schema $schema): void
    {
        foreach (RepairTemplatePlatformSeeds::all() as $template) {
            $structureJson = json_encode($template['structure_json'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $diagramJson = json_encode($template['diagram_json'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $this->connection->executeStatement(
                <<<'SQL'
                INSERT INTO repair_template (
                    id, template_key, name, material_class,
                    structure_json, diagram_json, is_active, created_at, updated_at
                )
                VALUES (?, ?, ?, 'tent', ?::json, ?::json, true, NOW(), NOW())
                ON CONFLICT (template_key) DO NOTHING
                SQL,
                [
                    $template['id'],
                    $template['template_key'],
                    $template['name'],
                    $structureJson,
                    $diagramJson,
                ]
            );
        }

        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE material_item
            SET repair_template_key = 'spatz'
            WHERE repair_template_key IS NULL
              AND LOWER(COALESCE(manufacturer, '')) = 'spatz'
            SQL
        );

        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE material_item
            SET repair_template_key = 'hajk'
            WHERE repair_template_key IS NULL
              AND LOWER(COALESCE(manufacturer, '')) = 'hajk'
            SQL
        );

        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE material_item
            SET repair_template_key = 'wico'
            WHERE repair_template_key IS NULL
              AND LOWER(COALESCE(manufacturer, '')) = 'wico'
            SQL
        );

        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE material_item
            SET repair_template_key = 'phoenix'
            WHERE repair_template_key IS NULL
              AND (
                LOWER(COALESCE(manufacturer, '')) = 'zelthangar'
                OR LOWER(COALESCE(model, '')) LIKE '%phoenix%'
                OR LOWER(name) LIKE '%phoenix%'
              )
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM repair_template WHERE template_key IN ('spatz', 'phoenix', 'hajk', 'wico')");

        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE material_item
            SET repair_template_key = NULL
            WHERE repair_template_key IN ('spatz', 'phoenix', 'hajk', 'wico')
            SQL
        );
    }
}
