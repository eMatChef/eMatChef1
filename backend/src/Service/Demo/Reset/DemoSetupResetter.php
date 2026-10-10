<?php

declare(strict_types=1);

namespace App\Service\Demo\Reset;

use App\Entity\Activity;
use App\Entity\Department;
use App\Entity\DepartmentCalendarPeriod;
use App\Entity\DepartmentGrossanlassConfig;
use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Clock\BusinessClock;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioInterface;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Demo\Scenario\SeedResult;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zurücksetzen des Demo-Szenarios «Grossanlass Einrichtung» auf den Seed-Ausgangszustand (nur dieses Department).
 *
 * Läuft ausschliesslich im {@see \App\Service\Demo\Scenario\DemoScenarioRunner} (Advisory-Lock, eine Transaktion).
 * Das Department und seine ID bleiben bestehen; Benutzerkonten, Passwörter, MFA, Identitäten, andere Departments,
 * Szenarien und Ledger-Einträge werden nie angefasst.
 *
 * Was passiert (alles oder nichts):
 *  1. Inventar: Welche Daten dieses Departments existieren? Jede department-bezogene Tabelle ist entweder
 *     BEHALTEN, ZURÜCKSETZEN oder (Standard für alles Unbekannte) BLOCKIEREND: enthält eine blockierende Tabelle Zeilen,
 *     wird nichts verändert und der Grund gemeldet. Ebenso, wenn zu löschende Zeilen von nicht freigegebenen Tabellen
 *     referenziert werden.
 *  2. Löschen (nur Einrichtungsdaten): Ressorts/Bereiche samt Mitgliedern und Freigaben, Orte (Pins), Adressen,
 *     Beitrittsanfragen dieses Departments.
 *  3. Wiederherstellen: GA-Stammdaten (Name, Anlasszeitraum nach frischem Seed, Ort, Notizen, Typ, Einladungsstand),
 *     Einrichtungsfreigabe, Demo-Uhr, sowie die vom Seed verwalteten Werte (Department-Name, GA-Typ, Mitgliedsrollen)
 *     über den normalen Seed-Sync im «Reset-Modus».
 *  4. Verify muss danach ohne Verstösse durchlaufen, sonst Rollback.
 */
class DemoSetupResetter
{
    /** Tabellen, die zum Seed-Ausgangszustand gehören und nie gelöscht werden (nur gezählt und gemeldet). */
    private const KEEP = [
        'membership' => 'Mitgliedschaften (Seed-Mitglieder werden auf die Katalog-Rolle gesetzt; weitere bleiben unverändert)',
        'department_grossanlass_user_card' => 'Benutzerkarten (aus den Mitgliedschaften abgeleitet)',
        'department_grossanlass_config' => 'GA-Konfiguration (wird auf Seed-Werte zurückgesetzt)',
        'department_calendar_period' => 'Kalenderperiode des Anlasses (Datum wird auf den Seed-Zeitraum gesetzt)',
        'activity' => 'Hauptaktivität des Anlasses',
        'accounting_cost_center' => 'Kostenstellen (Seed-Grundausstattung)',
        'accounting_cost_center_rule' => 'Kostenstellen-Regeln (Seed-Grundausstattung)',
        'category' => 'Kategorien (Seed-Grundausstattung)',
        'department_setting' => 'Department-Einstellungen',
        'department_display_screen' => 'Infoscreen-Geräte (Kopplungen bleiben bestehen)',
        'demo_seed_record' => 'Seed-Ledger',
        'audit_event' => 'Audit-Protokoll',
    ];

    /** Tabellen mit Einrichtungsdaten, die dieser Reset löscht (Spalte → Department). */
    private const RESET = ['group', 'group_membership', 'department_grossanlass_group_share', 'department_grossanlass_place', 'address', 'join_request'];

    /** Spalten, die ein Department referenzieren (Prüfung auf unbekannte Tabellen). */
    private const DEPARTMENT_COLUMNS = ['department_id', 'host_department_id', 'guest_department_id', 'owner_department_id'];

