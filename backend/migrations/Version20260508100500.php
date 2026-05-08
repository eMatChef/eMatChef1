<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508100500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create security_alert_event table for persisted security alert history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE security_alert_event (id CHARACTER(13) NOT NULL, alert_type VARCHAR(64) NOT NULL, severity VARCHAR(16) NOT NULL, source_key VARCHAR(190) NOT NULL, window_minutes INT NOT NULL, event_count INT NOT NULL, ip_address VARCHAR(64) DEFAULT NULL, identifier VARCHAR(190) DEFAULT NULL, path VARCHAR(190) NOT NULL, status_code INT DEFAULT NULL, context JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_security_alert_created ON security_alert_event (created_at)');
        $this->addSql('CREATE INDEX idx_security_alert_type_created ON security_alert_event (alert_type, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE security_alert_event');
    }
}

