<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * refresh_tokens.id nutzt Doctrine AUTO (= PostgreSQL-Sequenz). Adminer-Dump / manuelles Schema
 * legte die Tabelle ohne Sequenz an → Login 500: relation "refresh_tokens_id_seq" does not exist.
 *
 * Fehlt die Tabelle komplett (frische DB ohne ältere gesdinet-Migration), wird sie hier angelegt
 * (entspr. Gesdinet\JWTRefreshTokenBundle Entity / RefreshToken.orm.xml).
 */
final class Version20260324213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure refresh_tokens exists; add refresh_tokens_id_seq and default for id (Gesdinet JWT refresh).';
    }

    public function up(Schema $schema): void
    {
        // Direkt auf der Connection ausführen (nicht nur addSql-Queue), damit die Tabelle sicher existiert,
        // bevor die folgenden addSql-Statements laufen (vermeidet „relation refresh_tokens does not exist“).
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS public.refresh_tokens ('
            . 'id INT NOT NULL, '
            . 'refresh_token VARCHAR(128) NOT NULL, '
            . 'username VARCHAR(255) NOT NULL, '
            . 'valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            . 'PRIMARY KEY(id)'
            . ')'
        );
        $this->connection->executeStatement(
            'CREATE UNIQUE INDEX IF NOT EXISTS uniq_refresh_tokens_refresh_token ON public.refresh_tokens (refresh_token)'
        );

        $this->addSql('CREATE SEQUENCE IF NOT EXISTS refresh_tokens_id_seq');
        $this->addSql('ALTER TABLE public.refresh_tokens ALTER COLUMN id SET DEFAULT nextval(\'refresh_tokens_id_seq\')');
        $this->addSql('ALTER SEQUENCE refresh_tokens_id_seq OWNED BY public.refresh_tokens.id');
        // Leere Tabelle: setval mit Wert 0 ist in PG ungültig — expliziter DO-Block (robust gegen alte Parser-/Bind-Pfade).
        $this->addSql(<<<'SQL'
DO $sync$
DECLARE
    row_count bigint;
    max_id int;
BEGIN
    SELECT COUNT(*) INTO row_count FROM public.refresh_tokens;
    IF row_count = 0 THEN
        PERFORM setval('public.refresh_tokens_id_seq'::regclass, 1, false);
    ELSE
        SELECT MAX(id) INTO max_id FROM public.refresh_tokens;
        PERFORM setval('public.refresh_tokens_id_seq'::regclass, GREATEST(1, COALESCE(max_id, 1)), true);
    END IF;
END $sync$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE public.refresh_tokens ALTER COLUMN id DROP DEFAULT');
        $this->addSql('DROP SEQUENCE IF EXISTS refresh_tokens_id_seq');
    }
}
