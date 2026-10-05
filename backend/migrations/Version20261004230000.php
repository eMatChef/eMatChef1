<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add MiData department onboarding offers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE midata_department_onboarding (
    id CHAR(12) NOT NULL,
    user_id CHAR(12) NOT NULL,
    provider VARCHAR(32) NOT NULL,
    external_person_id VARCHAR(255) NOT NULL,
    external_department_group_id VARCHAR(255) NOT NULL,
    external_role_class VARCHAR(128) NOT NULL,
    department_name VARCHAR(255) NOT NULL,
    region_name VARCHAR(255) DEFAULT NULL,
    kantonalverband_name VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_midata_onboarding_user_group ON midata_department_onboarding (user_id, provider, external_department_group_id)');
        $this->addSql('ALTER TABLE midata_department_onboarding ADD CONSTRAINT fk_midata_onboarding_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE midata_department_onboarding');
    }
}
