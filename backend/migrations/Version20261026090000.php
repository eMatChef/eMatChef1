<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Helferpool: department_grossanlass_config.staffing_mw_allowed/staffing_ok_allowed (Verwaltungsrecht für GA-MW bzw. GA-OK, Standard true).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS staffing_mw_allowed BOOLEAN DEFAULT TRUE NOT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS staffing_ok_allowed BOOLEAN DEFAULT TRUE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS staffing_ok_allowed');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS staffing_mw_allowed');
    }
}
