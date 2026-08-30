<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Gmail-Labelbaum und Anzeige der Referenz (Inquiry-ID bleibt intern 12 Zeichen).
 */
final class GrossanlassGmailRouting
{
    public const DEFAULT_ROOT = 'eMatChef';
    public const DEFAULT_INQUIRIES = 'Firmenanfragen';
    public const DEFAULT_WAITING = 'Status/Wartet auf Antwort';
    public const DEFAULT_REPLIED = 'Status/Antwort erhalten';

    /** @var list<string> */
    public const DEFAULT_STATUS_TREE = [
        'Status/Wartet auf Antwort',
        'Status/Antwort erhalten',
        'Status/Zusage',
        'Status/Teilzusage',
        'Status/Absage',
        'Status/Nachfassen',
        'Status/Erledigt',
    ];
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
            'label_root' => self::DEFAULT_ROOT,
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

        $root = self::enforceEmatchefRoot((string) ($raw['label_root'] ?? $defaults['label_root']));

        return [
            'label_root' => $root,
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
        ?string $inquiryStatus = null,
    ): array {
        $root = self::resolveRoot($routing, $departmentName);
        $names = [$root];
        $inquiries = $routing['label_inquiries'];
        if ($inquiries !== '') {
            $names[] = $root . '/' . $inquiries;
        }
        $statusFull = $inquiryStatus !== null
            ? self::inquiryStatusPath($routing, $departmentName, $inquiryStatus)
            : self::statusLabelName($routing, $departmentName, $status);
        if ($statusFull !== '') {
            $names[] = $statusFull;
            $parts = explode('/', $statusFull);
            if (count($parts) >= 2) {
                $names[] = $parts[0] . '/' . $parts[1];
            }
        }
        if ($routing['label_by_package']) {
            $packageParent = $inquiries !== '' ? $root . '/' . $inquiries : $root;
            foreach ($categoryIds as $category) {
                $path = self::sanitizePath((string) $category);
                if ($path === '') {
                    continue;
                }
                $acc = $packageParent;
                foreach (explode('/', $path) as $segment) {
                    if ($segment === '') {
                        continue;
                    }
                    $acc .= '/' . $segment;
                    $names[] = $acc;
                }
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
        $root = self::resolveRoot($routing, $departmentName);
        $path = $status === self::STATUS_REPLIED
            ? ($routing['label_replied'] ?? '')
            : ($routing['label_waiting'] ?? '');
        if ($path === '') {
            return '';
        }

        return $root . '/' . $path;
    }

    /**
     * @param array{label_root: string, label_waiting: string, label_replied: string} $routing
     */
    public static function inquiryStatusPath(array $routing, string $departmentName, string $inquiryStatus): string
    {
        $key = match ($inquiryStatus) {
            'antwort' => self::STATUS_REPLIED,
            'zusage' => 'zusage',
            'absage' => 'absage',
            default => self::STATUS_WAITING,
        };
        $path = match ($key) {
            self::STATUS_REPLIED => $routing['label_replied'] ?? self::DEFAULT_REPLIED,
            'zusage' => 'Status/Zusage',
            'absage' => 'Status/Absage',
            default => $routing['label_waiting'] ?? self::DEFAULT_WAITING,
        };

        return self::statusLabelName(
            [
                'label_root' => $routing['label_root'] ?? '',
                'label_waiting' => $path,
                'label_replied' => $path,
            ],
            $departmentName,
            self::STATUS_WAITING,
        );
    }

    /**
     * @param array{label_root: string, label_waiting: string, label_replied: string} $routing
     * @return list<string>
     */
    public static function allStatusLabelNames(array $routing, string $departmentName): array
    {
        $root = self::resolveRoot($routing, $departmentName);
        $paths = array_values(array_unique(array_filter([
            $routing['label_waiting'] ?? '',
            $routing['label_replied'] ?? '',
            ...self::DEFAULT_STATUS_TREE,
        ])));
        $names = [];
        foreach ($paths as $path) {
            $clean = self::sanitizePath($path);
            if ($clean === '') {
                continue;
            }
            $names[] = $root . '/' . $clean;
            $first = explode('/', $clean)[0] ?? '';
            if ($first !== '' && $first !== $clean) {
                $names[] = $root . '/' . $first;
            }
        }

        return array_values(array_unique($names));
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

    public static function composedRoot(string $departmentName): string
    {
        $name = self::sanitizeSegment($departmentName);
        if ($name === '' || strcasecmp($name, self::DEFAULT_ROOT) === 0) {
            return self::DEFAULT_ROOT;
        }
        if (str_starts_with($name, self::DEFAULT_ROOT . '-')) {
            return $name;
        }

        return self::sanitizeSegment(self::DEFAULT_ROOT . '-' . $name);
    }

    /** Wurzel muss `eMatChef` oder `eMatChef-…` sein — andere Werte werden präfixiert. */
    public static function enforceEmatchefRoot(string $root): string
    {
        $root = self::sanitizeSegment($root);
        if ($root === '' || strcasecmp($root, self::DEFAULT_ROOT) === 0) {
            return self::DEFAULT_ROOT;
        }
        if (str_starts_with($root, self::DEFAULT_ROOT . '-')) {
            return $root;
        }

        return self::sanitizeSegment(self::DEFAULT_ROOT . '-' . $root);
    }

    /**
     * @param list<string> $labelNames
     */
    public static function hasRootLabel(array $labelNames, string $root): bool
    {
        $root = self::sanitizeSegment($root);
        if ($root === '') {
            return false;
        }
        foreach ($labelNames as $name) {
            $name = (string) $name;
            if ($name === $root || str_starts_with($name, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    /** Gmail-Suche: Leerzeichen im Label werden zu Bindestrichen. */
    public static function inboxQuery(string $root, string $window = 'newer_than:21d'): string
    {
        $token = 'label:' . str_replace(' ', '-', self::sanitizeSegment($root));

        return $window !== '' ? $token . ' ' . $window : $token;
    }

    /**
     * @param array{label_root?: string} $routing
     */
    public static function resolveRoot(array $routing, string $departmentName = ''): string
    {
        $root = self::enforceEmatchefRoot((string) ($routing['label_root'] ?? ''));
        if ($root === self::DEFAULT_ROOT) {
            return self::composedRoot($departmentName);
        }

        return $root;
    }

    /**
     * @param list<string> $names
     */
    public static function suggestRoot(array $names, string $departmentName): string
    {
        $childCount = [];
        foreach ($names as $name) {
            if (!str_contains($name, '/')) {
                $childCount[$name] = $childCount[$name] ?? 0;
            }
        }
        foreach ($names as $name) {
            $slash = strpos($name, '/');
            if ($slash === false) {
                continue;
            }
            $root = substr($name, 0, $slash);
            $childCount[$root] = ($childCount[$root] ?? 0) + 1;
        }
        $composed = self::composedRoot($departmentName);
        if (isset($childCount[$composed])) {
            return $composed;
        }
        if (isset($childCount[self::DEFAULT_ROOT])) {
            return self::DEFAULT_ROOT;
        }
        $prefer = self::sanitizeSegment($departmentName);
        if ($prefer !== '' && isset($childCount[$prefer])) {
            return self::enforceEmatchefRoot($prefer);
        }
        arsort($childCount);
        foreach ($childCount as $root => $count) {
            if ($count > 0) {
                return self::enforceEmatchefRoot((string) $root);
            }
        }

        return $composed;
    }

    /**
     * @param list<string> $names
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
    public static function importFromGmail(array $names, string $root, string $referencePrefix = ''): array
    {
        $root = self::sanitizeSegment($root);
        $defaults = self::defaults();
        if ($root === '') {
            return self::normalize(['reference_prefix' => $referencePrefix]);
        }
        $relPaths = [];
        foreach ($names as $name) {
            if ($name === $root) {
                continue;
            }
            $prefix = $root . '/';
            if (!str_starts_with($name, $prefix)) {
                continue;
            }
            $rel = substr($name, strlen($prefix));
            if ($rel !== '') {
                $relPaths[] = $rel;
            }
        }
        $firstLevel = [];
        $descendants = [];
        foreach ($relPaths as $rel) {
            $seg = explode('/', $rel)[0] ?? '';
            if ($seg === '') {
                continue;
            }
            $firstLevel[$seg] = true;
            $descendants[$seg] = ($descendants[$seg] ?? 0) + (str_contains($rel, '/') ? 1 : 0);
        }
        $inquiries = '';
        foreach (array_keys($firstLevel) as $seg) {
            if (preg_match('/firmenanfragen|anfragen|partner/i', $seg) === 1) {
                $inquiries = $seg;
                break;
            }
        }
        if ($inquiries === '' && $descendants !== []) {
            arsort($descendants);
            foreach ($descendants as $seg => $_count) {
                if (preg_match('/status|wartet|waiting|antwort|reply/i', (string) $seg) === 1) {
                    continue;
                }
                $inquiries = (string) $seg;
                break;
            }
        }
        $waiting = '';
        $replied = '';
        foreach ($relPaths as $rel) {
            if (preg_match('/wartet|waiting/i', $rel) === 1) {
                if ($waiting === '') {
                    $waiting = $rel;
                }
                continue;
            }
            if (preg_match('/antwort|reply/i', $rel) === 1 && $replied === '') {
                $replied = $rel;
            }
        }
        $packageKids = 0;
        $extras = [];
        $inquiriesPrefix = $inquiries !== '' ? $inquiries . '/' : null;
        foreach ($relPaths as $rel) {
            if ($rel === $inquiries || $rel === $waiting || $rel === $replied) {
                continue;
            }
            if ($waiting !== '' && ($rel === explode('/', $waiting)[0] || str_starts_with($waiting, $rel . '/'))) {
                continue;
            }
            if ($replied !== '' && ($rel === explode('/', $replied)[0] || str_starts_with($replied, $rel . '/'))) {
                continue;
            }
            if ($inquiriesPrefix !== null && str_starts_with($rel, $inquiriesPrefix)) {
                ++$packageKids;
                continue;
            }
            $extras[] = $root . '/' . $rel;
        }

        return self::normalize([
            'label_root' => $root,
            'label_inquiries' => $inquiries,
            'label_waiting' => $waiting !== '' ? $waiting : $defaults['label_waiting'],
            'label_replied' => $replied !== '' ? $replied : $defaults['label_replied'],
            'label_by_package' => $packageKids > 0,
            'extra_labels' => $extras,
            'reference_prefix' => $referencePrefix,
        ]);
    }

    /**
     * Direkte Kinder unter Wurzel/Anfragen-Ordner (z. B. PFF27/Firmenanfragen/Bauholz).
     *
     * @param list<string> $gmailNames
     * @return list<string>
     */
    public static function inquiryCategoryNames(array $gmailNames, string $root, string $inquiries): array
    {
        $root = self::sanitizeSegment($root);
        $inquiries = self::sanitizePath($inquiries);
        if ($root === '' || $inquiries === '') {
            return [];
        }
        $prefix = $root . '/' . $inquiries . '/';
        $names = [];
        foreach ($gmailNames as $name) {
            if (!str_starts_with($name, $prefix)) {
                continue;
            }
            $rest = substr($name, strlen($prefix));
            if ($rest === '' || str_contains($rest, '/')) {
                continue;
            }
            $segment = self::sanitizeSegment($rest);
            if ($segment !== '') {
                $names[] = $segment;
            }
        }
        $names = array_values(array_unique($names));
        natcasesort($names);

        return array_values($names);
    }

    /**
     * Labels unter der Wurzel, die eMatChef nicht setzt — nur Hinweis, nie automatisch löschen.
     *
     * @param list<string> $gmailNames
     * @param list<string> $wanted
     * @return list<string>
     */
    public static function unusedGmailLabels(array $gmailNames, array $wanted, string $root): array
    {
        $root = self::sanitizeSegment($root);
        if ($root === '') {
            return [];
        }
        $wantedSet = [];
        foreach ($wanted as $name) {
            if ($name !== '') {
                $wantedSet[$name] = true;
            }
        }
        $prefix = $root . '/';
        $unused = [];
        foreach ($gmailNames as $name) {
            if ($name === $root) {
                continue;
            }
            if ($name !== $root && !str_starts_with($name, $prefix)) {
                continue;
            }
            if (isset($wantedSet[$name])) {
                continue;
            }
            $isParent = false;
            foreach ($wanted as $keep) {
                if ($keep !== '' && str_starts_with($keep, $name . '/')) {
                    $isParent = true;
                    break;
                }
            }
            if ($isParent) {
                continue;
            }
            $unused[] = $name;
        }
        $unused = array_values(array_unique($unused));
        usort($unused, static fn (string $a, string $b) => substr_count($b, '/') <=> substr_count($a, '/') ?: strcasecmp($a, $b));

        return $unused;
    }
}
