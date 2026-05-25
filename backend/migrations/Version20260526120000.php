<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity_item: created_by_user_id und submitter_department_id (Nachlieferung / Provenance).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_item ADD created_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_item ADD submitter_department_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_activity_item_submitter_dept ON activity_item (submitter_department_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_activity_item_submitter_dept');
        $this->addSql('ALTER TABLE activity_item DROP submitter_department_id');
        $this->addSql('ALTER TABLE activity_item DROP created_by_user_id');
    }
}
