<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Scenario;

use App\Entity\Department;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioIdentity;
use App\Service\Demo\Scenario\DemoScenarioKey;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class RegistryAndIdentityTest extends ScenarioTestCase
{
    public function testRegistryIndexesByKeyAndRejectsDuplicatesAndUnknownKeys(): void
    {
        $registry = $this->registry($this->scenario(DemoScenarioKey::GROSSANLASS_CAMP), $this->scenario(DemoScenarioKey::MATERIALVERWALTUNG));
        self::assertSame([DemoScenarioKey::GROSSANLASS_CAMP, DemoScenarioKey::MATERIALVERWALTUNG], array_keys($registry->all()));
        self::assertTrue($registry->has(DemoScenarioKey::MATERIALVERWALTUNG));
        self::assertFalse($registry->has(DemoScenarioKey::GROSSANLASS_EVENT));

        try {
            $this->registry($this->scenario(DemoScenarioKey::MATERIALVERWALTUNG), $this->scenario(DemoScenarioKey::MATERIALVERWALTUNG))->all();
            self::fail('Duplikat');
        } catch (DemoScenarioException $e) {
            self::assertStringContainsString('doppelt', $e->getMessage());
        }
        $this->expectException(DemoScenarioException::class);
        $this->registry($this->scenario('irgendein-name'))->all();
    }

    public function testRegistryRefusesUnregisteredScenario(): void
    {
        $this->expectException(DemoScenarioException::class);
        $this->registry()->get(DemoScenarioKey::MATERIALVERWALTUNG);
    }

    private function identity(?Department $holder = null, bool $expectFlush = false): DemoScenarioIdentity
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($holder);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $expectFlush ? $em->expects(self::once())->method('flush') : $em->expects(self::never())->method('flush');

        return new DemoScenarioIdentity($em, $this->registry(
            $this->scenario(DemoScenarioKey::MATERIALVERWALTUNG, false),
            $this->scenario(DemoScenarioKey::GROSSANLASS_EVENT, true),
        ));
    }

    public function testAssignSetsKeyOnDemoDepartment(): void
    {
        $d = $this->department('d1', true, true);
        $this->identity(null, true)->assign($d, DemoScenarioKey::GROSSANLASS_EVENT);
        self::assertSame(DemoScenarioKey::GROSSANLASS_EVENT, $d->getDemoScenarioKey());
    }

    public function testAssignIsIdempotentForSameKey(): void
    {
        $d = $this->department('d1', true, true, DemoScenarioKey::GROSSANLASS_EVENT);
        $this->identity()->assign($d, DemoScenarioKey::GROSSANLASS_EVENT);
        self::assertSame(DemoScenarioKey::GROSSANLASS_EVENT, $d->getDemoScenarioKey());
    }

    public function testRealDepartmentIsNeverAssigned(): void
    {
        $real = $this->department('real', false, true);
        try {
            $this->identity()->assign($real, DemoScenarioKey::GROSSANLASS_EVENT);
            self::fail('echtes Department');
        } catch (DemoScenarioException $e) {
            self::assertStringContainsString('kein Demo-Department', $e->getMessage());
        }
        self::assertNull($real->getDemoScenarioKey());
    }

    public function testTypeMismatchSecondKeyAndTakenKeyAreRefused(): void
    {
        foreach ([
            [$this->department('d1', true, false), DemoScenarioKey::GROSSANLASS_EVENT, null, 'passt nicht'],
            [$this->department('d2', true, true, DemoScenarioKey::GROSSANLASS_CAMP), DemoScenarioKey::GROSSANLASS_EVENT, null, 'gehört bereits'],
            [$this->department('d3', true, true), DemoScenarioKey::GROSSANLASS_EVENT, $this->department('other', true, true, DemoScenarioKey::GROSSANLASS_EVENT), 'bereits dem Department'],
        ] as [$dept, $key, $holder, $message]) {
            try {
                $this->identity($holder)->assign($dept, $key);
                self::fail($message);
            } catch (DemoScenarioException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testUnknownScenarioIsRefused(): void
    {
        $this->expectException(DemoScenarioException::class);
        $this->identity()->assign($this->department('d1'), DemoScenarioKey::GROSSANLASS_CAMP);
    }
}
