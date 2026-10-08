<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Organisation;

use App\Service\Demo\Organisation\DemoOrganisationCatalog;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Util\DemoAccounts;
use PHPUnit\Framework\TestCase;

final class DemoOrganisationCatalogTest extends TestCase
{
    public function testShippedCatalogIsValidAndCoversThreeScenariosWithSharedDemoUsers(): void
    {
        $catalog = new DemoOrganisationCatalog();
        self::assertMatchesRegularExpression('/^\d{4}\.\d+\.\d+$/', $catalog->version());

        $perScenario = [];
        $orgNames = [];
        foreach (DemoScenarioKey::all() as $key) {
            $s = $catalog->scenario($key);
            $orgNames[] = $s['organisation']['name'];
            $orgNames[] = $s['department']['name'];
            self::assertNotEmpty($s['groups'], $key);
            self::assertNotEmpty($s['members'], $key);
            // je Szenario und Department kein Konto doppelt
            $perScenario[$key] = array_map(static fn (array $m): string => ($m['department'] ?? 'main') . '|' . $m['account'], $s['members']);
            self::assertSame(array_values(array_unique($perScenario[$key])), $perScenario[$key], $key);
            $perScenario[$key] = array_column($s['members'], 'account');
        }
        // Organisationen und Departments sind je Szenario eigen
        self::assertSame(array_values(array_unique($orgNames)), $orgNames);
        // Demo-Benutzer dürfen mehreren Demo-Departments angehören (matwart: eigenes Department und Gast-Abteilung)
        self::assertContains('matwart', $perScenario['materialverwaltung']);
        self::assertContains('matwart', $perScenario['grossanlass-event']);
        // Nur Konten aus demo-accounts.json (Single Source); der Lieferant ist ein sharedAccount ohne Mitgliedschaft
        foreach ($perScenario as $accounts) {
            foreach ($accounts as $a) {
                self::assertTrue(DemoAccounts::isSeedOwnedEmail(DemoAccounts::email($a)));
                self::assertNotSame('supplier', $a);
            }
        }
        self::assertSame(['supplier'], $catalog->sharedAccounts());
    }

    public function testGlobalRolesNeverReceiveAnAutomaticMwMembership(): void
    {
        $catalog = new DemoOrganisationCatalog();
        $roles = [];
        foreach (DemoScenarioKey::all() as $key) {
            foreach ($catalog->scenario($key)['members'] as $member) {
                if (\in_array($member['account'], ['superadmin', 'orgchef', 'suborgchef'], true)) {
                    self::assertNotContains($member['role'], ['mw', 'cmw'], $key . '/' . $member['account']);
                    $roles[$member['account']][] = $key . ':' . ($member['department'] ?? 'main') . '=' . $member['role'];
                }
            }
        }
        // ausdrücklich zugewiesene, unterschiedliche normale Rollen in mehreren Departments
        self::assertSame(['materialverwaltung:main=u', 'grossanlass-event:main=lw'], $roles['superadmin']);
        self::assertSame(['grossanlass-camp:main=u'], $roles['orgchef']);
        self::assertSame(['materialverwaltung:main=u', 'grossanlass-camp:main=l2'], $roles['suborgchef']);
    }

    public function testHierarchyScopesAndGuestDepartmentAreDeclared(): void
    {
        $catalog = new DemoOrganisationCatalog();
        $mat = $catalog->scenario('materialverwaltung');

        // Kantonalverband → (Materialverwaltung | Abteilung Süd → Aussenstelle): mehrstufig, KV ist selbst Department
        self::assertSame('kantonalverband', $mat['department']['parent']);
        $parents = array_column($mat['departments'], 'parent', 'key');
        self::assertArrayNotHasKey('parent', $mat['departments'][0]);
        self::assertSame('kantonalverband', $parents['abteilung-sued']);
        self::assertSame('abteilung-sued', $parents['abteilung-sued-aussenstelle']);

        // Orgchef: übergeordnete Ebene, Suborgchef: untergeordneter Zweig; keine Mitgliedschaft nötig
        $scopes = array_column($mat['adminScopes'], 'roots', 'account');
        self::assertSame(['kantonalverband'], $scopes['orgchef']);
        self::assertSame(['abteilung-sued'], $scopes['suborgchef']);

        // Gast-Abteilung im Grossanlass
        $event = $catalog->scenario('grossanlass-event');
        self::assertSame(['gast-abteilung'], $event['participants']);
        self::assertContains('gast-abteilung', array_column($event['departments'], 'key'));
    }

