<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo-Zusage zsfa7a9edabd: Firma «Maki Landtechnik» war als Artikel erfasst; Artikel ist der Teleskoplader (Fahrzeug). Einsatz, Kosten, Offerte und Anfrage bleiben unverändert.';
    }

    public function up(Schema $schema): void
    {
        // Gezielt und idempotent: greift nur, solange Name = Quelle = Firma (also noch unkorrigiert).
        $this->addSql(<<<'SQL'
            UPDATE department_grossanlass_commitment
            SET name = 'Teleskoplader', family = 'vehicle', quantity = 1, source = 'Maki Landtechnik'
            WHERE id = 'zsfa7a9edabd' AND name = 'Maki Landtechnik' AND source = 'Maki Landtechnik'
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Datenkorrektur, nicht umkehrbar.
    }
}
