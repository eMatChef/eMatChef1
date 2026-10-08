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

    /** @var array{catalogVersion: string, scenarios: array<string, array<string, mixed>>}|null */
    private ?array $data = null;

    public function __construct(private string $file = self::FILE)
    {
    }

    public function version(): string
    {
        return $this->data()['catalogVersion'];
    }

    /** @return array<string, mixed> organisation{name}, department{name}, groups[], members[] */
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

        $accountOwner = [];
        foreach ($scenarios as $key => $scenario) {
            foreach (['organisation', 'department'] as $part) {
                if (trim((string) ($scenario[$part]['name'] ?? '')) === '') {
                    $fail(sprintf('%s: %s.name fehlt.', $key, $part));
                }
            }
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
                try {
                    DemoAccounts::accountByKey($account);
                } catch (\InvalidArgumentException) {
                    $fail(sprintf('%s: Konto «%s» fehlt in demo-accounts.json.', $key, $account));
                }
                if (isset($accountOwner[$account])) {
                    $fail(sprintf('Konto «%s» ist mehreren Szenarien zugeordnet (%s, %s).', $account, $accountOwner[$account], $key));
                }
                $accountOwner[$account] = $key;
                if (!\in_array($member['role'] ?? null, self::MEMBERSHIP_ROLES, true)) {
                    $fail(sprintf('%s: Rolle «%s» von «%s» ungültig.', $key, $member['role'] ?? '', $account));
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
        }
    }
}
