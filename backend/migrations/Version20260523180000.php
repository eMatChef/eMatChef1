<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pack-Pipeline: quantity_stored auf activity_pack_item und activity_pack_container_item.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_pack_item ADD quantity_stored INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE activity_pack_container_item ADD quantity_stored INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_pack_item DROP quantity_stored');
        $this->addSql('ALTER TABLE activity_pack_container_item DROP quantity_stored');
    }
}
