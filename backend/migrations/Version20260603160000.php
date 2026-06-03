<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting booking receipt attachments (JSON, var/uploads/accounting/).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_booking ADD COLUMN IF NOT EXISTS receipts JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_booking DROP COLUMN IF EXISTS receipts');
    }
}
