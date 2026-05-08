<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rollenmodell-Trennung: Membership-Rollen sa/org/sub auf mw herunterstufen.
 * Globale Admin-Rollen (ROLE_SUPERADMIN, ROLE_ORGANISATIONSCHEF, ROLE_SUBORGCHEF)
 * kommen ausschließlich aus profile.roles, nicht aus Department-Membership.
 */
final class Version20260308220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Downgrade membership roles sa/org/sub to mw (role model separation)';
    }

    public function up(Schema $schema): void
    {
        // Zähle betroffene Einträge vorher (Sicherheitsprüfung)
        $countBefore = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM membership WHERE role IN ('sa', 'org', 'sub')"
        );

        $this->connection->executeStatement(
            "UPDATE membership SET role = 'mw' WHERE role IN ('sa', 'org', 'sub')"
        );

        // Zähle nachher (sollte 0 sein für die alten Rollen)
        $countAfter = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM membership WHERE role IN ('sa', 'org', 'sub')"
        );

        if ($countAfter > 0) {
            throw new \RuntimeException(
                "Migration rollenmodell: Erwartet 0 Einträge mit role IN ('sa','org','sub') nach Update, gefunden: {$countAfter}"
            );
        }
        $this->write("Migriert: {$countBefore} Membership-Einträge von sa/org/sub auf mw heruntergestuft.");
    }

    public function down(Schema $schema): void
    {
        // Down-Migration: Kann nicht zuverlässig rückgängig gemacht werden,
        // da wir nicht wissen, ob ein Eintrag ursprünglich sa, org oder sub war.
        $this->addSql("-- Rollback nicht möglich: ursprüngliche Rolle (sa/org/sub) unbekannt");
    }
}
