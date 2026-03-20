<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rollenmodell-Trennung: Profile.roles für bestehende Admin-User setzen.
 * superadmin@example.com, organisationschef@example.com, suborgchef@example.com
 * erhalten ihre globalen Admin-Rollen in profile.roles.
 */
final class Version20260308230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set profile.roles for existing admin users (superadmin, organisationschef, suborgchef)';
    }

    public function up(Schema $schema): void
    {
        $updates = [
            'superadmin@example.com' => '["ROLE_USER", "ROLE_SUPERADMIN"]',
            'organisationschef@example.com' => '["ROLE_USER", "ROLE_ORGANISATIONSCHEF"]',
            'suborgchef@example.com' => '["ROLE_USER", "ROLE_SUBORGCHEF"]',
        ];

        foreach ($updates as $email => $rolesJson) {
            $this->connection->executeStatement(
                'UPDATE profile SET roles = ?::jsonb WHERE email = ?',
                [$rolesJson, $email]
            );
        }
        $this->write('Profile-Rollen für Admin-User aktualisiert.');
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement(
            "UPDATE profile SET roles = '[\"ROLE_USER\"]'::jsonb WHERE email IN ('superadmin@example.com', 'organisationschef@example.com', 'suborgchef@example.com')"
        );
    }
}
