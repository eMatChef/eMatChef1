<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Korrigiert die group_id FK in activity: zeigt jetzt auf "group" statt department.
 * Erstellt auch Testdaten für Gruppen und GroupMemberships.
 */
final class Version20260208210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix activity.group_id FK to reference "group" table instead of department. Add test groups and memberships.';
    }

    public function up(Schema $schema): void
    {
        // 1. Alte FK entfernen (zeigt auf department)
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT IF EXISTS FK_AC74095AFE54D947');
        
        // 2. Bestehende group_id Werte nullen (sie referenzierten department-IDs)
        $this->addSql('UPDATE activity SET group_id = NULL WHERE group_id IS NOT NULL');

        // 3. Neue FK auf "group" Tabelle erstellen
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AFE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // 4.–7. Testdaten nur, wenn die ursprüngliche Dev-/Seed-DB vorhanden ist (frische Prod-DB: überspringen).
        $seedDepts = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM department WHERE id IN ('2d2b91b1c181','77028894f790','432a8c08aa75')"
        );
        $seedUsers = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM \"user\" WHERE id IN ('c13aa592c4b5','a4845fd6efd8','5e39323d8498','caedd9ea458c','e02385918add','b3b041813ef4','0f0b24cec2b9','3b592950b9cf','85b04fc68db8')"
        );
        if ($seedDepts !== 3 || $seedUsers < 1) {
            return;
        }

        // 4. Testdaten: Gruppen für "Pfadi Zürich" (department_id = 2d2b91b1c181)
        $this->addSql("INSERT INTO \"group\" (id, department_id, name, parent_id, sort_order, created_at, updated_at) VALUES
            ('g001pzwolfe', '2d2b91b1c181', 'Wölfe', NULL, 1, NOW(), NOW()),
            ('g002pzpfadi', '2d2b91b1c181', 'Pfadistufe', NULL, 2, NOW(), NOW()),
            ('g003pzpio'  , '2d2b91b1c181', 'Piostufe', NULL, 3, NOW(), NOW()),
            ('g004pzrover', '2d2b91b1c181', 'Roverstufe', NULL, 4, NOW(), NOW()),
            ('g005pzleite', '2d2b91b1c181', 'Leiterteam', NULL, 5, NOW(), NOW())
        ON CONFLICT (id) DO NOTHING");

        // 5. Testdaten: Gruppen für "Pfadi ZüriOberland" (department_id = 77028894f790)
        $this->addSql("INSERT INTO \"group\" (id, department_id, name, parent_id, sort_order, created_at, updated_at) VALUES
            ('g006zowolfe', '77028894f790', 'Wölfe ZO', NULL, 1, NOW(), NOW()),
            ('g007zopfadi', '77028894f790', 'Pfadistufe ZO', NULL, 2, NOW(), NOW())
        ON CONFLICT (id) DO NOTHING");

        // 6. Testdaten: Gruppen für "Pfadi Effi" (department_id = 432a8c08aa75)
        $this->addSql("INSERT INTO \"group\" (id, department_id, name, parent_id, sort_order, created_at, updated_at) VALUES
            ('g008efwolfe', '432a8c08aa75', 'Wölfe Effi', NULL, 1, NOW(), NOW()),
            ('g009efpfadi', '432a8c08aa75', 'Pfadistufe Effi', NULL, 2, NOW(), NOW())
        ON CONFLICT (id) DO NOTHING");

        // 7. Testdaten: GroupMemberships (Admin-User c13aa592c4b5 als Leader in mehreren Gruppen)
        $this->addSql("INSERT INTO group_membership (user_id, group_id, role, is_primary, created_at) VALUES
            ('c13aa592c4b5', 'g001pzwolfe', 'leader', false, NOW()),
            ('c13aa592c4b5', 'g002pzpfadi', 'leader', true, NOW()),
            ('c13aa592c4b5', 'g005pzleite', 'leader', false, NOW()),
            ('a4845fd6efd8', 'g002pzpfadi', 'member', true, NOW()),
            ('a4845fd6efd8', 'g005pzleite', 'leader', false, NOW()),
            ('5e39323d8498', 'g001pzwolfe', 'member', true, NOW()),
            ('caedd9ea458c', 'g003pzpio', 'leader', true, NOW()),
            ('e02385918add', 'g004pzrover', 'leader', true, NOW()),
            ('b3b041813ef4', 'g002pzpfadi', 'member', false, NOW()),
            ('0f0b24cec2b9', 'g006zowolfe', 'leader', true, NOW()),
            ('3b592950b9cf', 'g007zopfadi', 'leader', true, NOW()),
            ('85b04fc68db8', 'g008efwolfe', 'member', true, NOW())
        ON CONFLICT DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        // GroupMembership Testdaten entfernen
        $this->addSql("DELETE FROM group_membership WHERE group_id IN ('g001pzwolfe','g002pzpfadi','g003pzpio','g004pzrover','g005pzleite','g006zowolfe','g007zopfadi','g008efwolfe','g009efpfadi')");
        
        // Gruppen Testdaten entfernen
        $this->addSql("DELETE FROM \"group\" WHERE id IN ('g001pzwolfe','g002pzpfadi','g003pzpio','g004pzrover','g005pzleite','g006zowolfe','g007zopfadi','g008efwolfe','g009efpfadi')");

        // FK zurück auf department
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT IF EXISTS FK_AC74095AFE54D947');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AFE54D947 FOREIGN KEY (group_id) REFERENCES department (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
