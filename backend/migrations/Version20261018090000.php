<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * GA-Helferpool V1: Helfer, Verfügbarkeit, Personalbedarf, Zuteilung, Protokoll.
 *
 * - Department-Isolation über zusammengesetzte Fremdschlüssel (x_id, department_id); dafür bekommt
 *   department_grossanlass_task einen Unique-Index (id, department_id).
 * - Keine Exclusion Constraints, kein btree_gist: Überlappungen (Verfügbarkeit, Doppelbuchung) prüft der
 *   Service transaktional unter Sperre auf dem Helfer, damit begründete Ausnahmen möglich bleiben.
 * - Datenübernahme wiederholbar und ohne Verdopplung: Bedarf wird nur für Aufgaben übernommen, die noch
 *   keinen Eintrag in department_grossanlass_staffing_need haben. Funktionswünsche bleiben als Bedarfszeilen
 *   mit label erhalten; der Rest (Gesamt − Σ Funktionen) wird eine Zeile ohne label. Die Summe je Aufgabe
 *   bleibt max(Gesamt, Σ Funktionen). Die alten Bedarfstabellen bleiben unverändert bestehen (nur Kommentar).
 */
final class Version20261018090000 extends AbstractMigration
{
    /** Zeitfenster der Aufgabe, sonst des Bauprojekts, sonst undatiert (kein künstliches Fenster). */
    private const WINDOW_START = "CASE WHEN t.starts_at IS NOT NULL AND t.duration_minutes > 0 THEN t.starts_at
        WHEN g.window_start IS NOT NULL AND g.window_end IS NOT NULL AND g.window_end >= g.window_start THEN g.window_start::timestamp END";

    private const WINDOW_END = "CASE WHEN t.starts_at IS NOT NULL AND t.duration_minutes > 0 THEN t.starts_at + (t.duration_minutes * INTERVAL '1 minute')
        WHEN g.window_start IS NOT NULL AND g.window_end IS NOT NULL AND g.window_end >= g.window_start THEN (g.window_end + 1)::timestamp END";

