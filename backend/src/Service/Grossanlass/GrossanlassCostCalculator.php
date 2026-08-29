<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassCost;

final class GrossanlassCostCalculator
{
    public static function cash(?string $cashOutChf, string $status): float
    {
        if ($status === DepartmentGrossanlassCost::STATUS_CANCELLED) {
            return 0.0;
        }

        return self::num($cashOutChf);
    }

    public static function netto(
        string $costKind,
        ?string $assetTreatment,
        ?string $cashOutChf,
        ?string $depositReturnedChf,
        ?string $proceedsActualChf,
        string $status,
    ): float {
        if ($status === DepartmentGrossanlassCost::STATUS_CANCELLED) {
            return 0.0;
        }

        $cash = self::num($cashOutChf);

        return match ($costKind) {
            DepartmentGrossanlassCost::KIND_PURCHASE => $assetTreatment === DepartmentGrossanlassCost::ASSET_INVENTORY
                ? 0.0
                : $cash,
            DepartmentGrossanlassCost::KIND_RENTAL => round($cash - self::num($depositReturnedChf), 2),
            DepartmentGrossanlassCost::KIND_LOAN => 0.0,
            DepartmentGrossanlassCost::KIND_BUY_RESALE => round($cash - self::num($proceedsActualChf), 2),
            DepartmentGrossanlassCost::KIND_ANCILLARY => $cash,
            default => $cash,
        };
    }

    public static function fromCost(DepartmentGrossanlassCost $cost): array
    {
        $status = $cost->getStatus();

        return [
            'cash_chf' => self::cash($cost->getCashOutChf(), $status),
            'netto_chf' => self::netto(
                $cost->getCostKind(),
                $cost->getAssetTreatment(),
                $cost->getCashOutChf(),
                $cost->getDepositReturnedChf(),
                $cost->getProceedsActualChf(),
                $status,
            ),
            'soll_chf' => $status === DepartmentGrossanlassCost::STATUS_CANCELLED ? 0.0 : self::num($cost->getSollChf()),
        ];
    }

    public static function kindFromOrigin(string $origin, ?string $preferredKind = null): string
    {
        if ($preferredKind !== null && $preferredKind !== '' && in_array($preferredKind, DepartmentGrossanlassCost::KINDS, true)) {
            if ($preferredKind === DepartmentGrossanlassCost::KIND_ANCILLARY) {
                return $preferredKind;
            }
            $allowed = match ($origin) {
                'loan' => [DepartmentGrossanlassCost::KIND_LOAN, DepartmentGrossanlassCost::KIND_RENTAL],
                'buy' => [DepartmentGrossanlassCost::KIND_PURCHASE],
                'buy_resale' => [DepartmentGrossanlassCost::KIND_BUY_RESALE],
                default => DepartmentGrossanlassCost::KINDS,
            };
            if (in_array($preferredKind, $allowed, true)) {
                return $preferredKind;
            }
        }

        return match ($origin) {
            'buy' => DepartmentGrossanlassCost::KIND_PURCHASE,
            'buy_resale' => DepartmentGrossanlassCost::KIND_BUY_RESALE,
            default => DepartmentGrossanlassCost::KIND_LOAN,
        };
    }

    private static function num(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }
}
