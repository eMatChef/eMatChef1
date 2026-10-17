<?php

namespace App\Service\Display\Layout;

/**
 * Zentrale Widget-Registry für Infoscreen-Layouts (Grossanlass und normale Departments).
 *
 * Jedes Widget hat einen eindeutigen Typ, eine versionierte Konfiguration mit festem Schema (nur die hier
 * deklarierten Felder, nie HTML/JavaScript), erlaubte Kontexte, Standardwerte, eine Datenquellenkennung und eine
 * Mindestgrösse im Raster. Der Renderer im Frontend ist über den Typ zugeordnet.
 */
final class DisplayWidgetRegistry
{
    public const CONTEXT_GROSSANLASS = 'grossanlass';
    public const CONTEXT_DEPARTMENT = 'department';

    public const SOURCE_STATIC = 'static';
    public const SOURCE_CLOCK = 'clock';
    /** Bestehende Demo-/Seed-Daten der GA-Anzeigebeispiele (keine Live-Daten). */
    public const SOURCE_GA_DEMO = 'ga_demo';

    private const ALL = [self::CONTEXT_GROSSANLASS, self::CONTEXT_DEPARTMENT];
    private const GA = [self::CONTEXT_GROSSANLASS];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $definitions = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->definitions ??= $this->build();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $type): ?array
    {
        return $this->all()[$type] ?? null;
    }

    /**
     * Widgets, die im Kontext angeboten werden dürfen (Fachwidgets nie vermischt).
     *
     * @return list<array<string, mixed>>
     */
    public function forContext(string $context): array
    {
        $rows = [];
        foreach ($this->all() as $type => $def) {
            if (\in_array($context, $def['contexts'], true)) {
                $rows[] = ['type' => $type] + $def;
            }
        }

        return $rows;
    }

    /**
     * Prüft und normalisiert die Konfiguration eines Widgets: unbekannte Felder werden abgelehnt,
     * fehlende Felder erhalten den Standardwert.
     *
     * @param array<string, mixed> $config
     * @param list<array{path: string, code: string, message: string}> $errors
     *
     * @return array<string, mixed>
     */
    public function normalizeConfig(string $type, array $config, string $path, array &$errors): array
    {
        $def = $this->get($type);
        if ($def === null) {
            return [];
        }
        $out = [];
        foreach (array_keys($config) as $key) {
            if (!isset($def['fields'][$key])) {
                $errors[] = ['path' => "$path.$key", 'code' => 'config_unknown_field', 'message' => "Unbekanntes Feld «{$key}»."];
            }
        }
        foreach ($def['fields'] as $key => $field) {
            $fieldPath = "$path.$key";
            if (!\array_key_exists($key, $config)) {
                $out[$key] = $field['default'];
                continue;
            }
            $value = $this->normalizeField($field, $config[$key], $fieldPath, $errors);
            $out[$key] = $value ?? $field['default'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $field
     * @param list<array{path: string, code: string, message: string}> $errors
     */
    private function normalizeField(array $field, mixed $value, string $path, array &$errors): mixed
    {
        $invalid = static function (string $code, string $message) use (&$errors, $path): null {
            $errors[] = ['path' => $path, 'code' => $code, 'message' => $message];

            return null;
        };

        switch ($field['type']) {
            case 'string':
            case 'text':
                if (!\is_string($value)) {
                    return $invalid('config_invalid_value', 'Text erwartet.');
                }
                $value = trim($value);
                if (mb_strlen($value) > $field['max']) {
                    return $invalid('config_too_long', 'Text ist zu lang.');
                }
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                    return $invalid('config_invalid_value', 'Ungültige Zeichen.');
                }

                return $value;
            case 'int':
                if (!\is_int($value) && !(\is_string($value) && preg_match('/^-?\d+$/', $value))) {
                    return $invalid('config_invalid_value', 'Ganzzahl erwartet.');
                }
                $value = (int) $value;
                if ($value < $field['min'] || $value > $field['max']) {
                    return $invalid('config_out_of_range', 'Wert ausserhalb des erlaubten Bereichs.');
                }

                return $value;
            case 'bool':
                if (!\is_bool($value)) {
                    return $invalid('config_invalid_value', 'Wahrheitswert erwartet.');
                }

                return $value;
            case 'enum':
                if (!\is_string($value) || !\in_array($value, $field['values'], true)) {
                    return $invalid('config_invalid_value', 'Ungültige Auswahl.');
                }

                return $value;
            case 'url':
                if (!\is_string($value)) {
                    return $invalid('config_invalid_value', 'Adresse erwartet.');
                }
                $value = trim($value);
                if ($value === '') {
                    return '';
                }
                if (mb_strlen($value) > 500 || !preg_match('#^https://[^\s<>"\'\\\\]+$#i', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                    return $invalid('config_invalid_url', 'Nur https-Adressen sind erlaubt.');
                }

                return $value;
            case 'lines':
                if (!\is_array($value) || !array_is_list($value) || \count($value) > $field['max_items']) {
                    return $invalid('config_invalid_value', 'Liste erwartet.');
                }
                $lines = [];
                foreach ($value as $i => $line) {
                    if (!\is_string($line) || mb_strlen(trim($line)) > $field['max'] || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $line)) {
                        return $invalid('config_invalid_value', "Eintrag $i ist ungültig.");
                    }
                    if (trim($line) !== '') {
                        $lines[] = trim($line);
                    }
                }

                return $lines;
        }

        return $invalid('config_invalid_value', 'Unbekannter Feldtyp.');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function build(): array
    {
        $str = static fn (int $max, string $default = ''): array => ['type' => 'string', 'max' => $max, 'default' => $default];
        $enum = static fn (array $values, string $default): array => ['type' => 'enum', 'values' => $values, 'default' => $default];
        $int = static fn (int $min, int $max, int $default): array => ['type' => 'int', 'min' => $min, 'max' => $max, 'default' => $default];
        $filter = [
            'title' => $str(80),
            'filter_ressort' => $str(80),
            'filter_bereich' => $str(80),
            'filter_project' => $str(80),
        ];

        $defs = [
            'header' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_CLOCK, 'min_size' => [4, 1], 'default_size' => [12, 1],
                'fields' => [
                    'title' => $str(120),
                    'subtitle' => $str(160),
                    'show_logo' => ['type' => 'bool', 'default' => true],
                    'show_clock' => ['type' => 'bool', 'default' => true],
                ],
            ],
            'text' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_STATIC, 'min_size' => [2, 1], 'default_size' => [6, 2],
                'fields' => [
                    'text' => ['type' => 'text', 'max' => 1000, 'default' => ''],
                    'level' => $enum(['title', 'heading', 'body', 'caption'], 'body'),
                    'align' => $enum(['left', 'center', 'right'], 'left'),
                ],
            ],
            'clock' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_CLOCK, 'min_size' => [2, 1], 'default_size' => [3, 2],
                'fields' => [
                    'format' => $enum(['time', 'time_seconds', 'date', 'datetime'], 'time'),
                    'align' => $enum(['left', 'center', 'right'], 'right'),
                ],
            ],
            'image' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_STATIC, 'min_size' => [1, 1], 'default_size' => [3, 3],
                'fields' => [
                    'source' => $enum(['emc_logo', 'url'], 'emc_logo'),
                    'url' => ['type' => 'url', 'default' => ''],
                    'alt' => $str(200),
                    'fit' => $enum(['contain', 'cover'], 'contain'),
                ],
            ],
            'notice' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_STATIC, 'min_size' => [3, 2], 'default_size' => [6, 2],
                'fields' => [
                    'title' => $str(120),
                    'text' => ['type' => 'text', 'max' => 600, 'default' => ''],
                    'severity' => $enum(['info', 'warning', 'critical'], 'info'),
                ],
            ],
            'divider' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_STATIC, 'min_size' => [1, 1], 'default_size' => [12, 1],
                'fields' => [
                    'orientation' => $enum(['horizontal', 'vertical'], 'horizontal'),
                    'weight' => $enum(['thin', 'normal', 'thick'], 'normal'),
                ],
            ],
            'kpi' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_STATIC, 'min_size' => [2, 2], 'default_size' => [3, 3],
                'fields' => [
                    'label' => $str(80),
                    'value' => $str(40),
                    'unit' => $str(20),
                    'caption' => $str(120),
                ],
            ],
            'list' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_STATIC, 'min_size' => [3, 2], 'default_size' => [4, 4],
                'fields' => [
                    'title' => $str(80),
                    'items' => ['type' => 'lines', 'max' => 160, 'max_items' => 20, 'default' => []],
                    'ordered' => ['type' => 'bool', 'default' => false],
                ],
            ],
            'progress' => [
                'category' => 'general', 'contexts' => self::ALL, 'data_source' => self::SOURCE_STATIC, 'min_size' => [3, 2], 'default_size' => [4, 2],
                'fields' => [
                    'label' => $str(80),
                    'value' => $int(0, 100, 0),
                    'caption' => $str(120),
                ],
            ],
            'ga_material_overview' => $this->ga('material', $filter, ['view' => $enum(['overview'], 'overview')], [4, 4], [6, 6]),
            'ga_pack_progress' => $this->ga('material', $filter, ['view' => $enum(['pack'], 'pack')], [4, 4], [6, 6]),
            'ga_missing_material' => $this->ga('material', $filter, ['view' => $enum(['missing'], 'missing')], [4, 4], [6, 6]),
            'ga_goods_receipt' => $this->ga('material', $filter, ['view' => $enum(['incoming'], 'incoming')], [4, 4], [6, 6]),
            'ga_transport_overview' => $this->ga('logistik', $filter, ['view' => $enum(['material', 'overview', 'transports'], 'transports')], [4, 4], [6, 6]),
            'ga_dispatch_tours' => $this->ga('logistik', $filter, ['view' => $enum(['tours', 'resources'], 'tours')], [4, 4], [6, 6]),
            'ga_overview' => $this->ga('overall', $filter, [], [6, 5], [8, 8]),
            'ga_projects' => $this->ga('projekt', $filter, ['rotate' => ['type' => 'bool', 'default' => true], 'rotate_sec' => $int(5, 600, 15)], [6, 5], [8, 8]),
            'ga_tasks' => $this->ga('tasks', $filter, ['max_items' => $int(3, 20, 8)], [3, 3], [4, 6]),
            'ga_helpers' => [
                'category' => 'ga', 'contexts' => self::GA, 'data_source' => self::SOURCE_STATIC, 'min_size' => [3, 3], 'default_size' => [4, 4],
                'fields' => [
                    'title' => $str(80, ''),
                    'lines' => ['type' => 'lines', 'max' => 200, 'max_items' => 12, 'default' => []],
                ],
            ],
            'ga_site_map' => $this->ga('map', $filter, [], [4, 4], [6, 6]),
            'ga_activity_feed' => $this->ga('feed', $filter, ['area' => $enum(['all', 'material', 'logistics', 'task', 'sale', 'problem'], 'all'), 'max_items' => $int(3, 20, 8)], [3, 3], [4, 6]),
            'ga_warnings' => $this->ga('warnings', $filter, ['max_items' => $int(1, 12, 5)], [3, 2], [4, 3]),
        ];

        foreach ($defs as $type => &$def) {
            $def['version'] = 1;
            $def['supported_versions'] = [1];
        }

        return $defs;
    }

    /**
     * @param array<string, array<string, mixed>> $filter
     * @param array<string, array<string, mixed>> $extra
     * @param array{int, int} $min
     * @param array{int, int} $default
     *
     * @return array<string, mixed>
     */
    private function ga(string $family, array $filter, array $extra, array $min, array $default): array
    {
        return [
            'category' => 'ga',
            'family' => $family,
            'contexts' => self::GA,
            'data_source' => self::SOURCE_GA_DEMO,
            'min_size' => $min,
            'default_size' => $default,
            'fields' => $filter + $extra,
        ];
    }
}
