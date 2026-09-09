<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;

final class GrossanlassProcurementQuantityFreeze
{
    public static function isFrozen(?int $quantityAsked): bool
    {
        return $quantityAsked !== null;
    }

    public static function assertMergeAllowed(string $status, ?int $quantityAsked): void
    {
        if ($status !== ActivityGrossanlassProcurementLine::STATUS_BEDARF) {
            throw new \InvalidArgumentException('Position kann nur im Status «Bedarf» bearbeitet werden');
        }
        if (self::isFrozen($quantityAsked)) {
            throw new \InvalidArgumentException('Position ist nach der ersten Anfrage eingefroren');
        }
    }

    public static function delta(?int $quantityAsked, int $quantityCurrent): ?int
    {
        if ($quantityAsked === null) {
            return null;
        }

        return $quantityCurrent - $quantityAsked;
    }
}
