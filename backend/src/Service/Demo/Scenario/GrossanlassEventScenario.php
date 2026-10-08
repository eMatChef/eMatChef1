<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;

final class GrossanlassEventScenario extends AbstractDemoScenario
{
    public function __construct(private GrossanlassClockOriginResolver $grossanlassOrigin)
    {
    }

    public function key(): string
    {
        return DemoScenarioKey::GROSSANLASS_EVENT;
    }

    public function label(): string
    {
        return 'Grossanlass Event';
    }

    public function expectsGrossanlass(): bool
    {
        return true;
    }

    protected function phaseHint(): string
    {
        return 'Phase 2; bestehende Seed-Services bleiben bis dahin eigenständig';
    }

    /** Bestehender Grossanlass-Ausgangspunkt (Anlassbeginn − 5 Tage, 09:00) wiederverwendet. */
    public function clockOrigin(Department $department): ?\DateTimeInterface
    {
        return $department->getGrossanlassConfig() === null ? null : $this->grossanlassOrigin->originFor($department);
    }
}
