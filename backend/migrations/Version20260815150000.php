<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Coach als Flag an Membership + Coach-User an J+S-Auftrag (statt Rolle «coach»).
 */
final class Version20260815150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'membership.is_js_coach + activity_js_order.js_coach_user_id; migrate role coach → u + flag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership ADD COLUMN IF NOT EXISTS is_js_coach BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql("UPDATE membership SET is_js_coach = TRUE WHERE LOWER(TRIM(role)) = 'coach'");
        $this->addSql("UPDATE membership SET role = 'u' WHERE LOWER(TRIM(role)) = 'coach'");

        $this->addSql('ALTER TABLE activity_js_order ADD COLUMN IF NOT EXISTS js_coach_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'fk_js_order_coach_user'
                ) THEN
                    ALTER TABLE activity_js_order
                        ADD CONSTRAINT fk_js_order_coach_user
                        FOREIGN KEY (js_coach_user_id) REFERENCES "user"(id) ON DELETE SET NULL;
                END IF;
            END $$;
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_js_order_coach_user ON activity_js_order (js_coach_user_id)');

        // Default-Coach-User-ID aus erstem Flag-Mitglied (pro Department), falls noch leer
        $this->addSql(<<<'SQL'
            UPDATE department_setting ds
            SET setting_value = m.user_id, updated_at = NOW()
            FROM (
                SELECT DISTINCT ON (department_id) department_id, user_id
                FROM membership
                WHERE is_js_coach = TRUE
                ORDER BY department_id, user_id
            ) m
            WHERE ds.department_id = m.department_id
              AND ds.setting_key = 'js.default_coach_user_id'
              AND TRIM(ds.setting_value) = ''
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO department_setting (id, department_id, setting_key, setting_value, updated_at)
            SELECT
                LEFT(md5(random()::text || clock_timestamp()::text), 12),
                m.department_id,
                'js.default_coach_user_id',
                m.user_id,
                NOW()
            FROM (
                SELECT DISTINCT ON (department_id) department_id, user_id
                FROM membership
                WHERE is_js_coach = TRUE
                ORDER BY department_id, user_id
            ) m
            WHERE NOT EXISTS (
                SELECT 1 FROM department_setting ds
                WHERE ds.department_id = m.department_id
                  AND ds.setting_key = 'js.default_coach_user_id'
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_js_order DROP CONSTRAINT IF EXISTS fk_js_order_coach_user');
        $this->addSql('DROP INDEX IF EXISTS idx_js_order_coach_user');
        $this->addSql('ALTER TABLE activity_js_order DROP COLUMN IF EXISTS js_coach_user_id');
        $this->addSql('ALTER TABLE membership DROP COLUMN IF EXISTS is_js_coach');
    }
}
