<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fügt die group_id Spalte zur activity Tabelle hinzu (Sub-Department/Gruppe).
 */
final class Version20260208200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds group_id column to activity table referencing the group table.';
    }

    public function up(Schema $schema): void
    {
        // Fehlte historisch im Repo — FK auf "group" braucht die Tabelle (siehe Entity Group / GroupMembership).
        // `group` ist reserviert: information_schema.table_name ist hier unzuverlässig — pg_catalog.to_regclass nutzen.
        $groupRegclass = $this->connection->fetchOne("SELECT to_regclass('public.\"group\"')");
        if ($groupRegclass === null || $groupRegclass === false || $groupRegclass === '') {
            $this->addSql('CREATE TABLE public."group" (id CHARACTER(12) NOT NULL, department_id CHARACTER(12) NOT NULL, name VARCHAR(255) NOT NULL, parent_id CHARACTER(12) DEFAULT NULL, sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX idx_group_department ON public."group" (department_id)');
            $this->addSql('CREATE INDEX idx_group_parent ON public."group" (parent_id)');
            $this->addSql('ALTER TABLE public."group" ADD CONSTRAINT FK_group_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE public."group" ADD CONSTRAINT FK_group_parent FOREIGN KEY (parent_id) REFERENCES public."group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        }

        $gmRegclass = $this->connection->fetchOne("SELECT to_regclass('public.group_membership')");
        if ($gmRegclass === null || $gmRegclass === false || $gmRegclass === '') {
            $this->addSql('CREATE TABLE public.group_membership (user_id CHARACTER(12) NOT NULL, group_id CHARACTER(12) NOT NULL, role VARCHAR(20) NOT NULL, is_primary BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(user_id, group_id))');
            $this->addSql('CREATE INDEX idx_gm_group ON public.group_membership (group_id)');
            $this->addSql('CREATE INDEX idx_gm_user ON public.group_membership (user_id)');
            $this->addSql('ALTER TABLE public.group_membership ADD CONSTRAINT FK_gm_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE public.group_membership ADD CONSTRAINT FK_gm_group FOREIGN KEY (group_id) REFERENCES public."group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        }

        $this->addSql('ALTER TABLE activity ADD COLUMN group_id CHAR(12) NULL');
        $this->addSql('CREATE INDEX idx_activity_group ON activity (group_id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AFE54D947 FOREIGN KEY (group_id) REFERENCES public."group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AFE54D947');
        $this->addSql('DROP INDEX idx_activity_group');
        $this->addSql('ALTER TABLE activity DROP COLUMN group_id');
    }
}
