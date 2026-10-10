<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use App\Service\Clock\ClockOriginResolverInterface;

/**
 * Ausgangspunkt der Demo-Uhr für Departments mit Szenario-Identität; delegiert pro Schlüssel an das Szenario.
 * Departments ohne Schlüssel bleiben beim bisherigen Verhalten (GrossanlassClockOriginResolver).
 */
final class ScenarioClockOriginResolver implements ClockOriginResolverInterface
{
    public function __construct(private DemoScenarioRegistry $registry)
    {
    }

    public function supports(Department $department): bool
    {
        $key = $department->getDemoScenarioKey();

        return $key !== null && $this->registry->has($key) && $this->registry->get($key)->clockOrigin($department) !== null;
    }

    public function originFor(Department $department): \DateTimeInterface
    {
        $key = $department->getDemoScenarioKey();
        $origin = $key !== null && $this->registry->has($key) ? $this->registry->get($key)->clockOrigin($department) : null;

        return $origin ?? throw new \LogicException('Kein Szenario-Ausgangspunkt');
    }
}
