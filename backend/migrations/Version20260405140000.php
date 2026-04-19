<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Kennzeichnet, ob der Erstellungs-Wizard (Stepper) abgeschlossen wurde.
 */
final class Version20260405140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activity.create_wizard_completed (default true)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS create_wizard_completed BOOLEAN NOT NULL DEFAULT true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS create_wizard_completed');
    }
}
