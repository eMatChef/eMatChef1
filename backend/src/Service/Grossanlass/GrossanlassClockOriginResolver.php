<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Service\Clock\ClockOriginResolverInterface;

/** Demo-Grossanlass: Aufbauphase, Anlassbeginn minus 5 Tage um 09:00. */
final class GrossanlassClockOriginResolver implements ClockOriginResolverInterface
{
    public const DAYS_BEFORE_EVENT = 5;

    public function supports(Department $department): bool
    {
        return $department->isGrossanlass() && $department->getGrossanlassConfig() !== null;
    }

    public function originFor(Department $department): \DateTimeInterface
    {
        $config = $department->getGrossanlassConfig();
        if ($config === null) {
            throw new \LogicException('Kein Grossanlass-Department');
        }

        return \DateTime::createFromInterface($config->getPlannedEventStart())
            ->modify('-' . self::DAYS_BEFORE_EVENT . ' days')
            ->setTime(9, 0, 0);
    }
}
