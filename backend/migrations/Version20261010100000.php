<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Verknüpfte Anmeldungen: Anzeigename des externen Kontos (rein additiv, bestehende Zeilen bleiben NULL). */
final class Version20261010100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external_identity.display_name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_identity ADD display_name VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_identity DROP display_name');
    }
}
