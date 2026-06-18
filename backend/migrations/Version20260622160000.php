<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'address.parent_id — Zustellpunkt als Kind eines Eventstandorts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD COLUMN IF NOT EXISTS parent_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_address_parent ON address (parent_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_address_parent') THEN
        ALTER TABLE address ADD CONSTRAINT fk_address_parent
            FOREIGN KEY (parent_id) REFERENCES address (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP CONSTRAINT IF EXISTS fk_address_parent');
        $this->addSql('DROP INDEX IF EXISTS idx_address_parent');
        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS parent_id');
    }
}
