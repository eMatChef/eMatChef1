<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Scenario;

use App\Entity\DemoSeedRecord;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoSeedLedger;
use App\Service\Demo\Scenario\SeedContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class ContextAndLedgerTest extends ScenarioTestCase
{
    /** @param list<DemoSeedRecord> $stored */
    private function ledger(array $stored = [], ?EntityManagerInterface &$em = null): DemoSeedLedger
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturnCallback(static function (array $c) use ($stored) {
            foreach ($stored as $r) {
                if ($r->getScenarioKey() === $c['scenarioKey'] && $r->getSeedKey() === $c['seedKey']) {
                    return $r;
                }
            }

            return null;
        });
        $repo->method('findBy')->willReturnCallback(static fn (array $c) => array_values(array_filter($stored, static fn (DemoSeedRecord $r) => $r->getScenarioKey() === $c['scenarioKey'])));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new DemoSeedLedger($em);
    }

    public function testContextRequiresMatchingKeyAndDemoMode(): void
    {
        $s = $this->scenario(DemoScenarioKey::GROSSANLASS_EVENT, true);
        $ledger = $this->ledger();

        self::assertNull(SeedContext::create($s, null, $ledger)->department());
        self::assertNotNull(SeedContext::create($s, $this->department('d1', true, true, DemoScenarioKey::GROSSANLASS_EVENT), $ledger)->department());

        foreach ([
            $this->department('d2', true, true, DemoScenarioKey::GROSSANLASS_CAMP),   // fremdes Szenario
            $this->department('d3', true, true, null),                                // Name/Typ allein genügt nicht
            $this->department('d4', false, true, DemoScenarioKey::GROSSANLASS_EVENT), // echtes Department
        ] as $foreign) {
            try {
                SeedContext::create($s, $foreign, $ledger);
                self::fail('Isolation');
            } catch (DemoScenarioException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSeedKeysMustBePrefixedWithTheScenario(): void
    {
        foreach (['einsatz:aufbau', 'grossanlass-camp:x', 'grossanlass-event:Gross', 'grossanlass-event:', ''] as $bad) {
            try {
                DemoSeedLedger::assertSeedKey(DemoScenarioKey::GROSSANLASS_EVENT, $bad);
                self::fail($bad);
            } catch (DemoScenarioException) {
                self::addToAssertionCount(1);
            }
        }
        DemoSeedLedger::assertSeedKey(DemoScenarioKey::GROSSANLASS_EVENT, 'grossanlass-event:einsatz:aufbau-tische');
    }

    public function testRecordIsIdempotentAndNeverStealsAKey(): void
    {
        $dept = $this->department('d1', true, true, DemoScenarioKey::GROSSANLASS_EVENT);
        $existing = new DemoSeedRecord(DemoScenarioKey::GROSSANLASS_EVENT, 'grossanlass-event:a', \stdClass::class, '1', $dept);
        $em = null;
        $ledger = $this->ledger([$existing], $em);
        $em->expects(self::once())->method('persist'); // nur der neue Eintrag

        self::assertSame($existing, $ledger->record(DemoScenarioKey::GROSSANLASS_EVENT, 'grossanlass-event:a', new \stdClass(), '1', $dept));
        $ledger->record(DemoScenarioKey::GROSSANLASS_EVENT, 'grossanlass-event:b', new \stdClass(), '2', $dept);

        $this->expectException(DemoScenarioException::class);
        $ledger->record(DemoScenarioKey::GROSSANLASS_EVENT, 'grossanlass-event:a', new \stdClass(), 'andere-id', $dept);
    }

    public function testDryRunWritesNothingAndLedgerIsScopedToTheScenario(): void
    {
        $event = $this->department('d1', true, true, DemoScenarioKey::GROSSANLASS_EVENT);
        $camp = $this->department('d2', true, true, DemoScenarioKey::GROSSANLASS_CAMP);
        $campRecord = new DemoSeedRecord(DemoScenarioKey::GROSSANLASS_CAMP, 'grossanlass-camp:z', \stdClass::class, '9', $camp);
        $em = null;
        $ledger = $this->ledger([$campRecord], $em);
        $em->expects(self::never())->method('persist');

        $context = SeedContext::create($this->scenario(DemoScenarioKey::GROSSANLASS_EVENT, true), $event, $ledger, true);
        self::assertSame(0, $context->recordCount());
        self::assertNull($context->findRecord('grossanlass-event:z'));
        $this->expectException(DemoScenarioException::class);
        $context->record('grossanlass-event:x', new \stdClass(), '1');
    }

    public function testVerifyReportsMisownedLedgerRecordsAndTypeMismatch(): void
    {
        $dept = $this->department('d1', true, false, DemoScenarioKey::GROSSANLASS_EVENT);
        $other = $this->department('d2', true, true, DemoScenarioKey::GROSSANLASS_CAMP);
        $stray = new DemoSeedRecord(DemoScenarioKey::GROSSANLASS_EVENT, 'grossanlass-event:x', \stdClass::class, '1', $other);
        $scenario = $this->scenario(DemoScenarioKey::GROSSANLASS_EVENT, true);

        $violations = $scenario->verify(SeedContext::create($scenario, $dept, $this->ledger([$stray])));

        self::assertCount(2, $violations);
        self::assertSame([], $scenario->verify(SeedContext::create($scenario, null, $this->ledger())));
    }
}
