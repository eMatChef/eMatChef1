<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unverified MiData membership candidates for the onboarding search mode';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE midata_membership_candidate (
    id CHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    provider VARCHAR(32) NOT NULL,
    external_group_id VARCHAR(255) NOT NULL,
    external_role_class VARCHAR(128) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_midata_candidate_user_group ON midata_membership_candidate (user_id, provider, external_group_id)');
        $this->addSql('ALTER TABLE midata_membership_candidate ADD CONSTRAINT fk_midata_candidate_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE midata_membership_candidate');
    }
}
