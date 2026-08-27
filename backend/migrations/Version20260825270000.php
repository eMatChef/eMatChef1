<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825270000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Druckkatalog: TSC DA210 + gängige Thermo-Etiketten (bis 108 mm Druckbreite)';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->addSql("INSERT INTO print_media (id, catalog_key, family, brand, sku, name, width_mm, height_mm, cols, rows, is_continuous, default_cut_length_mm, shape, sheet_width_mm, sheet_height_mm, margin_top_mm, margin_left_mm, gap_x_mm, gap_y_mm, status, scope, created_at, updated_at) VALUES
            ('pmt04030mm01', 'tsc_40x30', 'tsc_desktop', 'TSC', '40x30', 'TSC 40×30 mm Thermo', 40.00, 30.00, 1, 1, FALSE, NULL, 'rect', 40.00, 30.00, 0, 0, 0, 0, 'published', 'global', '{$now}', '{$now}'),
            ('pmt05030mm01', 'tsc_50x30', 'tsc_desktop', 'TSC', '50x30', 'TSC 50×30 mm Thermo', 50.00, 30.00, 1, 1, FALSE, NULL, 'rect', 50.00, 30.00, 0, 0, 0, 0, 'published', 'global', '{$now}', '{$now}'),
            ('pmt05840mm01', 'tsc_58x40', 'tsc_desktop', 'TSC', '58x40', 'TSC 58×40 mm Thermo', 58.00, 40.00, 1, 1, FALSE, NULL, 'rect', 58.00, 40.00, 0, 0, 0, 0, 'published', 'global', '{$now}', '{$now}'),
            ('pmt07050mm01', 'tsc_70x50', 'tsc_desktop', 'TSC', '70x50', 'TSC 70×50 mm Thermo', 70.00, 50.00, 1, 1, FALSE, NULL, 'rect', 70.00, 50.00, 0, 0, 0, 0, 'published', 'global', '{$now}', '{$now}'),
            ('pmt10050mm01', 'tsc_100x50', 'tsc_desktop', 'TSC', '100x50', 'TSC 100×50 mm Thermo', 100.00, 50.00, 1, 1, FALSE, NULL, 'rect', 100.00, 50.00, 0, 0, 0, 0, 'published', 'global', '{$now}', '{$now}'),
            ('pmt10015mm01', 'tsc_100x150', 'tsc_desktop', 'TSC', '100x150', 'TSC 100×150 mm Thermo', 100.00, 150.00, 1, 1, FALSE, NULL, 'rect', 100.00, 150.00, 0, 0, 0, 0, 'published', 'global', '{$now}', '{$now}'),
            ('pmt104cont01', 'tsc_104_cont', 'tsc_desktop', 'TSC', '104-cont', 'TSC 104 mm Endlos', 104.00, NULL, 1, 1, TRUE, 50, 'rect', 104.00, 50.00, 0, 0, 0, 0, 'published', 'global', '{$now}', '{$now}')
            ON CONFLICT (id) DO NOTHING");

        $keys = '["tsc_40x30","tsc_50x30","tsc_58x40","tsc_70x50","tsc_100x50","tsc_100x150","tsc_104_cont"]';
        $this->addSql("INSERT INTO print_device_model (id, catalog_key, family, brand, name, compatible_media_keys, status, scope, created_at, updated_at) VALUES
            ('dmtscda21001', 'tsc_da210', 'tsc_desktop', 'TSC', 'DA210', '{$keys}'::json, 'published', 'global', '{$now}', '{$now}')
            ON CONFLICT (id) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM print_device_model WHERE catalog_key = 'tsc_da210'");
        $this->addSql("DELETE FROM print_media WHERE catalog_key LIKE 'tsc_%'");
    }
}
