<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605500000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workshop Paket 21: phase/status abgleichen; Infoscreen-Filter auf Phasen umstellen.';
    }

    public function up(Schema $schema): void
    {
        // Triage: offen ohne abgeschlossene Phase
        $this->addSql("
            UPDATE workshop_ticket
            SET strategy = 'triage', phase = NULL
            WHERE status = 'open'
              AND (strategy IS DISTINCT FROM 'triage' OR phase IS NOT NULL)
        ");

        // Terminal
        $this->addSql("
            UPDATE workshop_ticket SET phase = 'completed'
            WHERE status = 'completed' AND (phase IS NULL OR phase <> 'completed')
        ");
        $this->addSql("
            UPDATE workshop_ticket SET phase = 'cancelled'
            WHERE status = 'cancelled' AND (phase IS NULL OR phase <> 'cancelled')
        ");

        // Wartet auf Teile → phase
        $this->addSql("
            UPDATE workshop_ticket SET phase = 'awaiting_quote'
            WHERE status = 'waiting_parts'
              AND assigned_to_supplier_company_id IS NOT NULL
              AND phase IS DISTINCT FROM 'awaiting_quote'
        ");
        $this->addSql("
            UPDATE workshop_ticket SET phase = 'ordered'
            WHERE status = 'waiting_parts'
              AND assigned_to_supplier_company_id IS NULL
              AND phase NOT IN ('ordered', 'awaiting_quote')
        ");

        // In Arbeit ohne Phase
        $this->addSql("
            UPDATE workshop_ticket SET phase = 'in_progress'
            WHERE status = 'in_progress'
              AND strategy <> 'triage'
              AND phase IS NULL
        ");

        // Phase → Legacy status (Supplier-Portal)
        $this->addSql("
            UPDATE workshop_ticket SET status = 'waiting_parts'
            WHERE phase = 'awaiting_quote'
              AND status NOT IN ('completed', 'cancelled', 'waiting_parts')
        ");
        $this->addSql("
            UPDATE workshop_ticket SET status = 'in_progress'
            WHERE phase IN ('planning', 'ordered', 'ready', 'in_progress')
              AND status NOT IN ('completed', 'cancelled', 'in_progress')
        ");

        // Infoscreen: workshop_statuses Alt-Werte → Phasen
        $this->addSql("
            UPDATE department_display_screen
            SET workshop_statuses = (
                SELECT COALESCE(json_agg(mapped.elem ORDER BY mapped.ord), '[]'::json)
                FROM (
                    SELECT
                        CASE elem::text
                            WHEN '\"open\"' THEN 'triage'
                            WHEN '\"in_progress\"' THEN 'in_progress'
                            WHEN '\"waiting_parts\"' THEN 'awaiting_quote'
                            WHEN '\"completed\"' THEN 'completed'
                            WHEN '\"cancelled\"' THEN 'cancelled'
                            ELSE trim(both '\"' from elem::text)
                        END AS elem,
                        ord
                    FROM json_array_elements(workshop_statuses) WITH ORDINALITY AS t(elem, ord)
                ) mapped
            )
            WHERE workshop_statuses::text LIKE '%open%'
               OR workshop_statuses::text LIKE '%waiting_parts%'
        ");

        $this->addSql("
            UPDATE department_display_screen
            SET workshop_statuses = '[\"triage\",\"planning\",\"in_progress\",\"awaiting_quote\"]'::json
            WHERE workshop_statuses::text = '[]'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE department_display_screen
            SET workshop_statuses = (
                SELECT COALESCE(json_agg(mapped.elem ORDER BY mapped.ord), '[]'::json)
                FROM (
                    SELECT
                        CASE elem::text
                            WHEN '\"triage\"' THEN 'open'
                            WHEN '\"planning\"' THEN 'open'
                            WHEN '\"ordered\"' THEN 'in_progress'
                            WHEN '\"ready\"' THEN 'in_progress'
                            WHEN '\"awaiting_quote\"' THEN 'waiting_parts'
                            ELSE trim(both '\"' from elem::text)
                        END AS elem,
                        ord
                    FROM json_array_elements(workshop_statuses) WITH ORDINALITY AS t(elem, ord)
                ) mapped
            )
        ");
    }
}
