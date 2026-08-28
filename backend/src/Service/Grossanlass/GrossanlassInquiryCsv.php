<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Sheet-Export für Anfragen: erste Zeile Überschriften, Semikolon oder Komma.
 */
final class GrossanlassInquiryCsv
{
    /**
     * @return list<array{
     *     name: string,
     *     email: string,
     *     place: string,
     *     website: string,
     *     offering: string,
     *     notes: string,
     *     contact_name: string,
     *     contact_first_name: string,
     *     contact_last_name: string,
     *     contact_salutation: string,
     *     phone: string,
     *     categories: list<string>,
     *     line: int
     * }>
     */
    public static function parse(string $csv): array
    {
        $csv = self::stripBom($csv);
        $csv = str_replace(["\r\n", "\r"], "\n", $csv);
        $lines = array_values(array_filter(explode("\n", $csv), static fn (string $line) => trim($line) !== ''));
        if ($lines === []) {
            return [];
        }
        $delimiter = self::detectDelimiter($lines[0]);
        $header = str_getcsv($lines[0], $delimiter, '"', '\\');
        $map = self::headerMap($header);
        if (!isset($map['name'])) {
            throw new \InvalidArgumentException('CSV braucht eine Spalte Firma / Name.');
        }
        $out = [];
        for ($i = 1; $i < count($lines); ++$i) {
            $cells = str_getcsv($lines[$i], $delimiter, '"', '\\');
            $name = self::cell($cells, $map['name']);
            if ($name === '') {
                continue;
            }
            $first = isset($map['contact_first_name']) ? self::cell($cells, $map['contact_first_name']) : '';
            $last = isset($map['contact_last_name']) ? self::cell($cells, $map['contact_last_name']) : '';
            $contact = isset($map['contact_name']) ? self::cell($cells, $map['contact_name']) : '';
            if ($contact === '' && ($first !== '' || $last !== '')) {
                $contact = trim($first . ' ' . $last);
            }
            $salutation = isset($map['contact_salutation']) ? self::cell($cells, $map['contact_salutation']) : '';
            $out[] = [
                'name' => $name,
                'email' => isset($map['email']) ? strtolower(self::cell($cells, $map['email'])) : '',
                'place' => isset($map['place']) ? self::cell($cells, $map['place']) : '',
                'website' => isset($map['website']) ? self::cell($cells, $map['website']) : '',
                'offering' => isset($map['offering']) ? self::cell($cells, $map['offering']) : '',
                'notes' => isset($map['notes']) ? self::cell($cells, $map['notes']) : '',
                'contact_name' => $contact,
                'contact_first_name' => $first,
                'contact_last_name' => $last,
                'contact_salutation' => $salutation,
                'phone' => isset($map['phone']) ? self::cell($cells, $map['phone']) : '',
                'categories' => isset($map['categories'])
                    ? self::splitCategories(self::cell($cells, $map['categories']))
                    : [],
                'line' => $i + 1,
            ];
        }

        return $out;
    }

    /**
     * @param list<string|null> $header
     * @return array{
     *     name?: int,
     *     email?: int,
     *     place?: int,
     *     website?: int,
     *     offering?: int,
     *     notes?: int,
     *     contact_name?: int,
     *     contact_first_name?: int,
     *     contact_last_name?: int,
     *     contact_salutation?: int,
     *     phone?: int,
     *     categories?: int
     * }
     */
    private static function headerMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $raw) {
            $key = self::normalizeHeader((string) $raw);
            if ($key === '') {
                continue;
            }
            if (in_array($key, ['firma', 'name', 'company', 'unternehmen'], true)) {
                $map['name'] = $index;
            } elseif (in_array($key, ['email', 'mail'], true)) {
                $map['email'] = $index;
            } elseif (in_array($key, ['ort', 'place', 'stadt', 'city', 'adresse'], true)) {
                $map['place'] = $index;
            } elseif (in_array($key, ['webseite', 'website', 'url', 'www', 'homepage'], true)) {
                $map['website'] = $index;
            } elseif (in_array($key, ['was', 'angebot', 'offering'], true)) {
                $map['offering'] = $index;
            } elseif (in_array($key, ['hinweise', 'notes', 'bemerkungen', 'bemerkung'], true)) {
                $map['notes'] = $index;
            } elseif (in_array($key, ['anrede', 'salutation', 'titelanrede', 'herrfrau'], true)) {
                $map['contact_salutation'] = $index;
            } elseif (in_array($key, ['vorname', 'firstname', 'givenname', 'kontaktvorname'], true)) {
                $map['contact_first_name'] = $index;
            } elseif (in_array($key, ['nachname', 'lastname', 'surname', 'familienname', 'kontaktnachname'], true)) {
                $map['contact_last_name'] = $index;
            } elseif (in_array($key, ['firmeninhaberkontakt', 'firmeninhaber', 'kontakt', 'contact', 'inhaber', 'ansprechpartner'], true)) {
                $map['contact_name'] = $index;
            } elseif (in_array($key, ['telefon', 'phone', 'tel', 'handy', 'mobile'], true)) {
                $map['phone'] = $index;
            } elseif (in_array($key, ['bereiche', 'bereich', 'kategorien', 'kategorie', 'pakete', 'paket', 'categories', 'category'], true)) {
                $map['categories'] = $index;
            }
        }

        return $map;
    }

    private static function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['a', 'o', 'u', 'ss'], $value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';

        return $value;
    }

    /**
     * @param list<string|null> $cells
     */
    private static function cell(array $cells, int $index): string
    {
        return trim((string) ($cells[$index] ?? ''));
    }

    /**
     * @return list<string>
     */
    private static function splitCategories(string $raw): array
    {
        $parts = preg_split('/[,;|]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $value = trim($part);
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    private static function detectDelimiter(string $headerLine): string
    {
        $semicolons = substr_count($headerLine, ';');
        $commas = substr_count($headerLine, ',');

        return $semicolons > $commas ? ';' : ',';
    }

    private static function stripBom(string $csv): string
    {
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            return substr($csv, 3);
        }

        return $csv;
    }
}
