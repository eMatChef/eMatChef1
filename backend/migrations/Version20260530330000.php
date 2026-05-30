<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Medien Paket 4 — Material-Abbildung als photos JSON. */
final class Version20260530330000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add material_item.photos JSON column (media Paket 4).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD photos JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN photos');
    }
}
