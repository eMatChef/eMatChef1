<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 0 — Address-Scope-Modell (M1).
 *
 * - scope: department | supplier | global
 * - supplier_company_id (nullable, FK in Paket 1)
 * - department_id nullable für scope=global
 * - Migration GLOBAL000000 → scope=global
 */
final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Address scope model: scope column, nullable department_id, migrate GLOBAL000000 suppliers to scope=global (Paket 0).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE address ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'department'");
        $this->addSql('ALTER TABLE address ADD COLUMN supplier_company_id CHARACTER(12) DEFAULT NULL');

        $this->addSql('ALTER TABLE address ALTER COLUMN department_id DROP NOT NULL');

        $this->addSql("
            UPDATE address
            SET scope = 'global', department_id = NULL
            WHERE department_id = 'GLOBAL000000'
        ");

        $this->addSql("
            ALTER TABLE address ADD CONSTRAINT chk_address_scope_context CHECK (
                (scope = 'department' AND department_id IS NOT NULL AND supplier_company_id IS NULL)
                OR
                (scope = 'global' AND department_id IS NULL AND supplier_company_id IS NULL)
                OR
                (scope = 'supplier' AND supplier_company_id IS NOT NULL AND department_id IS NULL)
            )
        ");

        $this->addSql('CREATE INDEX idx_address_scope ON address (scope)');
        $this->addSql('CREATE INDEX idx_address_scope_type ON address (scope, type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_address_scope_type');
        $this->addSql('DROP INDEX IF EXISTS idx_address_scope');
        $this->addSql('ALTER TABLE address DROP CONSTRAINT IF EXISTS chk_address_scope_context');

        $this->addSql("
            UPDATE address
            SET department_id = 'GLOBAL000000'
            WHERE scope = 'global' AND department_id IS NULL
        ");

        $this->addSql("UPDATE address SET scope = 'department' WHERE scope = 'global'");

        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS supplier_company_id');
        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS scope');

        $this->addSql('ALTER TABLE address ALTER COLUMN department_id SET NOT NULL');
    }
}
