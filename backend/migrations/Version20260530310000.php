<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 15 — Entfernen des technischen Global-Departments GLOBAL000000 / GLOBALORG001.
 *
 * Voraussetzung: Paket 0 (address.scope=global) — globale Lieferanten ohne department_id.
 */
final class Version20260530310000 extends AbstractMigration
{
    private const LEGACY_DEPARTMENT_ID = 'GLOBAL000000';
    private const LEGACY_ORGANISATION_ID = 'GLOBALORG001';

    public function getDescription(): string
    {
        return 'Remove legacy GLOBAL000000 department and GLOBALORG001 organisation (Paket 15).';
    }

    public function up(Schema $schema): void
    {
        $dept = self::LEGACY_DEPARTMENT_ID;

        $this->addSql("
            UPDATE address
            SET scope = 'global', department_id = NULL
            WHERE department_id = '{$dept}'
        ");

        $this->addSql("
            UPDATE \"user\"
            SET last_used_department_id = NULL
            WHERE last_used_department_id = '{$dept}'
        ");

        $this->addSql("DELETE FROM membership WHERE department_id = '{$dept}'");

        $this->addSql("DELETE FROM department WHERE id = '{$dept}'");
        $this->addSql("DELETE FROM organisation WHERE id = '" . self::LEGACY_ORGANISATION_ID . "'");
    }

    public function down(Schema $schema): void
    {
        $org = self::LEGACY_ORGANISATION_ID;
        $dept = self::LEGACY_DEPARTMENT_ID;

        $this->addSql("
            INSERT INTO organisation (id, name, created_at, updated_at)
            VALUES ('{$org}', 'Global System', NOW(), NOW())
            ON CONFLICT (id) DO NOTHING
        ");

        $this->addSql("
            INSERT INTO department (id, organisation_id, name, created_at, updated_at, parent_id, billing_address_id)
            VALUES ('{$dept}', '{$org}', 'Global Suppliers', NOW(), NOW(), NULL, NULL)
            ON CONFLICT (id) DO NOTHING
        ");
    }
}