    /** Zulässige Fremdbezüge auf zu löschende Zeilen: Tabelle.Spalte → wird vom Reset ebenfalls bereinigt. */
    private const ALLOWED_REFERENCES = [
        'group' => ['group.parent_id', 'group_membership.group_id', 'department_grossanlass_group_share.group_id', 'department_grossanlass_group_share.target_group_id', 'department_grossanlass_config.logistics_group_id', 'department_grossanlass_place.group_id'],
        'address' => ['address.parent_id', 'department_grossanlass_config.venue_address_id', 'activity.venue_address_id'],
        'department_grossanlass_place' => [],
        'join_request' => [],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BusinessClock $clock,
    ) {
    }

    /** @return list<string> alle Tabellen mit Department-Bezug laut Schema (für den Schema-Test) */
    public function departmentScopedTables(): array
    {
        $in = implode(',', array_map(static fn (string $c): string => "'" . $c . "'", self::DEPARTMENT_COLUMNS));
        $rows = $this->connection()->fetchFirstColumn(
            "SELECT DISTINCT c.table_name FROM information_schema.columns c JOIN information_schema.tables t ON t.table_name = c.table_name AND t.table_schema = c.table_schema AND t.table_type = 'BASE TABLE' WHERE c.table_schema = current_schema() AND c.column_name IN ($in) ORDER BY 1",
        );

        return array_map('strval', $rows);
    }

    /** @return list<string> */
    public static function keptTables(): array
    {
        return array_keys(self::KEEP);
    }

    /** @return list<string> */
    public static function resetTables(): array
    {
        return self::RESET;
    }

    public function apply(SeedContext $context, DemoScenarioInterface $scenario): SeedResult
    {
        $department = $context->requireDepartment();
        $config = $department->getGrossanlassConfig();
        if ($scenario->key() !== DemoScenarioKey::GROSSANLASS_SETUP || $department->getDemoScenarioKey() !== DemoScenarioKey::GROSSANLASS_SETUP
            || !$department->isDemoMode() || !$department->isGrossanlass() || !$config instanceof DepartmentGrossanlassConfig) {
            throw new DemoScenarioException('Reset ist nur für das Demo-Department «Grossanlass Einrichtung» zulässig.');
        }
        $id = (string) $department->getId();

        $inventory = $this->inventory($id);
        $plan = [
            'scenario' => $scenario->key(),
            'department' => ['id' => $id, 'name' => $department->getName()],
            'delete' => $inventory['delete'],
            'restore' => ['config' => $this->plannedConfigChanges($config, $department), 'managed' => [], 'recreated' => 0],
            'keep' => $inventory['keep'] + ['memberships_extra' => $this->extraMemberships($id, $context)],
            'blocked' => $inventory['blocked'],
        ];
        if ($inventory['blocked'] !== []) {
            return SeedResult::okWithData('Reset nicht möglich: ' . \count($inventory['blocked']) . ' Blockade(n); nichts wurde verändert.', $plan, $inventory['blocked']);
        }

        $this->deleteSetupData($id, $inventory['ids']);
        // Direkte SQL-Löschungen: den Unit of Work verwerfen und Department, Konfiguration und Kontext frisch laden.
        $department = $this->entityManager->find(Department::class, $id) ?? throw new DemoScenarioException('Department nach dem Löschen nicht mehr auffindbar; zurückgerollt.');
        $context = $context->withDepartment($department);
        $config = $department->getGrossanlassConfig() ?? throw new DemoScenarioException('GA-Konfiguration fehlt; zurückgerollt.');
        $this->restoreConfig($department, $config);
        $this->entityManager->flush();

        $sync = $scenario->sync($context->withForcedManaged());
        $plan['restore']['managed'] = array_values(array_map(
            static fn (string $note): string => substr($note, \strlen(self::RESTORED_PREFIX)),
            array_filter($sync->notes, static fn (string $n): bool => str_starts_with($n, self::RESTORED_PREFIX)),
        ));
        $plan['restore']['recreated'] = $sync->created;
        sort($plan['restore']['managed']);

        $department = $this->entityManager->find(Department::class, $id) ?? $department;
        $this->clock->reset($department);
        $this->entityManager->flush();
        $config = $department->getGrossanlassConfig() ?? $config;

        $violations = $scenario->verify($context);
        if ($violations !== []) {
            throw new DemoScenarioException('Verify nach dem Reset meldet Verstösse (zurückgerollt): ' . implode('; ', $violations));
        }
        if ($config->isSetupReleased() || $this->scalar('SELECT count(*) FROM "group" WHERE department_id = ?', [$id]) !== 0) {
            throw new DemoScenarioException('Nachbedingung verletzt (Einrichtung offen, keine Ressorts erwartet); zurückgerollt.');
        }

        return SeedResult::okWithData('Demo «Grossanlass Einrichtung» zurückgesetzt.', $plan, $sync->notes);
    }

