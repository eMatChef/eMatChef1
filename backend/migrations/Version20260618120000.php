<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'J+S-Katalog dept_js00000: Namen an PDF-Formular anpassen, Lager-Varianten archivieren';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE activity_js_order_item SET material_item_id = 'jsmat0000001' WHERE material_item_id = 'jsmat0000002'");
        $this->addSql("UPDATE activity_js_order_item SET material_item_id = 'jsmat0000022' WHERE material_item_id = 'jsmat0000023'");

        $this->addSql("UPDATE material_item SET name = 'Bindestrick', updated_at = NOW() WHERE id = 'jsmat0000001'");
        $this->addSql("UPDATE material_item SET name = 'Manipulierseil 10–15 m', updated_at = NOW() WHERE id = 'jsmat0000003'");
        $this->addSql("UPDATE material_item SET name = 'Beinstulpe refl. (Stück)', updated_at = NOW() WHERE id = 'jsmat0000005'");
        $this->addSql("UPDATE material_item SET name = 'Kessel 15 l', updated_at = NOW() WHERE id = 'jsmat0000006'");
        $this->addSql("UPDATE material_item SET name = 'Kochkessel 12 l (inkl. Deckel)', updated_at = NOW() WHERE id = 'jsmat0000008'");
        $this->addSql("UPDATE material_item SET name = 'Speiseträger 20 l mit Schöpfkelle', updated_at = NOW() WHERE id = 'jsmat0000009'");
        $this->addSql("UPDATE material_item SET name = 'Kompass Recta', updated_at = NOW() WHERE id = 'jsmat0000013'");
        $this->addSql("UPDATE material_item SET name = 'Badminton (Netz + 6 Schläger)', updated_at = NOW() WHERE id = 'jsmat0000017'");
        $this->addSql("UPDATE material_item SET name = 'Volleyball (Netz + 2 Bälle)', updated_at = NOW() WHERE id = 'jsmat0000018'");
        $this->addSql("UPDATE material_item SET name = 'Badminton/Volleyball Set kombiniert', updated_at = NOW() WHERE id = 'jsmat0000019'");
        $this->addSql("UPDATE material_item SET name = 'Zelttuch', updated_at = NOW() WHERE id = 'jsmat0000022'");
        $this->addSql("UPDATE material_item SET name = 'Rettungsweste XXS (30–40 kg)', updated_at = NOW() WHERE id = 'jsmat0000025'");
        $this->addSql("UPDATE material_item SET name = 'Rettungsweste XS (40–50 kg)', updated_at = NOW() WHERE id = 'jsmat0000026'");
        $this->addSql("UPDATE material_item SET name = 'Rettungsweste S (50–60 kg)', updated_at = NOW() WHERE id = 'jsmat0000027'");
        $this->addSql("UPDATE material_item SET name = 'Rettungsweste M (60–70 kg)', updated_at = NOW() WHERE id = 'jsmat0000028'");
        $this->addSql("UPDATE material_item SET name = 'Rettungsweste L (70–90 kg)', updated_at = NOW() WHERE id = 'jsmat0000029'");
        $this->addSql("UPDATE material_item SET name = 'Rettungsweste XL (90 kg +)', updated_at = NOW() WHERE id = 'jsmat0000030'");

        $this->addSql("UPDATE material_item SET deleted_at = NOW(), updated_at = NOW() WHERE id IN ('jsmat0000002', 'jsmat0000023') AND deleted_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE material_item SET deleted_at = NULL, updated_at = NOW() WHERE id IN ('jsmat0000002', 'jsmat0000023')");
    }
}
