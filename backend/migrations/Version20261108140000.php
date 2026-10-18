<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Matplatz: stabile Zuordnung zu Lageradresse (storage_address_id) und Demo-Bestandsverknüpfung.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_place ADD storage_address_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_place ADD CONSTRAINT FK_ga_place_storage_address FOREIGN KEY (storage_address_id) REFERENCES address (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_ga_place_storage_address ON department_grossanlass_place (storage_address_id) WHERE (storage_address_id IS NOT NULL)');

        $this->addSql("UPDATE department_grossanlass_place SET storage_address_id = '70020a03680a' WHERE id = 'plf873a2b455' AND storage_address_id IS NULL");
        $this->addSql("UPDATE department_grossanlass_place SET storage_address_id = '4b1446b6054c' WHERE id = 'plce35e2f40a' AND storage_address_id IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_place DROP CONSTRAINT FK_ga_place_storage_address');
        $this->addSql('DROP INDEX uniq_ga_place_storage_address');
        $this->addSql('ALTER TABLE department_grossanlass_place DROP storage_address_id');
    }
}
