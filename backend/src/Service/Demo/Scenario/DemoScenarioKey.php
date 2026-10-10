<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

/** Die unabhängigen Demo-Szenarien. Schlüssel sind stabil und werden nie aus Namen abgeleitet. */
final class DemoScenarioKey
{
    public const MATERIALVERWALTUNG = 'materialverwaltung';
    public const GROSSANLASS_EVENT = 'grossanlass-event';
    public const GROSSANLASS_CAMP = 'grossanlass-camp';
    /** Grossanlass mit offener Ersteinrichtung (noch nicht freigegeben). */
    public const GROSSANLASS_SETUP = 'grossanlass-setup';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::MATERIALVERWALTUNG, self::GROSSANLASS_EVENT, self::GROSSANLASS_CAMP, self::GROSSANLASS_SETUP];
    }
}
