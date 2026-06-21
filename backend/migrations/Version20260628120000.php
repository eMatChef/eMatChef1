<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628120000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'Grossanlass Phase 4: activity_grossanlass_wish_line (Materialwünsche pro Runde/Bauprojekt)';
    }

    public function up(Schema $schema): void
    {
        if ($this->prepareNewTable($schema, 'activity_grossanlass_wish_line')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_wish_line (
    id CHARACTER(12) NOT NULL,
    round_id CHARACTER(12) NOT NULL,
    group_id CHARACTER(12) NOT NULL,
    wish_kind VARCHAR(20) NOT NULL,
    label VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    location VARCHAR(255) NOT NULL,
    valid_from TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    valid_to TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    timeframe_notes TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'requested',
    created_by_user_id CHARACTER(12) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE INDEX idx_grossanlass_wish_round ON activity_grossanlass_wish_line (round_id)');
            $this->addSql('CREATE INDEX idx_grossanlass_wish_group ON activity_grossanlass_wish_line (group_id)');
            $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_round') THEN
        ALTER TABLE activity_grossanlass_wish_line ADD CONSTRAINT fk_grossanlass_wish_round
            FOREIGN KEY (round_id) REFERENCES activity_grossanlass_round (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
            $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_group') THEN
        ALTER TABLE activity_grossanlass_wish_line ADD CONSTRAINT fk_grossanlass_wish_group
            FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
            $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_wish_created_by') THEN
        ALTER TABLE activity_grossanlass_wish_line ADD CONSTRAINT fk_grossanlass_wish_created_by
            FOREIGN KEY (created_by_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_created_by');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_group');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP CONSTRAINT IF EXISTS fk_grossanlass_wish_round');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_wish_line');
    }
}
