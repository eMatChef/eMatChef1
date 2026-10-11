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
    public function testShippedCatalogIsValidAndCoversAllScenariosWithSharedDemoUsers(): void
    {
        $catalog = new DemoOrganisationCatalog();
        self::assertMatchesRegularExpression('/^\d{4}\.\d+\.\d+$/', $catalog->version());

        $perScenario = [];
        $orgNames = [];
        foreach (DemoScenarioKey::all() as $key) {
            $s = $catalog->scenario($key);
            // Alle Szenarien teilen die eine Demo-Organisation; eine eigene Organisation je Szenario gibt es nicht mehr
            self::assertArrayNotHasKey('organisation', $s, $key);
            $orgNames[] = $s['department']['name'];
            // Die Einrichtungs-Szenerie hat bewusst keine Ressorts: sie ist der noch nicht eingerichtete Grossanlass.
            if ($key !== DemoScenarioKey::GROSSANLASS_SETUP) {
                self::assertNotEmpty($s['groups'], $key);
            }
            self::assertNotEmpty($s['members'], $key);
            // je Szenario und Department kein Konto doppelt
            $perScenario[$key] = array_map(static fn (array $m): string => ($m['department'] ?? 'main') . '|' . $m['account'], $s['members']);
            self::assertSame(array_values(array_unique($perScenario[$key])), $perScenario[$key], $key);
            $perScenario[$key] = array_column($s['members'], 'account');
        }
        self::assertSame('Demo Organisation', $catalog->organisation()['name']);
        // Die Szenario-Departments sind je Szenario eigen
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

        // Keine Organisations-Scopes: die eine Demo-Organisation würde sonst alle Szenarien umfassen (Rechteausweitung).
        // Orgchef verwaltet zusätzlich das Camp-, Suborgchef das Event-Department samt Gast-Abteilung, jeweils über roots.
        $rootsByScenario = [];
        foreach (DemoScenarioKey::all() as $key) {
            foreach ($catalog->scenario($key)['adminScopes'] ?? [] as $scope) {
                self::assertArrayNotHasKey('organisation', $scope, $key);
                $rootsByScenario[$scope['account']][$key] = $scope['roots'];
            }
        }
        ksort($rootsByScenario['orgchef']);
        self::assertSame(['grossanlass-camp' => ['main'], 'materialverwaltung' => ['kantonalverband']], $rootsByScenario['orgchef']);
        self::assertSame(['main', 'gast-abteilung'], $rootsByScenario['suborgchef']['grossanlass-event']);
        // Superadmin braucht keinen Scope; kein anderes Konto erhält Verwaltungsbereiche
        $accountsWithScope = [];
        foreach (DemoScenarioKey::all() as $key) {
            foreach ($catalog->scenario($key)['adminScopes'] ?? [] as $scope) {
                $accountsWithScope[$scope['account']] = true;
            }
        }
        $withScope = array_keys($accountsWithScope);
        sort($withScope);
        self::assertSame(['orgchef', 'suborgchef'], $withScope);

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
        }, 'genau die Szenarien'];
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
        yield 'admin scope without roots' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['adminScopes'][0] = ['account' => 'orgchef'];

            return $d;
        }, 'ohne roots'];
        yield 'organisation scope is not allowed' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['adminScopes'][0]['organisation'] = true;

            return $d;
        }, 'Organisations-Scope'];
        yield 'own organisation per scenario' => [static function (array $d): array {
            $d['scenarios']['grossanlass-camp']['organisation'] = ['name' => 'Eigene'];

            return $d;
        }, 'eigene Organisation je Szenario'];
        yield 'missing shared organisation' => [static function (array $d): array {
            unset($d['organisation']);

            return $d;
        }, 'organisation.name fehlt'];
        yield 'parentRef to unknown department' => [static function (array $d): array {
            $d['scenarios']['grossanlass-setup']['department']['parentRef'] = ['scenario' => 'materialverwaltung', 'department' => 'nirgendwo'];

            return $d;
        }, 'parentRef.department'];
        yield 'admin scope with unknown root' => [static function (array $d): array {
            $d['scenarios']['materialverwaltung']['adminScopes'][0]['roots'] = ['nirgendwo'];

            return $d;
        }, 'Wurzel «nirgendwo»'];
        yield 'event end before start' => [static function (array $d): array {
            $d['scenarios']['grossanlass-event']['grossanlass']['eventEnd'] = '2027-06-04T15:00:00';

            return $d;
        }, 'eventEnd muss nach eventStart'];
        yield 'phase with unknown key' => [static function (array $d): array {
            $d['scenarios']['grossanlass-event']['grossanlass']['phases'][0]['key'] = 'vorbereitung';

            return $d;
        }, 'grossanlass.phases'];
        yield 'time without wall clock format' => [static function (array $d): array {
            $d['scenarios']['grossanlass-event']['grossanlass']['clockOrigin'] = '2027-04-04 16:00';

            return $d;
        }, 'Wandzeit'];
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

    public function testGrossanlassScenariosDeclareTheirTypeAndSetupState(): void
    {
        $catalog = new DemoOrganisationCatalog();
        $event = $catalog->scenario('grossanlass-event')['grossanlass'];
        self::assertSame(['event', true], [$event['guestActivityType'], $event['setupReleased']]);
        // Event-Stammdaten (fiktiver Veranstaltungsplatz) und feste Zeiten 2027 (lokale Wandzeit, nie aus dem heutigen Datum)
        self::assertSame(['2027-06-04T16:00:00', '2027-06-06T16:00:00', '2027-04-04T16:00:00'], [$event['eventStart'], $event['eventEnd'], $event['clockOrigin']]);
        self::assertSame(
            ['aufbau' => ['2027-05-19T13:00:00', '2027-06-04T16:00:00'], 'abbau' => ['2027-06-06T17:00:00', '2027-06-23T12:00:00']],
            array_column(array_map(static fn (array $p): array => ['key' => $p['key'], 'range' => [$p['start'], $p['end']]], $event['phases']), 'range', 'key'),
        );
        self::assertSame('Demo-Festplatz Seewiese', $event['venue']['name']);
        self::assertNotSame('', $event['locationText']);
        self::assertNotSame('', $event['notes']);
        self::assertSame(['guestActivityType' => 'camp', 'setupReleased' => true], $catalog->scenario('grossanlass-camp')['grossanlass']);
        // Der noch nicht freigegebene Grossanlass für die Ersteinrichtung: ohne Ressorts, mit allen Rollen zum Testen der Sperre
        $setup = $catalog->scenario('grossanlass-setup');
        self::assertFalse($setup['grossanlass']['setupReleased']);
        self::assertSame([], $setup['groups']);
        $roles = array_column($setup['members'], 'role', 'account');
        self::assertSame('mw', $roles['ga-mw']);
        self::assertSame('cmw', $roles['ga-cmw']);
        self::assertSame('dc', $roles['ga-ok']);
        self::assertSame('bl', $roles['ga-bereich']);
        self::assertSame('u', $roles['ga-helfer']);
        self::assertNull($catalog->scenario('materialverwaltung')['grossanlass'] ?? null);
    }

    public function testGaAccountsCanTestEveryGrossanlassScenarioWithTheirOwnRole(): void
    {
        $catalog = new DemoOrganisationCatalog();
        $roles = static fn (string $key): array => array_column($catalog->scenario($key)['members'], 'role', 'account');
        $event = $roles('grossanlass-event');
        foreach (['ga-mw', 'ga-cmw', 'ga-ok', 'ga-komm', 'ga-spon', 'ga-lw', 'ga-clw', 'ga-bereich', 'ga-helfer'] as $account) {
            // dieselbe Rolle im Event und im Camp: ein Login testet beide Anlässe nach seiner vorgesehenen Rolle
            self::assertArrayHasKey($account, $roles('grossanlass-camp'), "$account im Camp");
            self::assertSame($event[$account], $roles('grossanlass-camp')[$account], "$account Rolle im Camp");
            // im Setup-Szenario sind alle Rollen vorhanden, damit die Sperre vor der Freigabe prüfbar ist
            self::assertArrayHasKey($account, $roles('grossanlass-setup'), "$account im Setup");
            self::assertSame($event[$account], $roles('grossanlass-setup')[$account], "$account Rolle im Setup");
        }
        // keine automatische Primär-Zuweisung: der Login startet weiter im Event
        foreach (['grossanlass-camp', 'grossanlass-setup'] as $key) {
            foreach ($catalog->scenario($key)['members'] as $member) {
                if (str_starts_with($member['account'], 'ga-')) {
                    self::assertFalse($member['primary'], $key . ' ' . $member['account']);
                }
            }
        }
        // Camp-Bereichsleitung und Helfer bekommen ein Ressort, damit «Mein Ressort» nicht leer ist
        $camp = array_column($catalog->scenario('grossanlass-camp')['members'], null, 'account');
        self::assertSame('lagergruppen', $camp['ga-bereich']['groups'][0]['group']);
        self::assertSame('gruppe-a', $camp['ga-helfer']['groups'][0]['group']);
    }
}
