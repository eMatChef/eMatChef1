<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Eingangsbereich ist ein Bereich, auch unter einem Bereich';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE \"group\"
            SET grossanlass_kind = 'bereich'
            WHERE name = 'Eingangsbereich'
              AND grossanlass_kind = 'teilbereich'");
    }

    public function down(Schema $schema): void
    {
    }
}
