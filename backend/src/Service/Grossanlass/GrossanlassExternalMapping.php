<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Feldzuordnung einer externen JSON-Antwort auf Helferpool-Felder (rein, ohne Datenbank). Der Benutzer legt je
 * eMatChef-Zielfeld einen Pfad im externen JSON fest (`person.name`, `shifts.0.start`); nichts davon ist fest verdrahtet.
 *
 * Mapping-Format: `records_path` (Pfad zur Liste der Datensätze, leer = Wurzel), `fields` (Ziel => Pfad relativ zum Datensatz),
 * `cancelled_values` (Werte von `booking_status`, die eine Stornierung bedeuten), optional `total_path` und `next_path`
 * (Hinweise auf Vollständigkeit: Gesamtzahl bzw. Folgeseite).
 */
final class GrossanlassExternalMapping
{
    /** Ziel => Pflichtfeld. */
    public const TARGETS = [
        'booking_id' => true,
        'shift_start' => true,
        'shift_end' => true,
        'helper_key' => false,
        'helper_name' => false,
        'helper_first_name' => false,
        'helper_last_name' => false,
        'helper_email' => false,
        'helper_phone' => false,
        'mission' => false,
        'booking_status' => false,
    ];

    /**
     * Prüft die Konfiguration (unabhängig von Daten). Pflichtfelder: Buchungs-ID, Beginn, Ende, ein Helfername
     * (`helper_name` oder Vor-/Nachname) und eine Helferidentität (`helper_key` oder `helper_email`). Namen allein
     * identifizieren nie eine Person.
     *
     * @param array<string, mixed> $mapping
     * @return list<array{field: string, code: string}>
     */
    public static function configProblems(array $mapping): array
    {
        $fields = self::fields($mapping);
        $problems = [];
        foreach (self::TARGETS as $target => $required) {
            if ($required && !isset($fields[$target])) {
                $problems[] = ['field' => $target, 'code' => 'required_unmapped'];
            }
        }
        if (!isset($fields['helper_name']) && !isset($fields['helper_first_name']) && !isset($fields['helper_last_name'])) {
            $problems[] = ['field' => 'helper_name', 'code' => 'required_unmapped'];
        }
        if (!isset($fields['helper_key']) && !isset($fields['helper_email'])) {
            $problems[] = ['field' => 'helper_key', 'code' => 'identity_unmapped'];
        }

        return $problems;
    }

    /**
     * Bereinigt ein vom Frontend geliefertes Mapping auf bekannte Ziele und kurze Pfade.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function sanitize(array $raw): array
    {
        $fields = [];
        foreach (is_array($raw['fields'] ?? null) ? $raw['fields'] : [] as $target => $path) {
            if (!isset(self::TARGETS[(string) $target])) {
                throw new \InvalidArgumentException('Unbekanntes Zielfeld: ' . $target);
            }
            $path = self::cleanPath($path);
            if ($path !== '') {
                $fields[(string) $target] = $path;
            }
        }
        $cancelled = [];
        foreach (is_array($raw['cancelled_values'] ?? null) ? $raw['cancelled_values'] : [] as $v) {
            $v = mb_strtolower(trim((string) $v));
            if ($v !== '' && mb_strlen($v) <= 60) {
                $cancelled[$v] = $v;
            }
        }

        return [
            'records_path' => self::cleanPath($raw['records_path'] ?? ''),
            'fields' => $fields,
            'cancelled_values' => array_values(array_slice($cancelled, 0, 20)),
            'total_path' => self::cleanPath($raw['total_path'] ?? ''),
            'next_path' => self::cleanPath($raw['next_path'] ?? ''),
        ];
    }

    /**
     * Holt die Datensatzliste und die Vollständigkeitshinweise aus der dekodierten Antwort.
     *
     * @param array<string, mixed> $mapping
     * @return array{records: list<mixed>, complete_hint: ?bool, problem: ?string}
     */
    public static function extractRecords(mixed $json, array $mapping): array
    {
        $path = (string) ($mapping['records_path'] ?? '');
        $node = $path === '' ? $json : self::get($json, $path);
        if (!is_array($node) || ($node !== [] && !array_is_list($node))) {
            return ['records' => [], 'complete_hint' => null, 'problem' => 'records_path_not_list'];
        }
        $hint = null;
        $totalPath = (string) ($mapping['total_path'] ?? '');
        if ($totalPath !== '') {
            $total = self::get($json, $totalPath);
            $hint = is_numeric($total) ? (int) $total === count($node) : false;
        }
        $nextPath = (string) ($mapping['next_path'] ?? '');
        if ($nextPath !== '') {
            $next = self::get($json, $nextPath);
            if ($next !== null && $next !== '' && $next !== false) {
                $hint = false; // Folgeseite vorhanden: Antwort unvollständig
            } elseif ($hint === null) {
                $hint = true;
            }
        }

        return ['records' => $node, 'complete_hint' => $hint, 'problem' => null];
    }

