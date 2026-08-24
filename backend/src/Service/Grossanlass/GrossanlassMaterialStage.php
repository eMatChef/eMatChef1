<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassRound;

final class GrossanlassMaterialStage
{
    public const GROB = 'grob';
    public const FEIN = 'fein';

    /** @var list<string> */
    public const STAGES = [self::GROB, self::FEIN];

    public static function normalize(string $formPurpose, mixed $stage): ?string
    {
        if ($formPurpose !== ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH) {
            if (is_string($stage) && trim($stage) !== '') {
                throw new \InvalidArgumentException('Material-Stufe nur bei Materialwünschen');
            }

            return null;
        }

        $value = is_string($stage) ? trim($stage) : '';
        if ($value === '') {
            return self::GROB;
        }
        if (!in_array($value, self::STAGES, true)) {
            throw new \InvalidArgumentException('Ungültige Material-Stufe');
        }

        return $value;
    }

    public static function isFein(?string $stage): bool
    {
        return $stage === self::FEIN;
    }

    public static function isGrob(?string $stage): bool
    {
        return $stage === self::GROB || $stage === null || $stage === '';
    }
}