    public const RESTORED_PREFIX = 'Zurückgesetzt: ';

    /**
     * @return array{delete: array<string, mixed>, keep: array<string, mixed>, blocked: list<string>, ids: array<string, list<string>>}
     */
    private function inventory(string $id): array
    {
        $blocked = [];
        $keep = [];
        $kept = array_keys(self::KEEP);

        foreach ($this->departmentScopedTables() as $table) {
            if (\in_array($table, self::RESET, true)) {
                continue;
            }
            $count = $this->countRows($table, $id);
            if ($count === 0) {
                continue;
            }
            if (\in_array($table, $kept, true)) {
                $keep[$table] = $count;
                continue;
            }
            $blocked[] = sprintf('Tabelle «%s» enthält %d Datensatz/Datensätze dieses Departments, die der Reset nicht sicher zuordnen kann.', $table, $count);
        }
        if ($this->scalar('SELECT count(*) FROM activity WHERE department_id = ?', [$id]) > 1) {
            $blocked[] = 'Es gibt weitere Aktivitäten ausser der Hauptaktivität des Anlasses.';
        }

        $groups = $this->connection()->fetchAllAssociative('SELECT id, name FROM "group" WHERE department_id = ? ORDER BY name, id', [$id]);
        $groupIds = array_map(static fn (array $g): string => (string) $g['id'], $groups);
        $addresses = $this->connection()->fetchAllAssociative('SELECT id, name, type FROM address WHERE department_id = ? ORDER BY name, id', [$id]);
        $addressIds = array_map(static fn (array $a): string => (string) $a['id'], $addresses);
        $placeIds = $this->connection()->fetchFirstColumn('SELECT id FROM department_grossanlass_place WHERE department_id = ? ORDER BY id', [$id]);
        $joinIds = $this->connection()->fetchFirstColumn('SELECT id FROM join_request WHERE department_id = ? ORDER BY id', [$id]);
        $placeIds = array_map('strval', $placeIds);
        $joinIds = array_map('strval', $joinIds);

        // Gruppen, die einem anderen Department gehören, dürfen an zu löschenden Zeilen nicht hängen.
        foreach (['group' => $groupIds, 'address' => $addressIds, 'department_grossanlass_place' => $placeIds, 'join_request' => $joinIds] as $table => $ids) {
            foreach ($this->foreignReferences($table, $ids) as $reference) {
                $blocked[] = $reference;
            }
        }

        $membersInGroups = $groupIds === [] ? 0 : $this->scalar('SELECT count(*) FROM group_membership WHERE group_id IN (' . $this->placeholders($groupIds) . ')', $groupIds);
        $shares = $this->scalar('SELECT count(*) FROM department_grossanlass_group_share WHERE department_id = ?', [$id]);

        return [
            'delete' => [
                'groups' => array_map(static fn (array $g): array => ['id' => (string) $g['id'], 'name' => (string) $g['name']], $groups),
                'group_members' => $membersInGroups,
                'group_shares' => $shares,
                'places' => \count($placeIds),
                'addresses' => array_map(static fn (array $a): array => ['id' => (string) $a['id'], 'name' => (string) $a['name'], 'type' => (string) $a['type']], $addresses),
                'join_requests' => \count($joinIds),
            ],
            'keep' => $keep,
            'blocked' => $blocked,
            'ids' => ['groups' => $groupIds, 'addresses' => $addressIds, 'places' => $placeIds, 'join_requests' => $joinIds],
        ];
    }

