<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Beschaffung: mehrere Bestellungen je Position erlauben (Eindeutigkeit je Position entfällt; Zugriff über idx_gpo_line). Keine Daten betroffen.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uq_grossanlass_procurement_order_line');
    }

    public function down(Schema $schema): void
    {
        // Nur möglich, solange je Position höchstens eine Bestellung besteht.
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_grossanlass_procurement_order_line ON activity_grossanlass_procurement_order (procurement_line_id)');
    }
}