    /**
     * Wendet das Mapping auf alle Datensätze an. Ungültige Datensätze werden mit Grund gemeldet und übersprungen,
     * doppelte Buchungs-IDs innerhalb einer Antwort nur einmal übernommen.
     *
     * @param list<mixed> $records
     * @param array<string, mixed> $mapping
     * @return array{rows: array<string, array<string, mixed>>, errors: list<array{index: int, key: ?string, field: ?string, code: string}>}
     */
    public static function mapRecords(array $records, array $mapping): array
    {
        $fields = self::fields($mapping);
        $cancelled = array_map('mb_strtolower', array_map('strval', (array) ($mapping['cancelled_values'] ?? [])));
        $rows = [];
        $errors = [];
        foreach ($records as $index => $record) {
            $err = static function (string $code, ?string $field = null, ?string $key = null) use (&$errors, $index): void {
                $errors[] = ['index' => $index, 'key' => $key, 'field' => $field, 'code' => $code];
            };
            if (!is_array($record)) {
                $err('record_not_object');
                continue;
            }
            $value = static function (string $target) use ($fields, $record): array {
                if (!isset($fields[$target])) {
                    return [null, null];
                }
                $raw = self::get($record, $fields[$target]);
                if ($raw === null || $raw === '') {
                    return [null, null];
                }
                if (is_array($raw)) {
                    return [null, 'not_scalar'];
                }

                return [is_bool($raw) ? ($raw ? 'true' : 'false') : trim((string) $raw), null];
            };

            $data = [];
            $failed = false;
            foreach (array_keys(self::TARGETS) as $target) {
                [$v, $problem] = $value($target);
                if ($problem !== null) {
                    $err($problem, $target);
                    $failed = true;
                }
                $data[$target] = $v;
            }
            $key = $data['booking_id'];
            if ($failed) {
                continue;
            }
            if ($key === null) {
                $err('missing_value', 'booking_id');
                continue;
            }
            if (mb_strlen($key) > 150) {
                $err('too_long', 'booking_id', mb_substr($key, 0, 40));
                continue;
            }
            if (isset($rows[$key])) {
                $err('duplicate_key', 'booking_id', $key);
                continue;
            }
            try {
                $start = GrossanlassStaffingTime::parse(self::dateInput($data['shift_start']), 'shift_start');
            } catch (\InvalidArgumentException) {
                $err($data['shift_start'] === null ? 'missing_value' : 'invalid_datetime', 'shift_start', $key);
                continue;
            }
            try {
                $end = GrossanlassStaffingTime::parse(self::dateInput($data['shift_end']), 'shift_end');
            } catch (\InvalidArgumentException) {
                $err($data['shift_end'] === null ? 'missing_value' : 'invalid_datetime', 'shift_end', $key);
                continue;
            }
            if ($end <= $start) {
                $err('end_before_start', 'shift_end', $key);
                continue;
            }

            $first = $data['helper_first_name'];
            $last = $data['helper_last_name'];
            if ($first === null && $last === null && $data['helper_name'] !== null) {
                [$first, $last] = self::splitName($data['helper_name']);
            }
            if ($first === null && $last === null) {
                $err('missing_value', 'helper_name', $key);
                continue;
            }
            $email = $data['helper_email'] === null ? null : mb_strtolower($data['helper_email']);
            if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180)) {
                $err('invalid_email', 'helper_email', $key);
                continue;
            }
            $helperKey = $data['helper_key'] ?? ($email !== null ? 'mail:' . $email : null);
            if ($helperKey === null) {
                $err('missing_value', 'helper_key', $key);
                continue;
            }
            if (mb_strlen($helperKey) > 190 || mb_strlen((string) $first) > 100 || mb_strlen((string) $last) > 100) {
                $err('too_long', 'helper_name', $key);
                continue;
            }
            $status = $data['booking_status'];

            $rows[$key] = [
                'booking_id' => $key,
                'helper_key' => $helperKey,
                'helper_first_name' => $first ?? '',
                'helper_last_name' => $last ?? '',
                'helper_email' => $email,
                'helper_phone' => $data['helper_phone'] === null ? null : mb_substr($data['helper_phone'], 0, 40),
                'mission' => $data['mission'] === null ? null : mb_substr($data['mission'], 0, 200),
                'shift_start' => $start->format('Y-m-d\TH:i:s'),
                'shift_end' => $end->format('Y-m-d\TH:i:s'),
                'booking_status' => $status,
                'cancelled' => $status !== null && in_array(mb_strtolower($status), $cancelled, true),
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** Wert unter einem Punktpfad (`a.b.0.c`); fehlende Stufen ergeben null. */
    public static function get(mixed $node, string $path): mixed
    {
        if ($path === '') {
            return $node;
        }
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /** @param array<string, mixed> $mapping @return array<string, string> */
    private static function fields(array $mapping): array
    {
        return array_filter(
            is_array($mapping['fields'] ?? null) ? $mapping['fields'] : [],
            static fn ($path, $target) => isset(self::TARGETS[$target]) && is_string($path) && $path !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private static function cleanPath(mixed $path): string
    {
        $path = trim((string) $path);
        if (mb_strlen($path) > 200) {
            throw new \InvalidArgumentException('Pfad ist zu lang');
        }
        if (str_starts_with($path, '.') || str_ends_with($path, '.') || str_contains($path, '..')) {
            throw new \InvalidArgumentException('Ungültiger Pfad: ' . $path);
        }

        return $path;
    }

    private static function dateInput(?string $v): mixed
    {
        if ($v !== null && ctype_digit($v) && strlen($v) >= 9) {
            return '@' . (strlen($v) >= 13 ? intdiv((int) $v, 1000) : $v); // Unix-Sekunden bzw. -Millisekunden
        }

        return $v;
    }

    /** @return array{0: ?string, 1: ?string} Vorname(n), Nachname (letztes Wort) */
    private static function splitName(string $full): array
    {
        $full = trim((string) preg_replace('/\s+/u', ' ', $full));
        $pos = mb_strrpos($full, ' ');
        if ($pos === false) {
            return [null, $full];
        }

        return [mb_substr($full, 0, $pos), mb_substr($full, $pos + 1)];
    }
}
