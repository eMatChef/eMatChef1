<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Service\Demo\Organisation\DemoOrganisationSeeder;

/** Szenario, dessen Organisationsstruktur aus dem Seed-Katalog stammt (Phase 2). Prozessdaten folgen in späteren Phasen. */
abstract class AbstractCatalogScenario extends AbstractDemoScenario
{
    public function __construct(private DemoOrganisationSeeder $organisationSeeder)
    {
    }

    public function sync(SeedContext $context): SeedResult
    {
        return $this->organisationSeeder->sync($this, $context);
    }

    protected function verifyContent(SeedContext $context): array
    {
        return $this->organisationSeeder->verify($this, $context);
    }

    protected function phaseHint(): string
    {
        return 'Organisationsstruktur aus dem Katalog; Prozessdaten folgen in späteren Phasen';
    }
}
