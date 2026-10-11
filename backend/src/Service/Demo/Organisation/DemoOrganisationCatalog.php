<?php

declare(strict_types=1);

namespace App\Service\Demo\Organisation;

use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Util\DemoAccounts;

/**
 * Versionierter Seed-Katalog der Demo-Organisationen (data/seeds/dev-demo/organisations.json).
 * Schlüssel (key) sind die stabilen Seed-Identitäten; Anzeigenamen dürfen sich ändern.
 * Der Katalog wird beim Laden vollständig validiert; ein ungültiger Katalog bricht jeden Sync ab.
 */
class DemoOrganisationCatalog
{
    public const FILE = __DIR__ . '/../../../../data/seeds/dev-demo/organisations.json';

    private const MEMBERSHIP_ROLES = ['mw', 'cmw', 'dc', 'bl', 'komm', 'spon', 'lw', 'clw', 'l1', 'l2', 'l3', 'u'];
    private const GROUP_ROLES = ['leader', 'member'];
    private const KINDS = ['ressort', 'bereich', 'teilbereich'];
    /** Schlüssel des Szenario-Departments in members[].department, adminScopes[].roots usw. */
    public const MAIN = 'main';
    private const GLOBAL_ROLES = ['sa', 'org', 'sub'];
    private const FORBIDDEN_FOR_GLOBAL = ['mw', 'cmw'];

    /** @var array{catalogVersion: string, organisation: array{name: string}, sharedAccounts?: list<string>, scenarios: array<string, array<string, mixed>>}|null */
    private ?array $data = null;

    public function __construct(private string $file = self::FILE)
    {
    }

    public function version(): string
    {
        return $this->data()['catalogVersion'];
    }

    /** @return array{name: string} die gemeinsame Demo-Organisation aller Szenarien */
    public function organisation(): array
    {
        return $this->data()['organisation'];
    }

    /** @return list<string> Konten ohne Department-Mitgliedschaft, die jeder Sync mit anlegt (z. B. Lieferant) */
    public function sharedAccounts(): array
    {
        return $this->data()['sharedAccounts'] ?? [];
    }

    /**
     * @return array<string, mixed> organisation{name}, department{name[, parent]}, [departments[]], groups[], members[],
     *                              [retiredMembers[]], [participants[]], [adminScopes[]]
     */
    public function scenario(string $key): array
    {
        return $this->data()['scenarios'][$key] ?? throw new DemoScenarioException(sprintf('Katalog kennt das Szenario «%s» nicht.', $key));
    }

    /** @return array{catalogVersion: string, organisation: array{name: string}, scenarios: array<string, array<string, mixed>>} */
    private function data(): array
    {
        if ($this->data === null) {
            $decoded = json_decode((string) file_get_contents($this->file), true, 512, JSON_THROW_ON_ERROR);
            $this->validate($decoded);
            $this->data = $decoded;
        }

        return $this->data;
    }

