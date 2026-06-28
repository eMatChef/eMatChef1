<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Workflow-Vereinheitlichung: pack_journey_step → activity.status; neue Status transport_out, transport_back, storing.
 */
final class Version20260704120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activity: migrate pack_journey_step to status; drop pack_journey_step column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE activity SET status = 'transport_out'
            WHERE pack_journey_step = 'transport_out' AND status = 'packed'
        ");
        $this->addSql("
            UPDATE activity SET status = 'at_event'
            WHERE pack_journey_step = 'issue' AND status IN ('packed', 'transport_out')
        ");
        $this->addSql("
            UPDATE activity SET status = 'transport_back'
            WHERE pack_journey_step = 'transport_back' AND status = 'at_event'
        ");
        $this->addSql("
            UPDATE activity SET status = 'storing'
            WHERE pack_journey_step = 'store' AND status = 'returned'
        ");

        $this->addSql('ALTER TABLE activity DROP pack_journey_step');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD pack_journey_step VARCHAR(32) DEFAULT NULL');

        $this->addSql("
            UPDATE activity SET pack_journey_step = 'pack' WHERE status = 'packing'
        ");
        $this->addSql("
            UPDATE activity SET pack_journey_step = 'transport_out'
            WHERE status = 'transport_out' OR (status = 'packed' AND type IN ('camp', 'event'))
        ");
        $this->addSql("
            UPDATE activity SET pack_journey_step = 'issue'
            WHERE status IN ('at_event', 'packed') AND type IN ('activity', 'external')
        ");
        $this->addSql("
            UPDATE activity SET pack_journey_step = 'issue' WHERE status = 'at_event' AND type IN ('camp', 'event')
        ");
        $this->addSql("
            UPDATE activity SET pack_journey_step = 'transport_back' WHERE status = 'transport_back'
        ");
        $this->addSql("
            UPDATE activity SET pack_journey_step = 'return' WHERE status = 'returned'
        ");
        $this->addSql("
            UPDATE activity SET pack_journey_step = 'store' WHERE status = 'storing'
        ");
    }
}
