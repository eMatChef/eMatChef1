<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: merkt, welche Wünsche schon in der Anfrage waren';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS asked_wish_ids JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS asked_wish_ids');
    }
}
