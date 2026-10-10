<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Display pairing requests: short-lived QR pairing of a TV with an existing infoscreen.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE display_pairing_request (
    id CHAR(12) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    poll_secret_hash CHAR(64) NOT NULL,
    user_code VARCHAR(8) NOT NULL,
    status VARCHAR(16) NOT NULL,
    screen_id CHAR(12) DEFAULT NULL,
    approved_by_user_id CHAR(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_display_pairing_token_hash ON display_pairing_request (token_hash)');
        $this->addSql('CREATE INDEX idx_display_pairing_expires ON display_pairing_request (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE display_pairing_request');
    }
}
