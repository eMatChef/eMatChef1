<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 6 — Supplier join_code für Team-Beitritt.
 */
final class Version20260530160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier portal Paket 6: supplier_company.join_code for team invites.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_company ADD join_code VARCHAR(8) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_company_join_code ON supplier_company (join_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_supplier_company_join_code');
        $this->addSql('ALTER TABLE supplier_company DROP COLUMN IF EXISTS join_code');
    }
}
