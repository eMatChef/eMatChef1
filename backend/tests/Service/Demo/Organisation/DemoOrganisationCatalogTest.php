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
    public function testShippedCatalogIsValidAndCoversThreeIndependentScenarios(): void
    {
        $catalog = new DemoOrganisationCatalog();
        self::assertMatchesRegularExpression('/^\d{4}\.\d+\.\d+$/', $catalog->version());

        $accounts = [];
        $orgNames = [];
        foreach (DemoScenarioKey::all() as $key) {
            $s = $catalog->scenario($key);
            $orgNames[] = $s['organisation']['name'];
            $orgNames[] = $s['department']['name'];
            self::assertNotEmpty($s['groups'], $key);
            self::assertNotEmpty($s['members'], $key);
            foreach ($s['members'] as $m) {
                $accounts[] = $m['account'];
            }
        }
        // Unabhängigkeit: kein Konto, keine Organisation und kein Department doppelt
        self::assertSame(array_values(array_unique($accounts)), $accounts);
        self::assertSame(array_values(array_unique($orgNames)), $orgNames);
        // Nur Konten aus demo-accounts.json (Single Source), Lieferant gehört nicht in ein Department
        foreach ($accounts as $a) {
            self::assertTrue(DemoAccounts::isSeedOwnedEmail(DemoAccounts::email($a)));
            self::assertNotSame('supplier', $a);
        }
    }

    /** @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, string}> */
    public static function brokenCatalogs(): iterable
    {
        yield 'account in two scenarios' => [static function (array $d): array {
            $d['scenarios']['grossanlass-camp']['members'][] = ['account' => 'matwart', 'role' => 'mw', 'groups' => []];

            return $d;
        }, 'mehreren Szenarien'];
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
