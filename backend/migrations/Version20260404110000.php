<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Freitext-Kontaktfelder an activity entfernen (Kontakt über address_id / venue_address_id).
 */
final class Version20260404110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop activity.customer_name, customer_email, customer_phone';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP COLUMN customer_name');
        $this->addSql('ALTER TABLE activity DROP COLUMN customer_email');
        $this->addSql('ALTER TABLE activity DROP COLUMN customer_phone');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD customer_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD customer_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD customer_phone VARCHAR(50) DEFAULT NULL');
    }
}
