<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605150000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'Plattform-Stamm repair_template + material_item.repair_template_key.';
    }

    public function up(Schema $schema): void
    {
        if ($this->prepareNewTable($schema, 'repair_template')) {
            $this->addSql(<<<'SQL'
            CREATE TABLE repair_template (
                id CHARACTER(12) NOT NULL,
                template_key VARCHAR(50) NOT NULL,
                name VARCHAR(160) NOT NULL,
                material_class VARCHAR(30) DEFAULT 'tent' NOT NULL,
                structure_json JSON NOT NULL,
                diagram_json JSON DEFAULT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
            $this->addSql('CREATE UNIQUE INDEX uniq_repair_template_key ON repair_template (template_key)');
            $this->addSql('CREATE INDEX idx_repair_template_active ON repair_template (is_active)');
        }

        $this->addSql('ALTER TABLE material_item ADD COLUMN IF NOT EXISTS repair_template_key VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_material_repair_template_key ON material_item (repair_template_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_material_repair_template_key');
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS repair_template_key');
        $this->addSql('DROP TABLE IF EXISTS repair_template');
    }
}
