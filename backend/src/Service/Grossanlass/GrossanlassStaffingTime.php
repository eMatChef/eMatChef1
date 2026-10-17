<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Zeitfenster-Rechnen für den Helferpool (rein, ohne Datenbank). Fenster sind halboffen [from, to):
 * 08:00–10:00 und 10:00–12:00 überschneiden sich nicht.
 */
final class GrossanlassStaffingTime
{
    public static function parse(mixed $value, string $field): \DateTime
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTime::createFromInterface($value);
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            throw new \InvalidArgumentException($field . ' ist erforderlich');
        }
        try {
            $parsed = new \DateTime($raw);
        } catch (\Exception) {
            throw new \InvalidArgumentException($field . ' ist kein gültiger Zeitpunkt');
        }

        return $parsed->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    /** @throws \InvalidArgumentException */
    public static function assertWindow(\DateTimeInterface $from, \DateTimeInterface $to): void
    {
        if ($to <= $from) {
            throw new \InvalidArgumentException('Ende muss nach dem Start liegen');
        }
    }

    public static function overlaps(\DateTimeInterface $aFrom, \DateTimeInterface $aTo, \DateTimeInterface $bFrom, \DateTimeInterface $bTo): bool
    {
        return $aFrom < $bTo && $bFrom < $aTo;
    }

    /**
     * Sortiert und fasst überlappende oder direkt aneinander anschliessende Fenster zusammen.
     *
     * @param list<array{0: \DateTimeInterface, 1: \DateTimeInterface}> $windows
     * @return list<array{0: \DateTime, 1: \DateTime}>
     */
    public static function merge(array $windows): array
    {
        usort($windows, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach ($windows as [$from, $to]) {
            $last = count($out) - 1;
            if ($last >= 0 && $from <= $out[$last][1]) {
                if ($to > $out[$last][1]) {
                    $out[$last][1] = \DateTime::createFromInterface($to);
                }
                continue;
            }
            $out[] = [\DateTime::createFromInterface($from), \DateTime::createFromInterface($to)];
        }

        return $out;
    }

    /**
     * Liegt [from, to) vollständig in einem zusammengefassten Verfügbarkeitsfenster?
     *
     * @param list<array{0: \DateTimeInterface, 1: \DateTimeInterface}> $availability
     */
    public static function isCovered(array $availability, \DateTimeInterface $from, \DateTimeInterface $to): bool
    {
        foreach (self::merge($availability) as [$aFrom, $aTo]) {
            if ($aFrom <= $from && $to <= $aTo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Zeitabhängige Deckung eines Personalbedarfs: wie viele Zuteilungen decken welchen Abschnitt des
     * Bedarfsfensters. Zuteilungen ausserhalb des Fensters werden abgeschnitten. Keine Namen, nur Zahlen.
     *
     * - `segments`: Abschnitte mit gleicher Besetzung (`assigned`), `missing` = fehlende Personen
     * - `gaps`: Abschnitte mit `assigned < required`
     * - `fill_ratio`: gedeckte Personenminuten (höchstens `required` je Abschnitt) / benötigte Personenminuten
     *
     * @param list<array{0: \DateTimeInterface, 1: \DateTimeInterface}> $assignments aktive Zuteilungen
     * @return array{required: int, minutes: int, segments: list<array{from: string, to: string, assigned: int, missing: int}>, gaps: list<array{from: string, to: string, assigned: int, missing: int}>, min_assigned: int, peak_assigned: int, fill_ratio: ?float, overbooked: bool}
     */
    public static function coverage(int $required, \DateTimeInterface $from, \DateTimeInterface $to, array $assignments): array
    {
        $points = [$from->getTimestamp(), $to->getTimestamp()];
        $clipped = [];
        foreach ($assignments as [$aFrom, $aTo]) {
            $s = max($aFrom->getTimestamp(), $from->getTimestamp());
            $e = min($aTo->getTimestamp(), $to->getTimestamp());
            if ($e > $s) {
                $clipped[] = [$s, $e];
                $points[] = $s;
                $points[] = $e;
            }
        }
        $points = array_values(array_unique($points));
        sort($points);

        $segments = [];
        $coveredPersonSeconds = 0;
        $minAssigned = PHP_INT_MAX;
        $peak = 0;
        for ($i = 0, $n = count($points) - 1; $i < $n; ++$i) {
            [$s, $e] = [$points[$i], $points[$i + 1]];
            $assigned = 0;
            foreach ($clipped as [$cs, $ce]) {
                if ($cs <= $s && $e <= $ce) {
                    ++$assigned;
                }
            }
            $coveredPersonSeconds += min($assigned, $required) * ($e - $s);
            $minAssigned = min($minAssigned, $assigned);
            $peak = max($peak, $assigned);
            $last = count($segments) - 1;
            if ($last >= 0 && $segments[$last]['assigned'] === $assigned) {
                $segments[$last]['to'] = $e;
                continue;
            }
            $segments[] = ['from' => $s, 'to' => $e, 'assigned' => $assigned];
        }

        $format = static fn (int $ts): string => (new \DateTime('@' . $ts))->setTimezone($from->getTimezone())->format('Y-m-d\TH:i:s');
        $out = [];
        $gaps = [];
        foreach ($segments as $segment) {
            $row = [
                'from' => $format($segment['from']),
                'to' => $format($segment['to']),
                'assigned' => $segment['assigned'],
                'missing' => max(0, $required - $segment['assigned']),
            ];
            $out[] = $row;
            if ($row['missing'] > 0) {
                $gaps[] = $row;
            }
        }
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        return [
            'required' => $required,
            'minutes' => intdiv($seconds, 60),
            'segments' => $out,
            'gaps' => $required > 0 ? $gaps : [],
            'min_assigned' => $minAssigned === PHP_INT_MAX ? 0 : $minAssigned,
            'peak_assigned' => $peak,
            'fill_ratio' => $required > 0 && $seconds > 0 ? round($coveredPersonSeconds / ($required * $seconds), 4) : null,
            'overbooked' => $peak > $required,
        ];
    }
}
