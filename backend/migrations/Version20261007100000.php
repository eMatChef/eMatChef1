<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** BusinessClock: Demo-Modus und Zeit-Offset pro Department. */
final class Version20261007100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add department.demo_mode and department.demo_clock_offset_seconds';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department ADD demo_mode BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE department ADD demo_clock_offset_seconds INT DEFAULT NULL');
        // Einmalige Datenmigration: das bisher nur am Namen erkennbare Demo-Grossanlass-Department markieren.
        $this->addSql("UPDATE department SET demo_mode = true WHERE is_grossanlass = true AND name = 'Demo Grossanlass'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department DROP demo_clock_offset_seconds');
        $this->addSql('ALTER TABLE department DROP demo_mode');
    }
}