    /**
     * Gruppe `g` hat bereits Aufgabenbedarf (neu oder aus dem alten Aufgabenbedarf, auch an einer vorhandenen
     * Standardaufgabe): dann gilt der alte Gruppenbedarf nicht mehr und wird nicht übernommen.
     */
    private const GROUP_HAS_TASK_NEED = "EXISTS (
        SELECT 1 FROM department_grossanlass_task gt
        WHERE gt.group_id = g.id
          AND (EXISTS (SELECT 1 FROM department_grossanlass_staffing_need sn WHERE sn.task_id = gt.id)
            OR EXISTS (SELECT 1 FROM department_grossanlass_task_helper_need tn WHERE tn.task_id = gt.id AND tn.required_count > 0)))";

    public function getDescription(): string
    {
        return 'GA-Helferpool V1: department_grossanlass_helper, _helper_availability, _staffing_need, _staffing_assignment, _staffing_log; Standardaufgabe-Flag; Datenübernahme aus Helferbedarf und membership.is_helper.';
    }

    public function up(Schema $schema): void
    {
        // Ziel für zusammengesetzte Fremdschlüssel.
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_task_id_dept ON department_grossanlass_task (id, department_id)');
        // Standardaufgabe «Allgemein» (lazy, pro Gruppe höchstens eine).
        $this->addSql('ALTER TABLE department_grossanlass_task ADD COLUMN IF NOT EXISTS is_default BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_task_default_per_group ON department_grossanlass_task (group_id) WHERE is_default');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_helper (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            origin_department_id CHARACTER(12) DEFAULT NULL,
            user_id CHARACTER(12) DEFAULT NULL,
            first_name VARCHAR(100) NOT NULL DEFAULT \'\',
            last_name VARCHAR(100) NOT NULL DEFAULT \'\',
            email VARCHAR(180) DEFAULT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            source VARCHAR(10) NOT NULL DEFAULT \'manual\',
            status VARCHAR(10) NOT NULL DEFAULT \'active\',
            archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            note TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_helper_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_helper_origin_dept FOREIGN KEY (origin_department_id) REFERENCES department (id) ON DELETE SET NULL,
            CONSTRAINT fk_ga_helper_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
            CONSTRAINT chk_ga_helper_source CHECK (source IN (\'manual\', \'import\', \'api\')),
            CONSTRAINT chk_ga_helper_status CHECK (status IN (\'active\', \'archived\'))
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_helper_id_dept ON department_grossanlass_helper (id, department_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_helper_user ON department_grossanlass_helper (department_id, user_id) WHERE user_id IS NOT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_helper_dept_status_name ON department_grossanlass_helper (department_id, status, last_name, first_name)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_helper_availability (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            helper_id CHARACTER(12) NOT NULL,
            starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_helper_avail_helper FOREIGN KEY (helper_id, department_id) REFERENCES department_grossanlass_helper (id, department_id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_helper_avail_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE,
            CONSTRAINT chk_ga_helper_avail_window CHECK (ends_at > starts_at)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_helper_avail_helper ON department_grossanlass_helper_availability (helper_id, starts_at, ends_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_helper_avail_dept ON department_grossanlass_helper_availability (department_id, starts_at, ends_at)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_staffing_need (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            task_id CHARACTER(12) NOT NULL,
            label VARCHAR(120) NOT NULL DEFAULT \'\',
            required_count INT NOT NULL DEFAULT 0,
            starts_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            origin VARCHAR(10) NOT NULL DEFAULT \'manual\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_staffing_need_task FOREIGN KEY (task_id, department_id) REFERENCES department_grossanlass_task (id, department_id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_staffing_need_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE,
            CONSTRAINT chk_ga_staffing_need_count CHECK (required_count >= 0),
            CONSTRAINT chk_ga_staffing_need_window CHECK ((starts_at IS NULL AND ends_at IS NULL) OR (starts_at IS NOT NULL AND ends_at IS NOT NULL AND ends_at > starts_at)),
            CONSTRAINT chk_ga_staffing_need_origin CHECK (origin IN (\'manual\', \'migrated\'))
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_staffing_need_id_dept ON department_grossanlass_staffing_need (id, department_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_staffing_need_migrated ON department_grossanlass_staffing_need (task_id, label) WHERE origin = \'migrated\'');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_staffing_need_task ON department_grossanlass_staffing_need (task_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_staffing_need_dept_window ON department_grossanlass_staffing_need (department_id, starts_at, ends_at)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_staffing_assignment (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            need_id CHARACTER(12) NOT NULL,
            helper_id CHARACTER(12) NOT NULL,
            starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'planned\',
            override_reason TEXT DEFAULT NULL,
            override_by_user_id CHARACTER(12) DEFAULT NULL,
            override_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_by_user_id CHARACTER(12) DEFAULT NULL,
            version INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_staffing_assign_need FOREIGN KEY (need_id, department_id) REFERENCES department_grossanlass_staffing_need (id, department_id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_staffing_assign_helper FOREIGN KEY (helper_id, department_id) REFERENCES department_grossanlass_helper (id, department_id),
            CONSTRAINT fk_ga_staffing_assign_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE,
            CONSTRAINT chk_ga_staffing_assign_window CHECK (ends_at > starts_at),
            CONSTRAINT chk_ga_staffing_assign_status CHECK (status IN (\'planned\', \'confirmed\', \'cancelled\')),
            CONSTRAINT chk_ga_staffing_assign_override CHECK ((override_reason IS NULL) = (override_by_user_id IS NULL) AND (override_reason IS NULL) = (override_at IS NULL))
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_staffing_assign_id_dept ON department_grossanlass_staffing_assignment (id, department_id)');
        // Deckung je Bedarf und Konfliktprüfung je Helfer: nur aktive Zuteilungen.
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_staffing_assign_need ON department_grossanlass_staffing_assignment (need_id, starts_at, ends_at) WHERE status <> \'cancelled\'');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_staffing_assign_helper ON department_grossanlass_staffing_assignment (helper_id, starts_at, ends_at) WHERE status <> \'cancelled\'');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_staffing_log (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            assignment_id CHARACTER(12) DEFAULT NULL,
            helper_id CHARACTER(12) NOT NULL,
            action VARCHAR(16) NOT NULL,
            user_id CHARACTER(12) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            details JSON NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_staffing_log_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_staffing_log_dept ON department_grossanlass_staffing_log (department_id, created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_staffing_log_assignment ON department_grossanlass_staffing_log (assignment_id)');

        $this->migrateTaskNeeds();
        $this->migrateGroupNeeds();
        $this->migrateHelperMembers();

        $this->addSql('COMMENT ON TABLE department_grossanlass_task_helper_need IS \'Legacy: ersetzt durch department_grossanlass_staffing_need (Migration 20261018090000); nicht mehr schreiben.\'');
        $this->addSql('COMMENT ON TABLE department_grossanlass_group_helper_need IS \'Legacy: ersetzt durch department_grossanlass_staffing_need (Migration 20261018090000); nicht mehr schreiben.\'');
    }

    /** Aufgabenbedarf: Funktionszeilen + Rest ohne label; Summe je Aufgabe = max(Gesamt, Σ Funktionen). */
    private function migrateTaskNeeds(): void
    {
        $this->addSql(sprintf(<<<'SQL'
WITH agg AS (
    SELECT task_id,
           COALESCE(MAX(required_count) FILTER (WHERE label = ''), 0) AS total_row,
           COALESCE(SUM(required_count) FILTER (WHERE label <> ''), 0) AS fn_sum
    FROM department_grossanlass_task_helper_need
    GROUP BY task_id
), legacy AS (
    SELECT task_id, label, required_count, sort_order
    FROM department_grossanlass_task_helper_need
    WHERE label <> '' AND required_count > 0
    UNION ALL
    SELECT task_id, '', GREATEST(total_row, fn_sum) - fn_sum, 0
    FROM agg
    WHERE GREATEST(total_row, fn_sum) - fn_sum > 0
)
INSERT INTO department_grossanlass_staffing_need
    (id, department_id, task_id, label, required_count, starts_at, ends_at, sort_order, origin, created_at, updated_at)
SELECT 'sn' || substr(md5(random()::text || clock_timestamp()::text || l.task_id || l.label), 1, 10),
       t.department_id, t.id, l.label, l.required_count, %s, %s, l.sort_order, 'migrated', NOW(), NOW()
FROM legacy l
JOIN department_grossanlass_task t ON t.id = l.task_id
JOIN "group" g ON g.id = t.group_id
WHERE NOT EXISTS (SELECT 1 FROM department_grossanlass_staffing_need s WHERE s.task_id = t.id)
SQL, self::WINDOW_START, self::WINDOW_END));
    }

    /** Alter Gruppenbedarf nur, wenn keine Aufgabe der Gruppe Bedarf hat; hängt an der Standardaufgabe «Allgemein». */
    private function migrateGroupNeeds(): void
    {
        $this->addSql(sprintf(<<<'SQL'
INSERT INTO department_grossanlass_task (id, department_id, group_id, title, sort_order, is_default, created_at)
SELECT 'gt' || substr(md5(random()::text || clock_timestamp()::text || g.id), 1, 10),
       g.department_id, g.id, 'Allgemein', 0, TRUE, NOW()
FROM "group" g
WHERE EXISTS (SELECT 1 FROM department_grossanlass_group_helper_need gn WHERE gn.group_id = g.id AND gn.required_count > 0)
  AND NOT %s
  AND NOT EXISTS (SELECT 1 FROM department_grossanlass_task dt WHERE dt.group_id = g.id AND dt.is_default)
SQL, self::GROUP_HAS_TASK_NEED));

        $this->addSql(sprintf(<<<'SQL'
WITH agg AS (
    SELECT group_id,
           COALESCE(MAX(required_count) FILTER (WHERE label = ''), 0) AS total_row,
           COALESCE(SUM(required_count) FILTER (WHERE label <> ''), 0) AS fn_sum
    FROM department_grossanlass_group_helper_need
    GROUP BY group_id
), legacy AS (
    SELECT group_id, label, required_count, sort_order
    FROM department_grossanlass_group_helper_need
    WHERE label <> '' AND required_count > 0
    UNION ALL
    SELECT group_id, '', GREATEST(total_row, fn_sum) - fn_sum, 0
    FROM agg
    WHERE GREATEST(total_row, fn_sum) - fn_sum > 0
)
INSERT INTO department_grossanlass_staffing_need
    (id, department_id, task_id, label, required_count, starts_at, ends_at, sort_order, origin, created_at, updated_at)
SELECT 'sn' || substr(md5(random()::text || clock_timestamp()::text || t.id || l.label), 1, 10),
       t.department_id, t.id, l.label, l.required_count, %s, %s, l.sort_order, 'migrated', NOW(), NOW()
FROM legacy l
JOIN department_grossanlass_task t ON t.group_id = l.group_id AND t.is_default
JOIN "group" g ON g.id = t.group_id
WHERE NOT %s
SQL, self::WINDOW_START, self::WINDOW_END, self::GROUP_HAS_TASK_NEED));
    }

    /** Helfer-Mitglieder (membership.is_helper) in Grossanlass-Departments; Kontakt bleibt über user_id am Konto. */
    private function migrateHelperMembers(): void
    {
        $this->addSql(<<<'SQL'
INSERT INTO department_grossanlass_helper
    (id, department_id, user_id, first_name, last_name, source, status, created_at, updated_at)
SELECT 'gh' || substr(md5(random()::text || clock_timestamp()::text || m.department_id || m.user_id), 1, 10),
       m.department_id, m.user_id, COALESCE(p.first_name, ''), COALESCE(p.last_name, ''), 'manual', 'active', NOW(), NOW()
FROM membership m
JOIN department d ON d.id = m.department_id AND d.is_grossanlass
JOIN "user" u ON u.id = m.user_id
LEFT JOIN profile p ON p.id = u.profile_id
WHERE m.is_helper
ON CONFLICT (department_id, user_id) WHERE user_id IS NOT NULL DO NOTHING
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON TABLE department_grossanlass_task_helper_need IS NULL');
        $this->addSql('COMMENT ON TABLE department_grossanlass_group_helper_need IS NULL');
        // Nur unveränderte Standardaufgaben ohne manuellen Bedarf entfernen; bearbeitete bleiben als normale Aufgabe.
        $this->addSql(<<<'SQL'
DELETE FROM department_grossanlass_task t
WHERE t.is_default
  AND t.description IS NULL AND t.assignee_user_id IS NULL AND t.starts_at IS NULL AND t.duration_minutes IS NULL
  AND NOT EXISTS (SELECT 1 FROM department_grossanlass_task_helper_need n WHERE n.task_id = t.id)
  AND NOT EXISTS (SELECT 1 FROM department_grossanlass_staffing_need s WHERE s.task_id = t.id AND s.origin <> 'migrated')
SQL);
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_staffing_log');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_staffing_assignment');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_staffing_need');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_helper_availability');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_helper');
        $this->addSql('DROP INDEX IF EXISTS uniq_ga_task_default_per_group');
        // Im Neuen Modell erfasster Bedarf und Zuteilungen gehen beim Rollback verloren; die alten Bedarfstabellen sind unverändert.
        $this->addSql('ALTER TABLE department_grossanlass_task DROP COLUMN IF EXISTS is_default');
        $this->addSql('DROP INDEX IF EXISTS uniq_ga_task_id_dept');
    }
}
