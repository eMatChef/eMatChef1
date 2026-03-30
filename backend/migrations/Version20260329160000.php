<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Status für QR-Kontaktmeldungen (Workflow statt nur „gelesen“).
 */
final class Version20260329160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public_found_item_message.status (open|in_progress|done), backfill from read_at.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE public_found_item_message ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open'");
        $this->addSql("UPDATE public_found_item_message SET status = 'done' WHERE read_at IS NOT NULL");
        $this->addSql('CREATE INDEX idx_pfim_dept_status ON public_found_item_message (department_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_pfim_dept_status');
        $this->addSql('ALTER TABLE public_found_item_message DROP COLUMN status');
    }
}
