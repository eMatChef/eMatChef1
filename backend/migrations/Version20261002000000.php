<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external identity records and migrate legacy Google accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS external_identity (
    id CHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    provider VARCHAR(32) NOT NULL,
    external_user_id VARCHAR(255) NOT NULL,
    email VARCHAR(180) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_external_identity_provider_external_user_id ON external_identity (provider, external_user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_external_identity_user ON external_identity (user_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE external_identity
        ADD CONSTRAINT fk_external_identity_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO external_identity (id, user_id, provider, external_user_id, email, created_at, updated_at)
SELECT
    substr(md5(random()::text || u.id || 'google' || COALESCE(u.google_id, '') || clock_timestamp()::text), 1, 12) AS id,
    u.id AS user_id,
    'google' AS provider,
    u.google_id AS external_user_id,
    p.email,
    NOW() AS created_at,
    NOW() AS updated_at
FROM "user" u
LEFT JOIN profile p ON p.id = u.profile_id
WHERE u.google_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM external_identity ei
      WHERE ei.provider = 'google'
        AND ei.external_user_id = u.google_id
  );
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_identity DROP CONSTRAINT IF EXISTS fk_external_identity_user');
        $this->addSql('DROP TABLE IF EXISTS external_identity');
    }
}