    /** @param array<string, mixed> $d */
    private function validate(array $d): void
    {
        $fail = static fn (string $m) => throw new DemoScenarioException('Seed-Katalog ungültig: ' . $m);

        if (!preg_match('/^\d{4}\.\d{1,2}\.\d{1,3}$/', (string) ($d['catalogVersion'] ?? ''))) {
            $fail('catalogVersion fehlt oder hat falsches Format (JJJJ.M.N).');
        }
        if (trim((string) ($d['organisation']['name'] ?? '')) === '') {
            $fail('organisation.name fehlt (gemeinsame Demo-Organisation).');
        }
        $scenarios = $d['scenarios'] ?? [];
        if (array_keys($scenarios) !== array_values(array_intersect(array_keys($scenarios), DemoScenarioKey::all())) || \count($scenarios) !== \count(DemoScenarioKey::all())) {
            $fail('genau die Szenarien ' . implode(', ', DemoScenarioKey::all()) . ' erwartet.');
        }

        foreach ($d['sharedAccounts'] ?? [] as $account) {
            try {
                DemoAccounts::accountByKey((string) $account);
            } catch (\InvalidArgumentException) {
                $fail(sprintf('sharedAccounts: Konto «%s» fehlt in demo-accounts.json.', $account));
            }
        }

        foreach ($scenarios as $key => $scenario) {
            $accountsInScenario = [];
            if (isset($scenario['organisation'])) {
                $fail(sprintf('%s: eine eigene Organisation je Szenario ist nicht vorgesehen (gemeinsame Organisation auf oberster Ebene).', $key));
            }
            if (trim((string) ($scenario['department']['name'] ?? '')) === '') {
                $fail(sprintf('%s: department.name fehlt.', $key));
            }
            $this->validateParentRef($key, $scenario, $scenarios, $fail);
            $this->validateGrossanlassTimes($key, $scenario['grossanlass'] ?? [], $fail);
            $departmentKeys = $this->validateDepartments($key, $scenario, $fail);
            $groupKeys = [];
            foreach ($scenario['groups'] ?? [] as $group) {
                $gk = (string) ($group['key'] ?? '');
                if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $gk) || isset($groupKeys[$gk])) {
                    $fail(sprintf('%s: Gruppen-Schlüssel «%s» ungültig oder doppelt.', $key, $gk));
                }
                if (trim((string) ($group['name'] ?? '')) === '') {
                    $fail(sprintf('%s: Gruppe «%s» ohne Namen.', $key, $gk));
                }
                if (isset($group['parent']) && !isset($groupKeys[$group['parent']])) {
                    $fail(sprintf('%s: Elterngruppe «%s» von «%s» muss vorher definiert sein.', $key, $group['parent'], $gk));
                }
                if (isset($group['kind']) && !\in_array($group['kind'], self::KINDS, true)) {
                    $fail(sprintf('%s: Gruppenart «%s» ungültig.', $key, $group['kind']));
                }
                $groupKeys[$gk] = true;
            }
            foreach ($scenario['members'] ?? [] as $member) {
                $account = (string) ($member['account'] ?? '');
                $memberDepartment = (string) ($member['department'] ?? self::MAIN);
                try {
                    $accountDefinition = DemoAccounts::accountByKey($account);
                } catch (\InvalidArgumentException) {
                    $fail(sprintf('%s: Konto «%s» fehlt in demo-accounts.json.', $key, $account));
                }
                if (!isset($departmentKeys[$memberDepartment])) {
                    $fail(sprintf('%s: Department «%s» von «%s» unbekannt.', $key, $memberDepartment, $account));
                }
                // Demo-Benutzer dürfen mehreren Demo-Departments angehören, aber nur einmal je Department.
                if (isset($accountsInScenario[$memberDepartment . '|' . $account])) {
                    $fail(sprintf('%s: Konto «%s» doppelt in «%s».', $key, $account, $memberDepartment));
                }
                if (\in_array($account, $d['sharedAccounts'] ?? [], true)) {
                    $fail(sprintf('%s: Konto «%s» ist ein sharedAccount und hat keine Mitgliedschaft.', $key, $account));
                }
                $accountsInScenario[$memberDepartment . '|' . $account] = true;
                if (!\in_array($member['role'] ?? null, self::MEMBERSHIP_ROLES, true)) {
                    $fail(sprintf('%s: Rolle «%s» von «%s» ungültig.', $key, $member['role'] ?? '', $account));
                }
                // Globale Rollen (sa/org/sub) sind keine operativen Rollen: nie automatisch MW.
                if (\in_array($accountDefinition['role'] ?? '', self::GLOBAL_ROLES, true) && \in_array($member['role'], self::FORBIDDEN_FOR_GLOBAL, true)) {
                    $fail(sprintf('%s: «%s» hat eine globale Rolle und erhält keine automatische Rolle «%s».', $key, $account, $member['role']));
                }
                if ($memberDepartment !== self::MAIN && ($member['groups'] ?? []) !== []) {
                    $fail(sprintf('%s: Gruppen sind nur im Szenario-Department vorgesehen («%s»).', $key, $account));
                }
                foreach ($member['groups'] ?? [] as $gm) {
                    if (!isset($groupKeys[$gm['group'] ?? ''])) {
                        $fail(sprintf('%s: Gruppe «%s» von «%s» unbekannt.', $key, $gm['group'] ?? '', $account));
                    }
                    if (!\in_array($gm['role'] ?? null, self::GROUP_ROLES, true)) {
                        $fail(sprintf('%s: Gruppenrolle bei «%s» ungültig.', $key, $account));
                    }
                }
            }
            foreach ($scenario['retiredMembers'] ?? [] as $retired) {
                $account = (string) ($retired['account'] ?? '');
                $retiredDepartment = (string) ($retired['department'] ?? self::MAIN);
                if (!isset($departmentKeys[$retiredDepartment])) {
                    $fail(sprintf('%s: retiredMembers: Department «%s» unbekannt.', $key, $retiredDepartment));
                }
                if (isset($accountsInScenario[$retiredDepartment . '|' . $account])) {
                    $fail(sprintf('%s: retiredMembers: «%s» ist in «%s» zugleich Mitglied.', $key, $account, $retiredDepartment));
                }
                try {
                    DemoAccounts::accountByKey($account);
                } catch (\InvalidArgumentException) {
                    $fail(sprintf('%s: retiredMembers: Konto «%s» fehlt in demo-accounts.json.', $key, $account));
                }
            }
            foreach ($scenario['participants'] ?? [] as $participant) {
                if (!isset($departmentKeys[$participant]) || $participant === self::MAIN) {
                    $fail(sprintf('%s: participants: «%s» muss ein zusätzliches Department sein.', $key, $participant));
                }
            }
            foreach ($scenario['adminScopes'] ?? [] as $scope) {
                $account = (string) ($scope['account'] ?? '');
                try {
                    $role = DemoAccounts::accountByKey($account)['role'] ?? '';
                } catch (\InvalidArgumentException) {
                    $fail(sprintf('%s: adminScopes: Konto «%s» fehlt in demo-accounts.json.', $key, $account));
                }
                if (!\in_array($role, ['org', 'sub'], true)) {
                    $fail(sprintf('%s: adminScopes: «%s» ist weder Orgchef noch Suborgchef.', $key, $account));
                }
                if (($scope['organisation'] ?? false) === true) {
                    $fail(sprintf('%s: adminScopes: «%s» darf keinen Organisations-Scope erhalten (gemeinsame Organisation würde alle Szenarien umfassen); roots verwenden.', $key, $account));
                }
                if (($scope['roots'] ?? []) === []) {
                    $fail(sprintf('%s: adminScopes: «%s» ohne roots.', $key, $account));
                }
                foreach ($scope['roots'] ?? [] as $root) {
                    if (!isset($departmentKeys[$root])) {
                        $fail(sprintf('%s: adminScopes: Wurzel «%s» von «%s» unbekannt.', $key, $root, $account));
                    }
                }
            }
        }
    }

    /**
     * Feste Grossanlass-Zeiten (Wandzeit `YYYY-MM-DDTHH:MM:SS`): Anlassbeginn/-ende, Phasen (Aufbau/Abbau) und Uhr-Ausgangspunkt.
     * Sie werden nie aus dem heutigen Datum berechnet.
     *
     * @param array<string, mixed> $ga
     */
    private function validateGrossanlassTimes(string $key, array $ga, callable $fail): void
    {
        $time = static function (mixed $value): ?\DateTimeImmutable {
            if (!\is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
                return null;
            }

            return \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value) ?: null;
        };
        foreach (['eventStart', 'eventEnd', 'clockOrigin'] as $field) {
            if (isset($ga[$field]) && $time($ga[$field]) === null) {
                $fail(sprintf('%s: grossanlass.%s muss die Wandzeit «JJJJ-MM-TTTHH:MM:SS» sein.', $key, $field));
            }
        }
        if (isset($ga['eventStart']) !== isset($ga['eventEnd'])) {
            $fail(sprintf('%s: grossanlass.eventStart und eventEnd gehören zusammen.', $key));
        }
        if (isset($ga['eventStart'], $ga['eventEnd']) && $time($ga['eventEnd']) <= $time($ga['eventStart'])) {
            $fail(sprintf('%s: grossanlass.eventEnd muss nach eventStart liegen.', $key));
        }
        foreach (['venue', 'delivery', 'storage'] as $part) {
            if (!isset($ga[$part])) {
                continue;
            }
            $lat = $ga[$part]['latitude'] ?? null;
            $lon = $ga[$part]['longitude'] ?? null;
            if (trim((string) ($ga[$part]['name'] ?? '')) === '' || !\is_numeric($lat) || !\is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
                $fail(sprintf('%s: grossanlass.%s braucht einen Namen und gültige Koordinaten (latitude, longitude).', $key, $part));
            }
        }
        $allowed = ['months_before_5', 'months_before_2', 'build', 'during_event', 'teardown', 'months_after_2'];
        $presetKeys = [];
        foreach ($ga['clockPresets'] ?? [] as $preset) {
            $presetKey = (string) ($preset['key'] ?? '');
            if (!\in_array($presetKey, $allowed, true) || isset($presetKeys[$presetKey]) || $time($preset['at'] ?? null) === null) {
                $fail(sprintf('%s: grossanlass.clockPresets «%s»: Schlüssel (%s, je einmal) und Wandzeit «JJJJ-MM-TTTHH:MM:SS» erforderlich.', $key, $presetKey, implode('|', $allowed)));
            }
            $presetKeys[$presetKey] = true;
        }
        $seen = [];
        foreach ($ga['phases'] ?? [] as $phase) {
            $phaseKey = (string) ($phase['key'] ?? '');
            if (!\in_array($phaseKey, ['aufbau', 'durchfuehrung', 'abbau'], true) || isset($seen[$phaseKey])) {
                $fail(sprintf('%s: grossanlass.phases: Schlüssel «%s» ungültig oder doppelt (aufbau|durchfuehrung|abbau).', $key, $phaseKey));
            }
            $seen[$phaseKey] = true;
            if (trim((string) ($phase['name'] ?? '')) === '' || $time($phase['start'] ?? null) === null || $time($phase['end'] ?? null) === null || $time($phase['end']) <= $time($phase['start'])) {
                $fail(sprintf('%s: grossanlass.phases «%s»: name, start und end (Ende nach Start) erforderlich.', $key, $phaseKey));
            }
            if (isset($ga['eventStart'], $ga['eventEnd']) && $time($ga['eventEnd']) > $time($ga['eventStart']) && $time($phase['start'] ?? null) !== null && $time($phase['end'] ?? null) !== null
                && ($time($phase['start']) < $time($ga['eventStart']) || $time($phase['end']) > $time($ga['eventEnd']))) {
                $fail(sprintf('%s: grossanlass.phases «%s» liegt ausserhalb des Anlasszeitraums (eventStart bis eventEnd).', $key, $phaseKey));
            }
        }
    }

    /**
     * Eltern-Department in einem anderen Szenario (department.parentRef {scenario, department}); das Szenario-Department
     * selbst hängt dann unter einem zusätzlichen Department des anderen Szenarios (z. B. Einrichtung unter dem Demo-Department).
     *
     * @param array<string, mixed>                $scenario
     * @param array<string, array<string, mixed>> $scenarios
     */
    private function validateParentRef(string $key, array $scenario, array $scenarios, callable $fail): void
    {
        $ref = $scenario['department']['parentRef'] ?? null;
        if ($ref === null) {
            return;
        }
        if (isset($scenario['department']['parent'])) {
            $fail(sprintf('%s: department.parent und department.parentRef schliessen sich aus.', $key));
        }
        $target = (string) ($ref['scenario'] ?? '');
        if ($target === $key || !isset($scenarios[$target])) {
            $fail(sprintf('%s: parentRef.scenario «%s» unbekannt oder identisch.', $key, $target));
        }
        $department = (string) ($ref['department'] ?? '');
        $known = array_map(static fn (array $d): string => (string) ($d['key'] ?? ''), (array) ($scenarios[$target]['departments'] ?? []));
        if (!\in_array($department, $known, true)) {
            $fail(sprintf('%s: parentRef.department «%s» ist kein zusätzliches Department von «%s».', $key, $department, $target));
        }
    }

    /**
     * Zusätzliche Departments (nur Hierarchie): Schlüssel eindeutig, Eltern vorher definiert (also keine Zyklen).
     *
     * @param array<string, mixed> $scenario
     *
     * @return array<string, true> bekannte Department-Schlüssel inklusive «main»
     */
    private function validateDepartments(string $scenarioKey, array $scenario, callable $fail): array
    {
        $known = [self::MAIN => true];
        $defined = [];
        foreach ($scenario['departments'] ?? [] as $department) {
            $dk = (string) ($department['key'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $dk) || $dk === self::MAIN || isset($defined[$dk])) {
                $fail(sprintf('%s: Department-Schlüssel «%s» ungültig oder doppelt.', $scenarioKey, $dk));
            }
            if (trim((string) ($department['name'] ?? '')) === '') {
                $fail(sprintf('%s: Department «%s» ohne Namen.', $scenarioKey, $dk));
            }
            if (isset($department['parent']) && !isset($defined[$department['parent']])) {
                $fail(sprintf('%s: Eltern-Department «%s» von «%s» muss vorher als zusätzliches Department definiert sein.', $scenarioKey, $department['parent'], $dk));
            }
            $defined[$dk] = true;
            $known[$dk] = true;
        }
        $mainParent = $scenario['department']['parent'] ?? null;
        if ($mainParent !== null && !isset($defined[$mainParent])) {
            $fail(sprintf('%s: department.parent «%s» ist kein zusätzliches Department.', $scenarioKey, $mainParent));
        }

        return $known;
    }
}
