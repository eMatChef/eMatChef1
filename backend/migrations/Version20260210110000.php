<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adressfelder optional machen (street, postal_code, city)
 * Eventstandorte brauchen nur GPS-Koordinaten, keine Postadresse
 */
final class Version20260210110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make street, postal_code, city nullable in address table (for event locations with GPS only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ALTER COLUMN street DROP NOT NULL');
        $this->addSql('ALTER TABLE address ALTER COLUMN postal_code DROP NOT NULL');
        $this->addSql('ALTER TABLE address ALTER COLUMN city DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE address SET street = '' WHERE street IS NULL");
        $this->addSql("UPDATE address SET postal_code = '' WHERE postal_code IS NULL");
        $this->addSql("UPDATE address SET city = '' WHERE city IS NULL");
        $this->addSql('ALTER TABLE address ALTER COLUMN street SET NOT NULL');
        $this->addSql('ALTER TABLE address ALTER COLUMN postal_code SET NOT NULL');
        $this->addSql('ALTER TABLE address ALTER COLUMN city SET NOT NULL');
    }
}
