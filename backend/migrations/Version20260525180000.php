<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'material_item: Packmaß (Gewicht und Abmessungen der Verpackungseinheit).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD pack_weight VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_item ADD pack_size_length VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_item ADD pack_size_width VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_item ADD pack_size_height VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP pack_size_height');
        $this->addSql('ALTER TABLE material_item DROP pack_size_width');
        $this->addSql('ALTER TABLE material_item DROP pack_size_length');
        $this->addSql('ALTER TABLE material_item DROP pack_weight');
    }
}
