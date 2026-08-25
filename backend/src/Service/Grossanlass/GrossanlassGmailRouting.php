<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Gmail-Labelbaum und Anzeige der Referenz (Inquiry-ID bleibt intern 12 Zeichen).
 */
final class GrossanlassGmailRouting
{
    public const DEFAULT_INQUIRIES = 'Firmenanfragen';
    public const DEFAULT_WAITING = 'Status/Wartet auf Antwort';
    public const DEFAULT_REPLIED = 'Status/Antwort erhalten';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_REPLIED = 'replied';

    /**
     * @return array{
     *     label_root: string,
     *     label_inquiries: string,
     *     label_waiting: string,
     *     label_replied: string,
     *     label_by_package: bool,
     *     extra_labels: list<string>,
     *     reference_prefix: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'label_root' => '',
            'label_inquiries' => self::DEFAULT_INQUIRIES,
            'label_waiting' => self::DEFAULT_WAITING,
            'label_replied' => self::DEFAULT_REPLIED,
            'label_by_package' => true,
            'extra_labels' => [],
            'reference_prefix' => '',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *     label_root: string,
     *     label_inquiries: string,
     *     label_waiting: string,
     *     label_replied: string,
     *     label_by_package: bool,
     *     extra_labels: list<string>,
     *     reference_prefix: string
     * }
     */
    public static function normalize(array $raw): array
    {
        $defaults = self::defaults();
        $extra = [];
        $rawExtra = $raw['extra_labels'] ?? [];
        if (is_string($rawExtra)) {
            $rawExtra = preg_split('/\r\n|\n|\r/', $rawExtra) ?: [];
        }
        if (is_array($rawExtra)) {
            foreach ($rawExtra as $line) {
                $path = self::sanitizePath((string) $line);
                if ($path !== '' && !in_array($path, $extra, true)) {
                    $extra[] = $path;
                }
            }
        }

        $prefix = trim((string) ($raw['reference_prefix'] ?? ''));
        $prefix = str_replace(["\n", "\r", "\t"], '', $prefix);
        if (mb_strlen($prefix) > 32) {
            $prefix = mb_substr($prefix, 0, 32);
        }

        return [
            'label_root' => self::sanitizeSegment((string) ($raw['label_root'] ?? $defaults['label_root'])),
            'label_inquiries' => self::sanitizePath((string) ($raw['label_inquiries'] ?? $defaults['label_inquiries'])),
            'label_waiting' => self::sanitizePath((string) ($raw['label_waiting'] ?? $defaults['label_waiting'])),
            'label_replied' => self::sanitizePath((string) ($raw['label_replied'] ?? $defaults['label_replied'])),
            'label_by_package' => (bool) ($raw['label_by_package'] ?? $defaults['label_by_package']),
            'extra_labels' => $extra,
            'reference_prefix' => $prefix,
        ];
    }

    /**
     * @param array{
     *     label_root: string,
     *     label_inquiries: string,
     *     label_waiting: string,
     *     label_replied: string,
     *     label_by_package: bool,
     *     extra_labels: list<string>,
     *     reference_prefix: string
     * } $routing
     * @param list<string> $categoryIds
     * @return list<string>
     */
    public static function labelNames(
        array $routing,
        string $departmentName,
        array $categoryIds,
        string $status = self::STATUS_WAITING,
    ): array {
        $root = $routing['label_root'] !== '' ? $routing['label_root'] : self::sanitizeSegment($departmentName);
        if ($root === '') {
            $root = 'Grossanlass';
        }
        $names = [$root];
        $inquiries = $routing['label_inquiries'];
        if ($inquiries !== '') {
            $names[] = $root . '/' . $inquiries;
        }
        $statusPath = $status === self::STATUS_REPLIED
            ? $routing['label_replied']
            : $routing['label_waiting'];
        if ($statusPath !== '') {
            $names[] = $root . '/' . $statusPath;
        }
        if ($routing['label_by_package']) {
            $packageParent = $inquiries !== '' ? $root . '/' . $inquiries : $root;
            foreach ($categoryIds as $category) {
                $segment = self::sanitizeSegment($category);
                if ($segment === '') {
                    continue;
                }
                $names[] = $packageParent . '/' . $segment;
            }
        }
        foreach ($routing['extra_labels'] as $extra) {
            if ($extra !== '') {
                $names[] = $extra;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array{label_root: string, label_waiting: string, label_replied: string} $routing
     */
    public static function statusLabelName(array $routing, string $departmentName, string $status): string
    {
        $root = $routing['label_root'] !== '' ? $routing['label_root'] : self::sanitizeSegment($departmentName);
        if ($root === '') {
            $root = 'Grossanlass';
        }
        $path = $status === self::STATUS_REPLIED
            ? ($routing['label_replied'] ?? '')
            : ($routing['label_waiting'] ?? '');
        if ($path === '') {
            return '';
        }

        return $root . '/' . $path;
    }

    public static function displayReference(string $prefix, string $inquiryId): string
    {
        return $prefix . $inquiryId;
    }

    public static function sanitizeSegment(string $name): string
    {
        $clean = trim(str_replace(['/', "\n", "\r"], '-', $name));

        return mb_substr($clean, 0, 80);
    }

    public static function sanitizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace(['\\', "\n", "\r"], '/', $path)) as $part) {
            $segment = self::sanitizeSegment($part);
            if ($segment !== '') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }
}
