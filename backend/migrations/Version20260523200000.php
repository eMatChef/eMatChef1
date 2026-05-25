<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Final cleanup: legacy activity status issued → at_event. */
final class Version20260523200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate remaining activity.status issued to at_event (legacy alias removal).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE activity SET status = 'at_event' WHERE status = 'issued'");
    }

    public function down(Schema $schema): void
    {
        // No rollback — issued is no longer a valid activity status.
    }
}