    /**
     * Fremdbezüge (Fremdschlüssel anderer Tabellen) auf Zeilen, die gelöscht würden.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function foreignReferences(string $table, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $quoted = $table === 'group' ? '"group"' : $table;
        $refs = $this->connection()->fetchAllAssociative(
            'SELECT c.conrelid::regclass::text AS tbl, a.attname AS col FROM pg_constraint c JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1] WHERE c.contype = \'f\' AND array_length(c.conkey, 1) = 1 AND c.confrelid = ?::regclass',
            [$quoted],
        );
        $messages = [];
        foreach ($refs as $ref) {
            $tbl = trim((string) $ref['tbl'], '"');
            $col = (string) $ref['col'];
            if (\in_array($tbl . '.' . $col, self::ALLOWED_REFERENCES[$table], true)) {
                continue;
            }
            $count = $this->scalar(sprintf('SELECT count(*) FROM "%s" WHERE "%s" IN (%s)', $tbl, $col, $this->placeholders($ids)), $ids);
            if ($count > 0) {
                $messages[] = sprintf('%d Datensatz/Datensätze in «%s.%s» verweisen auf Daten, die der Reset löschen würde (%s).', $count, $tbl, $col, $table);
            }
        }

        return $messages;
    }

    /** @param array<string, list<string>> $ids */
    private function deleteSetupData(string $departmentId, array $ids): void
    {
        $c = $this->connection();
        // Bezüge der bleibenden Zeilen zuerst lösen.
        $c->executeStatement('UPDATE department_grossanlass_config SET logistics_group_id = NULL, venue_address_id = NULL WHERE department_id = ?', [$departmentId]);
        $c->executeStatement('UPDATE activity SET venue_address_id = NULL WHERE department_id = ? AND venue_address_id IS NOT NULL', [$departmentId]);
        $c->executeStatement('DELETE FROM department_grossanlass_group_share WHERE department_id = ?', [$departmentId]);
        if ($ids['places'] !== []) {
            $c->executeStatement('DELETE FROM department_grossanlass_place WHERE department_id = ?', [$departmentId]);
        }
        if ($ids['groups'] !== []) {
            $c->executeStatement('DELETE FROM group_membership WHERE group_id IN (' . $this->placeholders($ids['groups']) . ')', $ids['groups']);
            $c->executeStatement('UPDATE "group" SET parent_id = NULL WHERE department_id = ?', [$departmentId]);
            $c->executeStatement('DELETE FROM "group" WHERE department_id = ?', [$departmentId]);
        }
        if ($ids['addresses'] !== []) {
            $c->executeStatement('UPDATE address SET parent_id = NULL WHERE department_id = ?', [$departmentId]);
            $c->executeStatement('DELETE FROM address WHERE department_id = ?', [$departmentId]);
        }
        if ($ids['join_requests'] !== []) {
            $c->executeStatement('DELETE FROM join_request WHERE department_id = ?', [$departmentId]);
        }
        $this->entityManager->clear();
    }

    /** @return array<string, array{from: mixed, to: mixed}> nur tatsächlich abweichende Felder */
    private function plannedConfigChanges(DepartmentGrossanlassConfig $config, Department $department): array
    {
        $target = $this->targetConfig($department);
        $current = [
            'status' => $config->getStatus(),
            'published' => $config->getPublishedAt() !== null,
            'struktur_modus' => $config->getStrukturModus(),
            'location_text' => $config->getLocationText(),
            'notes' => $config->getNotes(),
            'venue_address_id' => $config->getVenueAddressId(),
            'has_guest_departments' => $config->hasGuestDepartments(),
            'invite_group_ids' => array_values($config->getInviteGroupIds()),
            'logistics_group_id' => $config->getLogisticsGroupId(),
            'planned_event_start' => $config->getPlannedEventStart()->format('Y-m-d'),
            'planned_event_end' => $config->getPlannedEventEnd()?->format('Y-m-d'),
        ];
        $changes = [];
        foreach ($target as $field => $to) {
            if (($current[$field] ?? null) !== $to) {
                $changes[$field] = ['from' => $current[$field] ?? null, 'to' => $to];
            }
        }

        return $changes;
    }

