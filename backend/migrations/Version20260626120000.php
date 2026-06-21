<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626120000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'Grossanlass Phase 3: activity_grossanlass_round (Planungsrunden)';
    }

    public function up(Schema $schema): void
    {
        if ($this->prepareNewTable($schema, 'activity_grossanlass_round')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_round (
    id CHARACTER(12) NOT NULL,
    activity_id CHARACTER(12) NOT NULL,
    name VARCHAR(255) NOT NULL,
    round_type VARCHAR(32) NOT NULL DEFAULT 'ressort_wuensche',
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
    opens_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    closes_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    use_auto_schedule BOOLEAN NOT NULL DEFAULT FALSE,
    opened_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_by_user_id CHARACTER(12) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE INDEX idx_grossanlass_round_activity ON activity_grossanlass_round (activity_id)');
            $this->addSql('CREATE INDEX idx_grossanlass_round_status ON activity_grossanlass_round (status)');
            $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_round_activity') THEN
        ALTER TABLE activity_grossanlass_round ADD CONSTRAINT fk_grossanlass_round_activity
            FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
            $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_round_created_by') THEN
        ALTER TABLE activity_grossanlass_round ADD CONSTRAINT fk_grossanlass_round_created_by
            FOREIGN KEY (created_by_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_round DROP CONSTRAINT IF EXISTS fk_grossanlass_round_created_by');
        $this->addSql('ALTER TABLE activity_grossanlass_round DROP CONSTRAINT IF EXISTS fk_grossanlass_round_activity');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_round');
    }
}
