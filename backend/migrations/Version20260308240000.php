<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AdminJoinRequest: requested_organisation_id für Org-Filter (SA/ORG/SUB).
 * AdminJoinRequestEvent: History-Logs für create/assign/reject.
 */
final class Version20260308240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add requested_organisation_id to admin_join_request, create admin_join_request_event for history logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_join_request ADD requested_organisation_id CHAR(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_ajr_requested_org ON admin_join_request (requested_organisation_id)');

        $this->addSql('CREATE TABLE admin_join_request_event (
            id CHAR(12) NOT NULL,
            admin_join_request_id CHAR(12) NOT NULL,
            user_id CHAR(12) NOT NULL,
            action VARCHAR(32) NOT NULL,
            payload JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_ajre_request ON admin_join_request_event (admin_join_request_id)');
        $this->addSql('CREATE INDEX idx_ajre_user ON admin_join_request_event (user_id)');
        $this->addSql('CREATE INDEX idx_ajre_created ON admin_join_request_event (created_at)');
        $this->addSql('ALTER TABLE admin_join_request_event ADD CONSTRAINT fk_ajre_request FOREIGN KEY (admin_join_request_id) REFERENCES admin_join_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE admin_join_request_event ADD CONSTRAINT fk_ajre_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_join_request_event');
        $this->addSql('DROP INDEX idx_ajr_requested_org');
        $this->addSql('ALTER TABLE admin_join_request DROP requested_organisation_id');
    }
}
