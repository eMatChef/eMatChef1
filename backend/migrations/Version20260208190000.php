<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Erstellt die activity_item Tabelle (Material ↔ Aktivität Verknüpfung)
 * und die DB-Funktion get_materials_available_for_period für Verfügbarkeits-Prüfung.
 */
final class Version20260208190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates activity_item table and get_materials_available_for_period DB function.';
    }

    public function up(Schema $schema): void
    {
        // Historisches Duplikat von Version20260208180000 — auf frischer DB läuft 08180000 zuerst.
        if ($this->sm->tablesExist(['activity_item'])) {
            return;
        }

        // === 1. activity_item Tabelle ===
        $this->addSql('CREATE TABLE activity_item (
            id CHAR(13) NOT NULL, 
            activity_id CHAR(12) NOT NULL, 
            material_item_id CHAR(12) NOT NULL, 
            quantity INT DEFAULT 1 NOT NULL, 
            priority VARCHAR(20) DEFAULT \'normal\' NOT NULL, 
            status VARCHAR(20) DEFAULT \'requested\' NOT NULL, 
            notes TEXT DEFAULT NULL, 
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_activity_item_activity ON activity_item (activity_id)');
        $this->addSql('CREATE INDEX idx_activity_item_material ON activity_item (material_item_id)');
        $this->addSql('COMMENT ON COLUMN activity_item.created_at IS \'(DC2Type:datetime)\'');
        $this->addSql('COMMENT ON COLUMN activity_item.updated_at IS \'(DC2Type:datetime)\'');
        $this->addSql('ALTER TABLE activity_item ADD CONSTRAINT FK_activity_item_activity FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_item ADD CONSTRAINT FK_activity_item_material FOREIGN KEY (material_item_id) REFERENCES material_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // === 2. DB-Funktion: Material-Verfügbarkeit für Zeitraum ===
        $this->addSql("
            CREATE OR REPLACE FUNCTION get_materials_available_for_period(
                p_department_id CHAR(12),
                p_start_date TIMESTAMP,
                p_end_date TIMESTAMP
            )
            RETURNS TABLE (
                material_item_id CHAR(12),
                name VARCHAR(160),
                category_id CHAR(12),
                total_stock INT,
                reserved_in_activities INT,
                available_for_period INT
            )
            LANGUAGE plpgsql
            AS \$func\$
            BEGIN
                RETURN QUERY
                SELECT 
                    mi.id AS material_item_id,
                    mi.name,
                    mi.category_id,
                    COALESCE(batch_totals.total_qty, 0)::INT AS total_stock,
                    COALESCE(reserved.reserved_qty, 0)::INT AS reserved_in_activities,
                    GREATEST(0, COALESCE(batch_totals.total_qty, 0) - COALESCE(reserved.reserved_qty, 0))::INT AS available_for_period
                FROM material_item mi
                LEFT JOIN (
                    SELECT mb.material_item_id AS mid, SUM(mb.qty) AS total_qty
                    FROM material_batch mb
                    GROUP BY mb.material_item_id
                ) batch_totals ON batch_totals.mid = mi.id
                LEFT JOIN (
                    SELECT 
                        ai.material_item_id AS mid,
                        SUM(ai.quantity) AS reserved_qty
                    FROM activity_item ai
                    INNER JOIN activity a ON a.id = ai.activity_id
                    WHERE a.department_id = p_department_id
                      AND a.deleted_at IS NULL
                      AND a.status NOT IN ('cancelled', 'completed')
                      AND (
                          (COALESCE(a.planning_start, a.usage_start) < p_end_date)
                          AND 
                          (COALESCE(a.planning_end, a.usage_end) > p_start_date)
                      )
                    GROUP BY ai.material_item_id
                ) reserved ON reserved.mid = mi.id
                WHERE mi.department_id = p_department_id
                  AND mi.deleted_at IS NULL
                ORDER BY mi.name;
            END;
            \$func\$;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP FUNCTION IF EXISTS get_materials_available_for_period');
        $this->addSql('ALTER TABLE activity_item DROP CONSTRAINT IF EXISTS FK_activity_item_activity');
        $this->addSql('ALTER TABLE activity_item DROP CONSTRAINT IF EXISTS FK_activity_item_material');
        $this->addSql('DROP TABLE IF EXISTS activity_item');
    }
}
