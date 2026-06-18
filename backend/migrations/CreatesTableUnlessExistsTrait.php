<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Deploy-sicher: fehlgeschlagene/teilweise Migrationen hinterlassen in PostgreSQL
 * oft einen Composite-Type ohne Tabelle (pg_type_typname_nsp_index bei Retry).
 */
trait CreatesTableUnlessExistsTrait
{
    protected function prepareNewTable(Schema $schema, string $table): bool
    {
        if ($schema->hasTable($table)) {
            return false;
        }

        $this->addSql(sprintf('DROP TYPE IF EXISTS %s CASCADE', $table));

        return true;
    }
}
