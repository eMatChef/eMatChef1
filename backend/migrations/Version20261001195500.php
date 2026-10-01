<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001195500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Weitere Mailadressen am User und Empfängeradresse pro Department-Mitgliedschaft';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership ADD COLUMN IF NOT EXISTS notification_email VARCHAR(180) DEFAULT NULL');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_email_alias (
    id CHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    email VARCHAR(180) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_user_email_alias_email ON user_email_alias (email)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_email_alias_user ON user_email_alias (user_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE user_email_alias
        ADD CONSTRAINT fk_user_email_alias_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_email_alias DROP CONSTRAINT IF EXISTS fk_user_email_alias_user');
        $this->addSql('DROP TABLE IF EXISTS user_email_alias');
        $this->addSql('ALTER TABLE membership DROP COLUMN IF EXISTS notification_email');
    }
}
