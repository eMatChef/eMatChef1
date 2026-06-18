<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'J+S: Kategorie Lagersport & Trekking + PDF-Zeilennummer (no) für Bestellformular-Positionen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO category (id, department_id, name, description, parent_id, sort_order, created_at, updated_at)
            SELECT 'catjslagtr01', 'dept_js00000', 'Lagersport & Trekking',
                   'Bestellformular J+S Lagersport/Trekking — Positionen im Aktivitäts-Bestellmodal',
                   NULL, 0, NOW(), NOW()
            WHERE NOT EXISTS (SELECT 1 FROM category WHERE id = 'catjslagtr01')
            SQL);

        $lines = [
            ['jsmat0000001', 1],
            ['jsmat0000004', 2],
            ['jsmat0000006', 3],
            ['jsmat0000007', 4],
            ['jsmat0000010', 5],
            ['jsmat0000008', 6],
            ['jsmat0000013', 7],
            ['jsmat0000014', 8],
            ['jsmat0000003', 9],
            ['jsmat0000011', 10],
            ['jsmat0000005', 11],
            ['jsmat0000025', 12],
            ['jsmat0000026', 13],
            ['jsmat0000027', 14],
            ['jsmat0000028', 15],
            ['jsmat0000029', 16],
            ['jsmat0000030', 17],
            ['jsmat0000015', 18],
            ['jsmat0000016', 19],
            ['jsmat0000012', 20],
            ['jsmat0000009', 21],
            ['jsmat0000017', 22],
            ['jsmat0000018', 23],
            ['jsmat0000019', 24],
            ['jsmat0000020', 25],
            ['jsmat0000021', 26],
            ['jsmat0000022', 27],
            ['jsmat0000024', 28],
        ];

        foreach ($lines as [$id, $no]) {
            $this->addSql(
                "UPDATE material_item SET category_id = 'catjslagtr01', no = {$no}, updated_at = NOW() WHERE id = '{$id}'",
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE material_item SET category_id = NULL, no = NULL, updated_at = NOW() WHERE department_id = 'dept_js00000' AND is_js_material = true");
        $this->addSql("DELETE FROM category WHERE id = 'catjslagtr01'");
    }
}
