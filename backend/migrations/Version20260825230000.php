<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Druckkatalog: Global-Antrag (MW) und Prüfung → global verdrahten';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE print_device_model ADD COLUMN IF NOT EXISTS global_requested BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE print_media ADD COLUMN IF NOT EXISTS global_requested BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql("UPDATE print_device_model SET status = 'published', global_requested = TRUE WHERE status = 'pending' AND scope = 'organisation'");
        $this->addSql("UPDATE print_media SET status = 'published', global_requested = TRUE WHERE status = 'pending' AND scope = 'organisation'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE print_device_model DROP COLUMN IF EXISTS global_requested');
        $this->addSql('ALTER TABLE print_media DROP COLUMN IF EXISTS global_requested');
    }
}
