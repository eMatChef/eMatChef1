<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Ort kind: matplatz → lagerplatz (Lagerstandort)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE department_grossanlass_place SET kind = 'lagerplatz' WHERE kind = 'matplatz'");
        $this->addSql(
            "UPDATE department_display_layout_revision SET layout = REPLACE(layout::text, '\"matplatz\"', '\"lagerplatz\"')::json WHERE layout::text LIKE '%\"matplatz\"%'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE department_grossanlass_place SET kind = 'matplatz' WHERE kind = 'lagerplatz'");
        $this->addSql(
            "UPDATE department_display_layout_revision SET layout = REPLACE(layout::text, '\"lagerplatz\"', '\"matplatz\"')::json WHERE layout::text LIKE '%\"lagerplatz\"%'",
        );
    }
}