    /** @return array<string, mixed> Seed-Ausgangswerte der nicht vom Katalog verwalteten GA-Stammdaten */
    private function targetConfig(Department $department): array
    {
        [$start, $end] = $this->seedPeriod();

        return [
            'status' => DepartmentGrossanlassConfig::STATUS_DRAFT,
            'published' => false,
            'struktur_modus' => DepartmentGrossanlassConfig::STRUKTUR_OFFEN,
            'location_text' => '',
            'notes' => '',
            'venue_address_id' => null,
            'has_guest_departments' => false,
            'invite_group_ids' => [],
            'logistics_group_id' => null,
            'planned_event_start' => $start->format('Y-m-d'),
            'planned_event_end' => $end->format('Y-m-d'),
        ];
    }

    /** @return array{0: \DateTime, 1: \DateTime} Anlasszeitraum wie ein frischer Seed heute */
    private function seedPeriod(): array
    {
        $start = (new \DateTime('today'))->modify('+' . DemoGrossanlassSeedService::EVENT_START_DAYS_AFTER_SEED . ' days');
        $end = (clone $start)->modify('+14 days')->setTime(23, 59, 59);

        return [$start, $end];
    }

    private function restoreConfig(Department $department, DepartmentGrossanlassConfig $config): void
    {
        [$start, $end] = $this->seedPeriod();

        $config->setStatus(DepartmentGrossanlassConfig::STATUS_DRAFT);
        $config->setPublishedAt(null);
        $config->setPublishedByUserId(null);
        $config->setStrukturModus(DepartmentGrossanlassConfig::STRUKTUR_OFFEN);
        $config->setLocationText('');
        $config->setNotes('');
        $config->setVenueAddress(null);
        $config->setHasGuestDepartments(false);
        $config->setInviteGroupIds([]);
        $config->setLogisticsGroup(null);
        $config->setPlannedEventStart($start);
        $config->setPlannedEventEnd($end);

        $main = $config->getMainActivity();
        if ($main instanceof Activity) {
            $main->setUsageStart($start);
            $main->setUsageEnd($end);
            $main->setVenueAddress(null);
        }
        $period = $this->entityManager->getRepository(DepartmentCalendarPeriod::class)->findOneBy([
            'departmentId' => $department->getId(),
            'label' => DepartmentCalendarPeriod::LABEL_GROSSANLASS,
        ]);
        if ($period instanceof DepartmentCalendarPeriod) {
            $period->setStartDate((clone $start)->setTime(0, 0, 0));
            $period->setEndDate((clone $end)->setTime(0, 0, 0));
            $period->setStartTime(\DateTime::createFromFormat('H:i:s', $start->format('H:i:s')) ?: clone $start);
            $period->setEndTime(\DateTime::createFromFormat('H:i:s', $end->format('H:i:s')) ?: clone $end);
        }
    }

    /** @return list<string> Mitgliedschaften ohne Seed-Ledger-Eintrag: bleiben unverändert */
    private function extraMemberships(string $departmentId, SeedContext $context): array
    {
        $seeded = [];
        foreach ($context->records() as $record) {
            if ($record->getEntityClass() === \App\Entity\Membership::class) {
                $seeded[] = (string) $record->getEntityId();
            }
        }
        $rows = $this->connection()->fetchAllAssociative('SELECT user_id, role FROM membership WHERE department_id = ? ORDER BY user_id', [$departmentId]);
        $extra = [];
        foreach ($rows as $row) {
            if (!\in_array($row['user_id'] . ':' . $departmentId, $seeded, true)) {
                $extra[] = $row['user_id'] . ' (' . $row['role'] . ')';
            }
        }

        return $extra;
    }

    private function countRows(string $table, string $departmentId): int
    {
        $columns = $this->connection()->fetchFirstColumn(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ANY(?::text[])',
            [$table, '{' . implode(',', self::DEPARTMENT_COLUMNS) . '}'],
        );
        $total = 0;
        foreach ($columns as $column) {
            $total += $this->scalar(sprintf('SELECT count(*) FROM "%s" WHERE "%s" = ?', $table, $column), [$departmentId]);
        }

        return $total;
    }

    /** @param list<string> $values */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, \count($values), '?'));
    }

    /** @param list<mixed> $params */
    private function scalar(string $sql, array $params): int
    {
        return (int) $this->connection()->fetchOne($sql, $params);
    }

    private function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }
}
