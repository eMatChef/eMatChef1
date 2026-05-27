<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * profile.admin_capabilities für konfigurierbare Org-/Suborg-Rechte.
 */
final class Version20260526200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile.admin_capabilities JSON for org/sub admin permission overrides';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD admin_capabilities JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP admin_capabilities');
    }
}
