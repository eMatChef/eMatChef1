<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting follow-ups: link to activities (consumption, loss, replenishment, workshop).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD activity_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD source_kind VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD source_ref_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD material_item_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_aafu_activity ON accounting_acquisition_follow_up (activity_id)');
        $this->addSql('CREATE INDEX idx_aafu_source ON accounting_acquisition_follow_up (source_kind, source_ref_id)');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD CONSTRAINT FK_AAFU_ACTIVITY FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD CONSTRAINT FK_AAFU_MATERIAL_ITEM FOREIGN KEY (material_item_id) REFERENCES material_item (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up DROP CONSTRAINT FK_AAFU_MATERIAL_ITEM');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up DROP CONSTRAINT FK_AAFU_ACTIVITY');
        $this->addSql('DROP INDEX idx_aafu_source');
        $this->addSql('DROP INDEX idx_aafu_activity');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up DROP material_item_id');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up DROP source_ref_id');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up DROP source_kind');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up DROP activity_id');
    }
}
