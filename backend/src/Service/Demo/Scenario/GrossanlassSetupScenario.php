<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use App\Service\Demo\Organisation\DemoOrganisationSeeder;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;

final class GrossanlassSetupScenario extends AbstractCatalogScenario
{
    public function __construct(DemoOrganisationSeeder $organisationSeeder, private GrossanlassClockOriginResolver $grossanlassOrigin)
    {
        parent::__construct($organisationSeeder);
    }

    public function key(): string
    {
        return DemoScenarioKey::GROSSANLASS_SETUP;
    }

    public function label(): string
    {
        return 'Grossanlass Einrichtung';
    }

    public function expectsGrossanlass(): bool
    {
        return true;
    }

    /** Wie Camp und Event: Fachzeit am Ausgangspunkt der Aufbauphase des Grossanlasses. */
    public function clockOrigin(Department $department): ?\DateTimeInterface
    {
        return $department->getGrossanlassConfig() === null ? null : $this->grossanlassOrigin->originFor($department);
    }
}
