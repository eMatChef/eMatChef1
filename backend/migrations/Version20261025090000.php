<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Bauauftrag archivieren statt löschen: "group".archived_at/archived_by_user_id/archive_reason und Protokoll department_grossanlass_order_archive_log. Kein bestehender Bauauftrag gilt als archiviert.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "group" ADD COLUMN IF NOT EXISTS archived_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE "group" ADD COLUMN IF NOT EXISTS archive_reason TEXT DEFAULT NULL');
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_order_archive_log (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            group_id CHARACTER(12) NOT NULL,
            type VARCHAR(16) NOT NULL,
            user_id CHARACTER(12) NOT NULL,
            note TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_order_archive_log_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_order_archive_log_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_order_archive_log_group ON department_grossanlass_order_archive_log (group_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_order_archive_log');
        $this->addSql('ALTER TABLE "group" DROP COLUMN IF EXISTS archive_reason');
        $this->addSql('ALTER TABLE "group" DROP COLUMN IF EXISTS archived_by_user_id');
        $this->addSql('ALTER TABLE "group" DROP COLUMN IF EXISTS archived_at');
    }
}
