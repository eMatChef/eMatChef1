<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Sicherheitsprotokoll: IP und User-Agent strukturiert am Audit-Event (rein additiv, historische Zeilen bleiben NULL). */
final class Version20261009100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit_event.ip_address and user_agent';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_event ADD ip_address VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_event ADD user_agent VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_event DROP user_agent');
        $this->addSql('ALTER TABLE audit_event DROP ip_address');
    }
}
