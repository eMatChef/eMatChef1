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

    /** @var array{catalogVersion: string, sharedAccounts?: list<string>, scenarios: array<string, array<string, mixed>>}|null */
    private ?array $data = null;

    public function __construct(private string $file = self::FILE)
    {
    }

    public function version(): string
    {
        return $this->data()['catalogVersion'];
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

    /** @return array{catalogVersion: string, scenarios: array<string, array<string, mixed>>} */
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
        $scenarios = $d['scenarios'] ?? [];
        if (array_keys($scenarios) !== array_values(array_intersect(array_keys($scenarios), DemoScenarioKey::all())) || \count($scenarios) !== \count(DemoScenarioKey::all())) {
            $fail('genau die drei Szenarien ' . implode(', ', DemoScenarioKey::all()) . ' erwartet.');
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
            foreach (['organisation', 'department'] as $part) {
                if (trim((string) ($scenario[$part]['name'] ?? '')) === '') {
                    $fail(sprintf('%s: %s.name fehlt.', $key, $part));
                }
            }
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
                if (($scope['roots'] ?? []) === []) {
                    $fail(sprintf('%s: adminScopes: «%s» ohne roots.', $key, $account));
                }
                foreach ($scope['roots'] as $root) {
                    if (!isset($departmentKeys[$root])) {
                        $fail(sprintf('%s: adminScopes: Wurzel «%s» von «%s» unbekannt.', $key, $root, $account));
                    }
                }
            }
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