    /** @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, string}> */
    public static function brokenCatalogs(): iterable
    {
        yield 'account twice in one scenario' => [static function (array $d): array {
            $d['scenarios']['grossanlass-camp']['members'][] = ['account' => 'camp-mw', 'role' => 'mw', 'groups' => []];

            return $d;
        }, 'doppelt'];
        yield 'shared account with membership' => [static function (array $d): array {
            $d['scenarios']['grossanlass-camp']['members'][] = ['account' => 'supplier', 'role' => 'u', 'groups' => []];

            return $d;
        }, 'sharedAccount'];
        yield 'unknown shared account' => [static function (array $d): array {
            $d['sharedAccounts'][] = 'gibt-es-nicht';

            return $d;
        }, 'sharedAccounts'];
        yield 'unknown account' => [static function (array $d): array {
            $d['scenarios']['grossanlass-camp']['members'][0]['account'] = 'gibt-es-nicht';

            return $d;
        }, 'fehlt in demo-accounts.json'];
        yield 'parent defined later' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['groups'][0]['parent'] = 'woelfe';

            return $d;
        }, 'vorher definiert'];
        yield 'duplicate group key' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['groups'][1]['key'] = 'stufen';

            return $d;
        }, 'doppelt'];
        yield 'invalid membership role' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['members'][0]['role'] = 'sa';

            return $d;
        }, 'Rolle'];
        yield 'unknown group in member' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['members'][3]['groups'][0]['group'] = 'nirgendwo';

            return $d;
        }, 'unbekannt'];
        yield 'missing scenario' => [static function (array $d): array {
            unset($d['scenarios']['grossanlass-camp']);

            return $d;
        }, 'drei Szenarien'];
        yield 'global role with mw membership' => [static function (array $d): array {
            $d['scenarios']['grossanlass-camp']['members'][] = ['account' => 'superadmin', 'role' => 'mw', 'groups' => []];

            return $d;
        }, 'keine automatische Rolle'];
        yield 'unknown member department' => [static function (array $d): array {
            $d['scenarios']['grossanlass-camp']['members'][0]['department'] = 'nirgendwo';

            return $d;
        }, 'Department «nirgendwo»'];
        yield 'groups outside the scenario department' => [static function (array $d): array {
            $d['scenarios']['grossanlass-event']['members'][] = ['account' => 'leader1', 'department' => 'gast-abteilung', 'role' => 'u', 'groups' => [['group' => 'bauten', 'role' => 'member']]];

            return $d;
        }, 'Gruppen sind nur'];
        yield 'department parent defined later (no cycles)' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['departments'][0]['parent'] = 'abteilung-sued';

            return $d;
        }, 'vorher als zusätzliches Department'];
        yield 'duplicate department key' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['departments'][1]['key'] = 'kantonalverband';

            return $d;
        }, 'ungültig oder doppelt'];
        yield 'main parent unknown' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['department']['parent'] = 'gibt-es-nicht';

            return $d;
        }, 'department.parent'];
        yield 'retired member still a member' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['retiredMembers'][] = ['account' => 'matwart'];

            return $d;
        }, 'zugleich Mitglied'];
        yield 'participant is not an extra department' => [static function (array $d): array {
            $d['scenarios']['grossanlass-event']['participants'] = ['main'];

            return $d;
        }, 'zusätzliches Department'];
        yield 'admin scope for a non-admin account' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['adminScopes'][] = ['account' => 'matwart', 'roots' => ['kantonalverband']];

            return $d;
        }, 'weder Orgchef noch Suborgchef'];
        yield 'admin scope with unknown root' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['adminScopes'][0]['roots'] = ['nirgendwo'];

            return $d;
        }, 'Wurzel «nirgendwo»'];
        yield 'bad version' => [static function (array $d): array {
            $d['catalogVersion'] = 'v1';

            return $d;
        }, 'catalogVersion'];
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $mutate */
    #[\PHPUnit\Framework\Attributes\DataProvider('brokenCatalogs')]
    public function testInvalidCatalogsAreRejected(callable $mutate, string $message): void
    {
        $data = $mutate(json_decode((string) file_get_contents(DemoOrganisationCatalog::FILE), true, 512, JSON_THROW_ON_ERROR));
        $file = tempnam(sys_get_temp_dir(), 'cat');
        file_put_contents($file, json_encode($data));
        try {
            $this->expectException(DemoScenarioException::class);
            $this->expectExceptionMessage($message);
            (new DemoOrganisationCatalog($file))->version();
        } finally {
            unlink($file);
        }
    }
}
