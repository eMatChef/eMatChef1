<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

final class MaterialverwaltungScenario extends AbstractDemoScenario
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

    protected function phaseHint(): string
    {
        return 'Phase 4';
    }
}
