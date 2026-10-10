<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use App\Service\Demo\Organisation\DemoOrganisationSeeder;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;

final class GrossanlassCampScenario extends AbstractCatalogScenario
{
    public function __construct(DemoOrganisationSeeder $organisationSeeder, private GrossanlassClockOriginResolver $grossanlassOrigin)
    {
        parent::__construct($organisationSeeder);
    }

    public function key(): string
    {
        return DemoScenarioKey::GROSSANLASS_CAMP;
    }

    public function label(): string
    {
        return 'Grossanlass Camp';
    }

    public function expectsGrossanlass(): bool
    {
        return true;
    }

    /** Vorläufig derselbe Ausgangspunkt wie Event; eigener Ausgangspunkt folgt mit dem Camp-Seed (E2). */
    public function clockOrigin(Department $department): ?\DateTimeInterface
    {
        return $department->getGrossanlassConfig() === null ? null : $this->grossanlassOrigin->originFor($department);
    }
}
