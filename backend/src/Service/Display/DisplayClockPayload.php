<?php

declare(strict_types=1);

namespace App\Service\Display;

use App\Entity\Department;
use App\Service\Clock\BusinessClock;

/**
 * Fachzeit eines Infoscreens, nur lesend und ohne Berechtigungs- oder Gerätedaten. Nur ein Demo-Department
 * (`demo_mode = true`) liefert seine BusinessClock-Zeit; jedes andere Department bleibt bei der echten Zeit des
 * Anzeigegeräts (`mode = real`, keine Zeitangabe). `now` ist naive Wandzeit wie alle Grossanlass-Zeiten.
 */
final class DisplayClockPayload
{
    private const WALL_CLOCK = 'Y-m-d\TH:i:s';

    public function __construct(private BusinessClock $clock) {}

    /**
     * @return array{mode: string, now?: string, offset_seconds?: int}
     */
    public function for(?Department $department): array
    {
        if (!$department instanceof Department || !$department->isDemoMode()) {
            return ['mode' => BusinessClock::MODE_REAL];
        }

        return [
            'mode' => BusinessClock::MODE_DEMO,
            'now' => $this->clock->now($department)->format(self::WALL_CLOCK),
            'offset_seconds' => $this->clock->offsetSeconds($department),
        ];
    }
}
