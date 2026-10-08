<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

/** Die drei unabhängigen Demo-Szenarien. Schlüssel sind stabil und werden nie aus Namen abgeleitet. */
final class DemoScenarioKey
{
    public const MATERIALVERWALTUNG = 'materialverwaltung';
    public const GROSSANLASS_EVENT = 'grossanlass-event';
    public const GROSSANLASS_CAMP = 'grossanlass-camp';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::MATERIALVERWALTUNG, self::GROSSANLASS_EVENT, self::GROSSANLASS_CAMP];
    }
}
