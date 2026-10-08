<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Organisation;

use App\Entity\DemoSeedRecord;
use App\Service\Demo\Organisation\ManagedSeedApplier;
use App\Service\Demo\Organisation\ManagedSpec;
use App\Service\Demo\Organisation\OwnershipConflictException;
use App\Service\Demo\Organisation\SyncReport;
use App\Service\Demo\Scenario\DemoSeedLedger;
use App\Service\Demo\Scenario\SeedContext;
use App\Tests\Service\Demo\Scenario\ScenarioTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ManagedSeedApplierTest extends ScenarioTestCase
{
    private const KEY = 'grossanlass-event:thing:a';

    /** @var array<string, object> */
    private array $store = [];
    /** @var array<string, DemoSeedRecord> */
    private array $records = [];
    private int $counter = 0;

    private function setup1(): array
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $ledger = $this->createMock(DemoSeedLedger::class);
        $ledger->method('find')->willReturnCallback(fn (string $s, string $k) => $this->records[$k] ?? null);
        $ledger->method('forScenario')->willReturnCallback(fn () => array_values($this->records));
        $ledger->method('record')->willReturnCallback(function (string $s, string $k, object $e, string $id, $dept, $hash = null, $ver = null) {
            return $this->records[$k] = (new DemoSeedRecord($s, $k, $e::class, $id, $dept))->markManaged($hash, $ver);
        });
        $dept = $this->department('d1', true, true, 'grossanlass-event');
        $ctx = SeedContext::create($this->scenario('grossanlass-event', true), $dept, $ledger);

        return [new ManagedSeedApplier($em), $ctx, new SyncReport()];
    }

    private function thing(string $id, string $name): object
    {
        return $this->store[$id] = new class($id, $name) {
            public function __construct(public string $id, public string $name)
            {
            }
        };
    }

    /** @param array<string, mixed> $desired */
    private function spec(array $desired, ?\Closure $adopt = null): ManagedSpec
    {
        return new ManagedSpec(
            seedKey: self::KEY,
            create: fn (): object => $this->thing('new' . (++$this->counter), ''),
            find: fn (string $id): ?object => $this->store[$id] ?? null,
            idOf: static fn (object $o): string => $o->id,
            read: static fn (object $o): array => ['name' => $o->name],
            write: static function (object $o, array $d): void {
                $o->name = $d['name'];
            },
            desired: $desired,
            adopt: $adopt,
        );
    }

    public function testCreatesThenIsIdempotent(): void
    {
        [$applier, $ctx, $report] = $this->setup1();
        $e = $applier->ensure($ctx, $report, $this->spec(['name' => 'A']), '1');
        self::assertSame('A', $e->name);
        self::assertSame(1, $report->created);

        $again = new SyncReport();
        self::assertSame($e, $applier->ensure($ctx, $again, $this->spec(['name' => 'A']), '1'));
        self::assertSame([0, 1], [$again->created, $again->unchanged]);
        self::assertSame([], $again->notes());
        self::assertCount(1, $this->store);
    }

    public function testUntouchedDataFollowsCatalogChanges(): void
    {
        [$applier, $ctx, $report] = $this->setup1();
        $e = $applier->ensure($ctx, $report, $this->spec(['name' => 'A']), '1');

        $second = new SyncReport();
        $applier->ensure($ctx, $second, $this->spec(['name' => 'A2']), '2');
        self::assertSame('A2', $e->name);
        self::assertSame(1, $second->updated);
        self::assertSame('2', $this->records[self::KEY]->getCatalogVersion());
    }

    public function testManualChangesAreNeverOverwrittenButReported(): void
    {
        [$applier, $ctx, $report] = $this->setup1();
        $e = $applier->ensure($ctx, $report, $this->spec(['name' => 'A']), '1');
        $e->name = 'Von Hand';

        $second = new SyncReport();
        $applier->ensure($ctx, $second, $this->spec(['name' => 'A2']), '2');
        self::assertSame('Von Hand', $e->name);
        self::assertSame(0, $second->updated);
        self::assertCount(1, $second->divergences);
        self::assertStringContainsString('name', $second->divergences[0]);

        // Manuell auf den Katalogwert gebracht: kein Konflikt mehr
        $e->name = 'A2';
        $third = new SyncReport();
        $applier->ensure($ctx, $third, $this->spec(['name' => 'A2']), '2');
        self::assertSame([], $third->notes());
    }

    public function testDeletedEntityIsRecreatedUnderTheSameSeedKey(): void
    {
        [$applier, $ctx, $report] = $this->setup1();
        $e = $applier->ensure($ctx, $report, $this->spec(['name' => 'A']), '1');
        unset($this->store[$e->id]);

        $second = new SyncReport();
        $new = $applier->ensure($ctx, $second, $this->spec(['name' => 'A']), '1');
        self::assertSame(1, $second->recreated);
        self::assertNotSame($e->id, $new->id);
        self::assertSame($new->id, $this->records[self::KEY]->getEntityId());
        self::assertCount(1, $this->records);
    }

    public function testAdoptionBaselinesCurrentStateAndConflictsLeaveEverythingUntouched(): void
    {
        [$applier, $ctx, $report] = $this->setup1();
        $existing = $this->thing('old', 'Altbestand');
        $e = $applier->ensure($ctx, $report, $this->spec(['name' => 'A'], fn (): object => $existing), '1');
        self::assertSame($existing, $e);
        self::assertSame(1, $report->adopted);
        self::assertSame('A', $existing->name); // nie manuell geändert (Baseline = Bestand) → folgt dem Katalog

        $this->records = [];
        $conflict = new SyncReport();
        $other = $this->thing('real', 'Echt');
        $result = $applier->ensure($ctx, $conflict, $this->spec(['name' => 'A'], static function (): never {
            throw new OwnershipConflictException('gehört nicht dem Seed');
        }), '1');
        self::assertNull($result);
        self::assertSame('Echt', $other->name);
        self::assertSame([], $this->records);
        self::assertStringContainsString('gehört nicht dem Seed', $conflict->conflicts[0]);
    }
}
