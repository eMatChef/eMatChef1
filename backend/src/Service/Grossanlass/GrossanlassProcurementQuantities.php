<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassCommitment;

/**
 * Einheitliche Mengenrechnung einer Bedarfsposition: Bedarf, gedeckt (je Quelle), bestellt und eingegangen.
 * Reine Rechnung ohne Datenbankzugriff; Quelle der Wahrheit sind die Chargen der Position und ihre Bewegungen.
 */
final class GrossanlassProcurementQuantities
{
    /**
     * @param list<array{origin: string, quantity: int, received?: int}> $charges Chargen der Position
     *
     * @return array{
     *     need: int,
     *     loaned: int,
     *     ordered: int,
     *     donated: int,
     *     covered: int,
     *     received: int,
     *     open: int,
     *     over: int,
     *     awaiting: int
     * }
     */
    public static function compute(int $need, array $charges): array
    {
        $need = max(0, $need);
        $loaned = 0;
        $ordered = 0;
        $donated = 0;
        $received = 0;
        foreach ($charges as $charge) {
            $qty = max(0, (int) $charge['quantity']);
            $received += min($qty, max(0, (int) ($charge['received'] ?? 0)));
            switch ($charge['origin']) {
                case DepartmentGrossanlassCommitment::ORIGIN_LOAN:
                    $loaned += $qty;
                    break;
                case DepartmentGrossanlassCommitment::ORIGIN_BUY:
                case DepartmentGrossanlassCommitment::ORIGIN_BUY_RESALE:
                    $ordered += $qty;
                    break;
                default:
                    $donated += $qty;
            }
        }
        $covered = $loaned + $ordered + $donated;

        return [
            'need' => $need,
            'loaned' => $loaned,
            'ordered' => $ordered,
            'donated' => $donated,
            'covered' => $covered,
            'received' => $received,
            'open' => max(0, $need - $covered),
            'over' => max(0, $covered - $need),
            // Zugesagt oder bestellt, aber noch nicht physisch da.
            'awaiting' => max(0, $covered - $received),
        ];
    }

    /**
     * Menge, die eine neue Bestellung ohne Überdeckung abdecken darf: Bedarf minus alle anderen Quellen.
     *
     * @param list<array{origin: string, quantity: int, received?: int}> $otherCharges
     */
    public static function orderableQuantity(int $need, array $otherCharges): int
    {
        return self::compute($need, $otherCharges)['open'];
    }
}
