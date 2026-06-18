<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'J+S-Bestellung: Coach-Übergabe, E-Mail-Versand, Retourprobe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_js_order ADD COLUMN IF NOT EXISTS submitted_to_coach_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_js_order ADD COLUMN IF NOT EXISTS submitted_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_js_order ADD COLUMN IF NOT EXISTS coach_email_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_js_order ADD COLUMN IF NOT EXISTS return_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_js_order DROP COLUMN IF EXISTS return_confirmed_at');
        $this->addSql('ALTER TABLE activity_js_order DROP COLUMN IF EXISTS coach_email_sent_at');
        $this->addSql('ALTER TABLE activity_js_order DROP COLUMN IF EXISTS submitted_by_user_id');
        $this->addSql('ALTER TABLE activity_js_order DROP COLUMN IF EXISTS submitted_to_coach_at');
    }
}
