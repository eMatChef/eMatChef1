<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * refresh_tokens.id nutzt Doctrine AUTO (= PostgreSQL-Sequenz). Adminer-Dump / manuelles Schema
 * legte die Tabelle ohne Sequenz an → Login 500: relation "refresh_tokens_id_seq" does not exist.
 */
final class Version20260324213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh_tokens_id_seq and default for refresh_tokens.id (Gesdinet JWT refresh).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS refresh_tokens_id_seq');
        $this->addSql('ALTER TABLE refresh_tokens ALTER COLUMN id SET DEFAULT nextval(\'refresh_tokens_id_seq\')');
        $this->addSql('ALTER SEQUENCE refresh_tokens_id_seq OWNED BY refresh_tokens.id');
        $this->addSql("SELECT setval('refresh_tokens_id_seq', (SELECT COALESCE(MAX(id), 0) FROM refresh_tokens), true)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens ALTER COLUMN id DROP DEFAULT');
        $this->addSql('DROP SEQUENCE IF EXISTS refresh_tokens_id_seq');
    }
}
