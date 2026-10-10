<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

final class MaterialverwaltungScenario extends AbstractCatalogScenario
{
    public function key(): string
    {
        return DemoScenarioKey::MATERIALVERWALTUNG;
    }

    public function label(): string
    {
        return 'Materialverwaltung';
    }

    public function expectsGrossanlass(): bool
    {
        return false;
    }
}
