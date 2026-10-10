<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Reine Mengenrechnung der Chargen-Verfügbarkeit (keine Datenbank).
 *
 * Ein Slot ist eine Reservierung einer Charge: Menge in einem halboffenen Zeitfenster [from, to). Berührende Fenster
 * (Ende = Beginn) überlappen nicht. Geprüft wird die höchste gleichzeitige Menge, nicht nur Paare: drei Einsätze mit je
 * 4 Stück auf eine Charge von 10 sind überbucht, obwohl jedes Paar passt.
 *
 * @phpstan-type Slot array{id: string, from: \DateTimeInterface, to: \DateTimeInterface, qty: int}
 */
final class GrossanlassAvailability
{
    /** Unikate (Menge ≤ 1) und Fahrzeuge werden nie gleichzeitig mehrfach vergeben. */
    public static function capacity(int $quantity, bool $unique): int
    {
        return $unique ? 1 : max(0, $quantity);
    }

    /**
     * Höchste gleichzeitige Menge im Fenster [from, to) (ohne Fenster: über alle Slots) und die daran beteiligten Slots.
     *
     * @param list<array{id: string, from: \DateTimeInterface, to: \DateTimeInterface, qty: int}> $slots
     * @return array{peak: int, ids: list<string>}
     */
    public static function peak(array $slots, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $points = [];
        foreach ($slots as $slot) {
            $point = $slot['from'];
            if ($from !== null && $point < $from) {
                $point = $from;
            }
            if (($to === null || $point < $to) && $point < $slot['to']) {
                $points[$point->format('U.u')] = $point;
            }
        }

        $best = ['peak' => 0, 'ids' => []];
        foreach ($points as $point) {
            $load = 0;
            $ids = [];
            foreach ($slots as $slot) {
                if ($slot['from'] <= $point && $point < $slot['to']) {
                    $load += max(0, $slot['qty']);
                    $ids[] = $slot['id'];
                }
            }
            if ($load > $best['peak']) {
                $best = ['peak' => $load, 'ids' => $ids];
            }
        }

        return $best;
    }

    /**
     * Alle überbuchten Gruppen gleichzeitiger Slots (für die Konfliktvorschau). Jede Gruppe wird einmal genannt.
     *
     * @param list<array{id: string, from: \DateTimeInterface, to: \DateTimeInterface, qty: int}> $slots
     * @return list<array{peak: int, ids: list<string>}>
     */
    public static function overbooked(array $slots, int $capacity): array
    {
        $points = [];
        foreach ($slots as $slot) {
            $points[$slot['from']->format('U.u')] = $slot['from'];
        }

        $groups = [];
        foreach ($points as $point) {
            $load = 0;
            $ids = [];
            foreach ($slots as $slot) {
                if ($slot['from'] <= $point && $point < $slot['to']) {
                    $load += max(0, $slot['qty']);
                    $ids[] = $slot['id'];
                }
            }
            if ($load > $capacity && count($ids) > 0) {
                sort($ids);
                $groups[implode('|', $ids)] = ['peak' => $load, 'ids' => $ids];
            }
        }

        return array_values($groups);
    }
}
