<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Grossanlass-Uhrzeiten sitzen auf demselben 15-Minuten-Raster wie ActivityTimeField.
 */
final class GrossanlassQuarterHour
{
    public static function snap(\DateTimeInterface $value): \DateTime
    {
        $dt = $value instanceof \DateTime ? clone $value : \DateTime::createFromInterface($value);
        $minutes = ((int) $dt->format('H')) * 60 + (int) $dt->format('i');
        $snapped = (int) round($minutes / 15) * 15;
        if ($snapped >= 24 * 60) {
            $dt->modify('+1 day');
            $dt->setTime(0, 0, 0);

            return $dt;
        }
        $dt->setTime(intdiv($snapped, 60), $snapped % 60, 0);

        return $dt;
    }
}
