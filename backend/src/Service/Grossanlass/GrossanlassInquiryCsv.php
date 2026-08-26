<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Sheet-Export für Anfragen: erste Zeile Überschriften, Semikolon oder Komma.
 */
final class GrossanlassInquiryCsv
{
    /**
     * @return list<array{name: string, email: string, place: string, categories: list<string>, line: int}>
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
            $out[] = [
                'name' => $name,
                'email' => isset($map['email']) ? strtolower(self::cell($cells, $map['email'])) : '',
                'place' => isset($map['place']) ? self::cell($cells, $map['place']) : '',
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
     * @return array{name?: int, email?: int, place?: int, categories?: int}
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
            } elseif (in_array($key, ['email', 'e-mail', 'mail', 'e_mail'], true)) {
                $map['email'] = $index;
            } elseif (in_array($key, ['ort', 'place', 'stadt', 'city', 'adresse'], true)) {
                $map['place'] = $index;
            } elseif (in_array($key, ['bereiche', 'kategorien', 'kategorie', 'pakete', 'paket', 'categories', 'category'], true)) {
                $map['categories'] = $index;
            }
        }

        return $map;
    }

    private static function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(['ä', 'ö', 'ü'], ['a', 'o', 'u'], $value);

        return trim($value, " \t\n\r\0\x0B\"'");
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
