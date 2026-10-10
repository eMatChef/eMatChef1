<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Demo-Seed-Ledger: Hash der zuletzt vom Seed geschriebenen Stammdaten und Katalogversion (rein additiv). */
final class Version20261008110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add demo_seed_record.managed_hash and catalog_version';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demo_seed_record ADD managed_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE demo_seed_record ADD catalog_version VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demo_seed_record DROP catalog_version');
        $this->addSql('ALTER TABLE demo_seed_record DROP managed_hash');
    }
}
