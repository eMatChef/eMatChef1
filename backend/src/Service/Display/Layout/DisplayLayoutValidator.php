<?php

namespace App\Service\Display\Layout;

/**
 * Validiert und normalisiert Layout-Dokumente (Schema Version 1) für Infoscreens.
 *
 * Dokument: { schema_version, default_variant_id, variants[], pages[] }
 *  - Variante: { id, name, aspect_ratio, root: Inhalt }  (z. B. «Standard 16:9», «Kompakt», «LED-Wall 3:2»)
 *  - Seite (wiederverwendbarer Baustein): { id, name, rows, items[] } auf einem 12-Spalten-Raster
 *  - Element (Zone): { id, x, y, w, h, content: Inhalt }
 *  - Inhalt: widget | group (Widgets in einer Zone) | page (eingebettetes Layout) | rotation (Folge von Inhalten)
 *
 * Seiten werden nur innerhalb des Dokuments referenziert; es gibt keine Verweise auf veränderbare offizielle Vorlagen.
 * Verschachtelung und Grösse sind begrenzt, zyklische Seitenverweise werden abgelehnt.
 */
final class DisplayLayoutValidator
{
    public const SCHEMA_VERSION = 1;
    public const COLUMNS = 12;
    public const MAX_ROWS = 48;
    public const MAX_COLUMNS = 48;
    public const DATA_MODES = ['live', 'demo'];
    public const DEFAULT_DATA_MODE = 'live';
    public const DEFAULT_ROWS = 12;
    public const MAX_DEPTH = 6;
    public const MAX_VARIANTS = 6;
    public const MAX_PAGES = 20;
    public const MAX_ITEMS = 40;
    public const MAX_STEPS = 20;
    public const MAX_EXPANDED_NODES = 2000;
    public const MAX_JSON_BYTES = 262144;
    public const MAX_ERRORS = 50;
    public const MIN_INTERVAL = 3;
    public const MAX_INTERVAL = 3600;
    public const DEFAULT_INTERVAL = 20;

    private const ID_PATTERN = '/^[A-Za-z0-9_-]{1,40}$/';

    /** @var list<array{path: string, code: string, message: string}> */
    private array $errors = [];
    private bool $strict = false;
    private string $context = '';
    private string $dataMode = self::DEFAULT_DATA_MODE;
    /** @var array<string, array{refs: list<array{0: string, 1: int}>, depth: int, nodes: int}> */
    private array $pageInfo = [];
    /** @var list<array{refs: list<array{0: string, 1: int}>, depth: int, nodes: int}> */
    private array $variantInfo = [];
    /** @var list<array{path: string, widgets: int}> */
    private array $widgetCounts = [];
    /** @var array{refs: list<array{0: string, 1: int}>, depth: int, nodes: int, widgets: int} */
    private array $acc = ['refs' => [], 'depth' => 0, 'nodes' => 0, 'widgets' => 0];

    public function __construct(private DisplayWidgetRegistry $registry)
    {
    }

    /**
     * @param mixed $layout
     *
     * @return array{layout: ?array<string, mixed>, errors: list<array{path: string, code: string, message: string}>}
     */
    public function validate(mixed $layout, string $context, bool $strict): array
    {
        $this->errors = [];
        $this->strict = $strict;
        $this->context = $context;
        $this->pageInfo = [];
        $this->variantInfo = [];

        if (!\is_array($layout) || array_is_list($layout) && $layout !== []) {
            return $this->fail('layout', 'layout_invalid', 'Layout muss ein Objekt sein.');
        }
        $encoded = json_encode($layout);
        if ($encoded === false || \strlen($encoded) > self::MAX_JSON_BYTES) {
            return $this->fail('layout', 'layout_too_large', 'Layout ist zu gross.');
        }
        $this->checkKeys($layout, ['schema_version', 'default_variant_id', 'variants', 'pages', 'data_mode'], 'layout');
        $dataMode = $layout['data_mode'] ?? self::DEFAULT_DATA_MODE;
        if (!\is_string($dataMode) || !\in_array($dataMode, self::DATA_MODES, true)) {
            $this->error('layout.data_mode', 'data_mode_invalid', 'Datenmodus muss «live» oder «demo» sein.');
            $dataMode = self::DEFAULT_DATA_MODE;
        }
        $this->dataMode = $dataMode;
        if (($layout['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            return $this->fail('layout.schema_version', 'schema_version_unsupported', 'Nicht unterstützte Schemaversion.');
        }

        $pages = $this->normalizePages($layout['pages'] ?? null);
        $variants = $this->normalizeVariants($layout['variants'] ?? null);

        $defaultId = $layout['default_variant_id'] ?? null;
        $variantIds = array_column($variants, 'id');
        if (!\is_string($defaultId) || !\in_array($defaultId, $variantIds, true)) {
            $this->error('layout.default_variant_id', 'default_variant_missing', 'Die Standardvariante existiert nicht.');
        }

        $this->analyzeGraph($variants);

        $errors = \array_slice($this->errors, 0, self::MAX_ERRORS);
        if ($errors !== []) {
            return ['layout' => null, 'errors' => $errors];
        }

        $normalized = [
            'schema_version' => self::SCHEMA_VERSION,
            'default_variant_id' => $defaultId,
            'variants' => $variants,
            'pages' => $pages,
        ];
        // «live» ist der Standard und wird nicht gespeichert; nur der ausdrückliche Demomodus steht im Dokument.
        if ($dataMode !== self::DEFAULT_DATA_MODE) {
            $normalized['data_mode'] = $dataMode;
        }

        return ['layout' => $normalized, 'errors' => []];
    }

    /**
     * Prüft einen einzelnen Baustein (Widget oder Gruppe) für die Bausteinbibliothek. Verweise auf Seiten und
     * Rotationen sind dort nicht erlaubt, damit ein Baustein in jedem Layout eigenständig einsetzbar ist.
     *
     * @return array{content: ?array<string, mixed>, errors: list<array{path: string, code: string, message: string}>}
     */
    public function validateBlock(mixed $content, string $context): array
    {
        $this->errors = [];
        $this->strict = false;
        $this->context = $context;
        $this->dataMode = self::DEFAULT_DATA_MODE;
        $this->acc = ['refs' => [], 'depth' => 0, 'nodes' => 0, 'widgets' => 0];

        $normalized = $this->normalizeContent($content, 'block', 0);
        if (!\in_array($normalized['type'], ['widget', 'group', 'rotation'], true) || $this->acc['refs'] !== []) {
            $this->error('block', 'block_type_invalid', 'Ein Baustein darf nur Widgets, Gruppen und Rotationen enthalten (keine Verweise auf Seiten).');
        }
        if ($this->acc['widgets'] === 0) {
            $this->error('block', 'block_empty', 'Der Baustein enthält kein Widget.');
        }
        $errors = \array_slice($this->errors, 0, self::MAX_ERRORS);

        return ['content' => $errors === [] ? $normalized : null, 'errors' => $errors];
    }

    /**
     * @return array{layout: null, errors: list<array{path: string, code: string, message: string}>}
     */
    private function fail(string $path, string $code, string $message): array
    {
        return ['layout' => null, 'errors' => [['path' => $path, 'code' => $code, 'message' => $message]]];
    }

    private function error(string $path, string $code, string $message): void
    {
        if (\count($this->errors) < self::MAX_ERRORS + 1) {
            $this->errors[] = ['path' => $path, 'code' => $code, 'message' => $message];
        }
    }

    /**
     * @param array<mixed> $node
     * @param list<string> $allowed
     */
    private function checkKeys(array $node, array $allowed, string $path): void
    {
        foreach (array_keys($node) as $key) {
            if (!\in_array($key, $allowed, true)) {
                $this->error("$path.$key", 'unknown_key', "Unbekanntes Feld «{$key}».");
            }
        }
    }

    private function validId(mixed $id, string $path): ?string
    {
        if (!\is_string($id) || !preg_match(self::ID_PATTERN, $id)) {
            $this->error($path, 'id_invalid', 'Ungültige Kennung.');

            return null;
        }

        return $id;
    }

    private function name(mixed $value, string $path, string $fallback = ''): string
    {
        if (!\is_string($value) || mb_strlen(trim($value)) > 80) {
            $this->error($path, 'name_invalid', 'Name ist ungültig (maximal 80 Zeichen).');

            return $fallback;
        }

        return trim($value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizePages(mixed $raw): array
    {
        if (!\is_array($raw) || !array_is_list($raw)) {
            $this->error('layout.pages', 'pages_invalid', 'Seiten müssen eine Liste sein.');

            return [];
        }
        if (\count($raw) > self::MAX_PAGES) {
            $this->error('layout.pages', 'too_many_pages', 'Zu viele Seiten.');

            return [];
        }
        $pages = [];
        $seen = [];
        foreach ($raw as $i => $page) {
            $path = "layout.pages[$i]";
            if (!\is_array($page)) {
                $this->error($path, 'page_invalid', 'Seite ist ungültig.');
                continue;
            }
            $this->checkKeys($page, ['id', 'name', 'rows', 'columns', 'items'], $path);
            $id = $this->validId($page['id'] ?? null, "$path.id");
            if ($id !== null) {
                if (isset($seen[$id])) {
                    $this->error("$path.id", 'id_duplicate', 'Seitenkennung doppelt.');
                }
                $seen[$id] = true;
            }
            $rows = $this->rows($page['rows'] ?? self::DEFAULT_ROWS, "$path.rows");
            $columns = $this->columns($page['columns'] ?? self::COLUMNS, "$path.columns");
            $this->acc = ['refs' => [], 'depth' => 1, 'nodes' => 0, 'widgets' => 0];
            $items = $this->normalizeItems($page['items'] ?? [], $rows, $columns, "$path.items", 1);
            if ($id !== null) {
                $this->pageInfo[$id] = ['refs' => $this->acc['refs'], 'depth' => $this->acc['depth'], 'nodes' => $this->acc['nodes'], 'widgets' => $this->acc['widgets']];
            }
            $normalizedPage = ['id' => $id ?? '', 'name' => $this->name($page['name'] ?? '', "$path.name"), 'rows' => $rows];
            if ($columns !== self::COLUMNS) {
                $normalizedPage['columns'] = $columns;
            }
            $normalizedPage['items'] = $items;
            $pages[] = $normalizedPage;
        }

        return $pages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeVariants(mixed $raw): array
    {
        if (!\is_array($raw) || !array_is_list($raw) || $raw === []) {
            $this->error('layout.variants', 'variants_missing', 'Mindestens eine Layoutvariante ist erforderlich.');

            return [];
        }
        if (\count($raw) > self::MAX_VARIANTS) {
            $this->error('layout.variants', 'too_many_variants', 'Zu viele Layoutvarianten.');

            return [];
        }
        $variants = [];
        $seen = [];
        foreach ($raw as $i => $variant) {
            $path = "layout.variants[$i]";
            if (!\is_array($variant)) {
                $this->error($path, 'variant_invalid', 'Variante ist ungültig.');
                continue;
            }
            $this->checkKeys($variant, ['id', 'name', 'aspect_ratio', 'root'], $path);
            $id = $this->validId($variant['id'] ?? null, "$path.id");
            if ($id !== null) {
                if (isset($seen[$id])) {
                    $this->error("$path.id", 'id_duplicate', 'Variantenkennung doppelt.');
                }
                $seen[$id] = true;
            }
            $aspect = $variant['aspect_ratio'] ?? '16:9';
            if (!\is_string($aspect) || !preg_match('/^([1-9]\d?):([1-9]\d?)$/', $aspect)) {
                $this->error("$path.aspect_ratio", 'aspect_invalid', 'Seitenverhältnis wie «16:9» erwartet.');
                $aspect = '16:9';
            }
            $this->acc = ['refs' => [], 'depth' => 0, 'nodes' => 0, 'widgets' => 0];
            $root = $this->normalizeContent($variant['root'] ?? null, "$path.root", 0);
            $info = ['refs' => $this->acc['refs'], 'depth' => $this->acc['depth'], 'nodes' => $this->acc['nodes'], 'widgets' => $this->acc['widgets'], 'path' => $path];
            $this->variantInfo[] = $info;
            $variants[] = ['id' => $id ?? '', 'name' => $this->name($variant['name'] ?? '', "$path.name"), 'aspect_ratio' => $aspect, 'root' => $root];
        }

        return $variants;
    }

    private function rows(mixed $value, string $path): int
    {
        if (!\is_int($value) || $value < 1 || $value > self::MAX_ROWS) {
            $this->error($path, 'rows_invalid', 'Zeilenanzahl muss zwischen 1 und ' . self::MAX_ROWS . ' liegen.');

            return self::DEFAULT_ROWS;
        }

        return $value;
    }

    private function columns(mixed $value, string $path): int
    {
        if (!\is_int($value) || $value < 1 || $value > self::MAX_COLUMNS) {
            $this->error($path, 'columns_invalid', 'Spaltenanzahl muss zwischen 1 und ' . self::MAX_COLUMNS . ' liegen.');

            return self::COLUMNS;
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(mixed $raw, int $rows, int $columns, string $path, int $depth): array
    {
        if (!\is_array($raw) || !array_is_list($raw)) {
            $this->error($path, 'items_invalid', 'Elemente müssen eine Liste sein.');

            return [];
        }
        if (\count($raw) > self::MAX_ITEMS) {
            $this->error($path, 'too_many_items', 'Zu viele Elemente in einer Zone.');

            return [];
        }
        $items = [];
        $seen = [];
        foreach ($raw as $i => $item) {
            $p = "{$path}[$i]";
            if (!\is_array($item)) {
                $this->error($p, 'item_invalid', 'Element ist ungültig.');
                continue;
            }
            $this->checkKeys($item, ['id', 'x', 'y', 'w', 'h', 'content'], $p);
            $id = $this->validId($item['id'] ?? null, "$p.id");
            if ($id !== null) {
                if (isset($seen[$id])) {
                    $this->error("$p.id", 'id_duplicate', 'Elementkennung doppelt.');
                }
                $seen[$id] = true;
            }
            foreach (['x', 'y', 'w', 'h'] as $k) {
                if (!\is_int($item[$k] ?? null)) {
                    $this->error("$p.$k", 'geometry_invalid', 'Ganzzahl erwartet.');
                    $item[$k] = 0;
                }
            }
            if ($item['x'] < 0 || $item['w'] < 1 || $item['x'] + $item['w'] > $columns
                || $item['y'] < 0 || $item['h'] < 1 || $item['y'] + $item['h'] > $rows) {
                $this->error($p, 'out_of_bounds', 'Element liegt ausserhalb des Rasters.');
            }
            $content = $this->normalizeContent($item['content'] ?? null, "$p.content", $depth);
            $items[] = ['id' => $id ?? '', 'x' => $item['x'], 'y' => $item['y'], 'w' => $item['w'], 'h' => $item['h'], 'content' => $content];
        }

        if ($this->strict) {
            $this->strictItemChecks($items, $path);
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function strictItemChecks(array $items, string $path): void
    {
        foreach ($items as $i => $a) {
            if (($a['content']['type'] ?? '') === 'widget') {
                $def = $this->registry->get($a['content']['widget']);
                if ($def !== null && ($a['w'] < $def['min_size'][0] || $a['h'] < $def['min_size'][1])) {
                    $this->error("{$path}[$i]", 'widget_too_small', 'Widget ist zu klein für lesbare Darstellung.');
                }
            }
            for ($j = $i + 1; $j < \count($items); ++$j) {
                $b = $items[$j];
                if ($a['x'] < $b['x'] + $b['w'] && $b['x'] < $a['x'] + $a['w'] && $a['y'] < $b['y'] + $b['h'] && $b['y'] < $a['y'] + $a['h']) {
                    $this->error("{$path}[$j]", 'items_overlap', 'Elemente überlappen sich.');
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeContent(mixed $raw, string $path, int $depth): array
    {
        ++$this->acc['nodes'];
        $this->acc['depth'] = max($this->acc['depth'], $depth + 1);
        if ($depth + 1 > self::MAX_DEPTH) {
            $this->error($path, 'too_deep', 'Verschachtelung ist zu tief.');

            return ['type' => 'group', 'rows' => self::DEFAULT_ROWS, 'items' => []];
        }
        if (!\is_array($raw)) {
            $this->error($path, 'content_invalid', 'Inhalt fehlt oder ist ungültig.');

            return ['type' => 'group', 'rows' => self::DEFAULT_ROWS, 'items' => []];
        }
        $type = $raw['type'] ?? null;

        switch ($type) {
            case 'widget':
                $this->checkKeys($raw, ['type', 'widget', 'widget_version', 'config'], $path);
                $widget = \is_string($raw['widget'] ?? null) ? $raw['widget'] : '';
                $def = $this->registry->get($widget);
                if ($def === null) {
                    $this->error("$path.widget", 'widget_unknown', 'Unbekannter Widget-Typ.');

                    return ['type' => 'widget', 'widget' => $widget, 'widget_version' => 1, 'config' => []];
                }
                if (!\in_array($this->context, $def['contexts'], true)) {
                    $this->error("$path.widget", 'widget_not_allowed', 'Dieses Widget ist in diesem Kontext nicht erlaubt.');
                }
                $version = $raw['widget_version'] ?? $def['version'];
                if (!\is_int($version) || !\in_array($version, $def['supported_versions'], true)) {
                    $this->error("$path.widget_version", 'widget_version_unsupported', 'Widget-Version wird nicht unterstützt.');
                    $version = $def['version'];
                }
                $config = $raw['config'] ?? [];
                if (!\is_array($config) || ($config !== [] && array_is_list($config))) {
                    $this->error("$path.config", 'config_invalid', 'Konfiguration muss ein Objekt sein.');
                    $config = [];
                }
                // Live-Anzeige: Widgets ohne echte Datenanbindung werden nie stillschweigend durch Demodaten ersetzt.
                if ($this->strict && $this->dataMode === self::DEFAULT_DATA_MODE && ($def['live'] ?? false) !== true) {
                    $this->error("$path.widget", 'widget_live_unsupported', "Das Widget «{$widget}» hat noch keine Live-Anbindung. Demodaten für die Anzeige aktivieren oder das Widget entfernen.");
                }
                ++$this->acc['widgets'];

                return [
                    'type' => 'widget',
                    'widget' => $widget,
                    'widget_version' => $version,
                    'config' => $this->registry->normalizeConfig($widget, $config, "$path.config", $this->errors),
                ];

            case 'group':
                $this->checkKeys($raw, ['type', 'name', 'rows', 'columns', 'items'], $path);
                $rows = $this->rows($raw['rows'] ?? self::DEFAULT_ROWS, "$path.rows");
                $columns = $this->columns($raw['columns'] ?? self::COLUMNS, "$path.columns");
                $group = ['type' => 'group'];
                $groupName = $this->name($raw['name'] ?? '', "$path.name");
                if ($groupName !== '') {
                    $group['name'] = $groupName;
                }
                $group['rows'] = $rows;
                if ($columns !== self::COLUMNS) {
                    $group['columns'] = $columns;
                }
                $group['items'] = $this->normalizeItems($raw['items'] ?? [], $rows, $columns, "$path.items", $depth + 1);

                return $group;

            case 'page':
                $this->checkKeys($raw, ['type', 'page_id'], $path);
                $pageId = $this->validId($raw['page_id'] ?? null, "$path.page_id") ?? '';
                $this->acc['refs'][] = [$pageId, $depth + 1];

                return ['type' => 'page', 'page_id' => $pageId];

            case 'rotation':
                $this->checkKeys($raw, ['type', 'enabled', 'interval_sec', 'steps'], $path);
                $enabled = $raw['enabled'] ?? true;
                if (!\is_bool($enabled)) {
                    $this->error("$path.enabled", 'rotation_invalid', 'Wahrheitswert erwartet.');
                    $enabled = true;
                }
                $interval = $this->interval($raw['interval_sec'] ?? self::DEFAULT_INTERVAL, "$path.interval_sec") ?? self::DEFAULT_INTERVAL;
                $steps = $raw['steps'] ?? null;
                if (!\is_array($steps) || !array_is_list($steps) || $steps === [] || \count($steps) > self::MAX_STEPS) {
                    $this->error("$path.steps", 'rotation_steps_invalid', 'Eine Rotation braucht 1 bis ' . self::MAX_STEPS . ' Schritte.');
                    $steps = [];
                }
                $out = [];
                foreach ($steps as $i => $step) {
                    $p = "$path.steps[$i]";
                    if (!\is_array($step)) {
                        $this->error($p, 'rotation_steps_invalid', 'Schritt ist ungültig.');
                        continue;
                    }
                    $this->checkKeys($step, ['name', 'duration_sec', 'content'], $p);
                    $duration = \array_key_exists('duration_sec', $step) && $step['duration_sec'] !== null
                        ? $this->interval($step['duration_sec'], "$p.duration_sec")
                        : null;
                    // Rotationsseite: optionaler Name (Studio), nur gespeichert wenn gesetzt; bestehende Schritte bleiben unverändert.
                    $stepName = \array_key_exists('name', $step) ? $this->name($step['name'], "$p.name") : '';
                    $entry = ['duration_sec' => $duration, 'content' => $this->normalizeContent($step['content'] ?? null, "$p.content", $depth + 1)];
                    if ($stepName !== '') {
                        $entry = ['name' => $stepName] + $entry;
                    }
                    $out[] = $entry;
                }

                return ['type' => 'rotation', 'enabled' => $enabled, 'interval_sec' => $interval, 'steps' => $out];
        }

        $this->error("$path.type", 'content_type_unknown', 'Unbekannter Inhaltstyp.');

        return ['type' => 'group', 'rows' => self::DEFAULT_ROWS, 'items' => []];
    }

    private function interval(mixed $value, string $path): ?int
    {
        if (!\is_int($value) || $value < self::MIN_INTERVAL || $value > self::MAX_INTERVAL) {
            $this->error($path, 'interval_invalid', 'Intervall muss zwischen ' . self::MIN_INTERVAL . ' und ' . self::MAX_INTERVAL . ' Sekunden liegen.');

            return null;
        }

        return $value;
    }

    /**
     * Seitenverweise: unbekannte Ziele, Zyklen, Tiefe und Gesamtgrösse nach Auflösung.
     *
     * @param list<array<string, mixed>> $variants
     */
    private function analyzeGraph(array $variants): void
    {
        $known = $this->pageInfo;
        $state = [];
        $cyclic = false;
        $depthOf = [];
        $nodesOf = [];
        $widgetsOf = [];

        $visit = function (string $id, array $stack) use (&$visit, &$state, &$cyclic, &$depthOf, &$nodesOf, &$widgetsOf, $known): void {
            if (isset($state[$id])) {
                return;
            }
            $info = $known[$id];
            $depth = $info['depth'];
            $nodes = $info['nodes'];
            $widgets = $info['widgets'];
            foreach ($info['refs'] as [$ref, $local]) {
                if (!isset($known[$ref])) {
                    continue;
                }
                if (\in_array($ref, $stack, true) || $ref === $id) {
                    $cyclic = true;
                    continue;
                }
                $visit($ref, [...$stack, $id]);
                $depth = max($depth, $local - 1 + ($depthOf[$ref] ?? 0));
                $nodes = min(self::MAX_EXPANDED_NODES + 1, $nodes + ($nodesOf[$ref] ?? 0));
                $widgets += $widgetsOf[$ref] ?? 0;
            }
            $state[$id] = true;
            $depthOf[$id] = $depth;
            $nodesOf[$id] = $nodes;
            $widgetsOf[$id] = $widgets;
        };

        foreach (array_keys($known) as $id) {
            $visit($id, []);
        }
        if ($cyclic) {
            $this->error('layout.pages', 'page_cycle', 'Zyklische Verweise zwischen Seiten sind nicht erlaubt.');

            return;
        }

        $allRefs = [];
        foreach ($this->variantInfo as $vi) {
            foreach ($vi['refs'] as [$ref]) {
                $allRefs[] = [$ref, $vi['path']];
            }
        }
        foreach ($known as $id => $info) {
            foreach ($info['refs'] as [$ref]) {
                $allRefs[] = [$ref, "layout.pages[$id]"];
            }
        }
        foreach ($allRefs as [$ref, $where]) {
            if ($ref !== '' && !isset($known[$ref])) {
                $this->error($where, 'page_ref_unknown', 'Verwiesene Seite existiert nicht.');
            }
        }

        foreach ($this->variantInfo as $vi) {
            $depth = $vi['depth'];
            $nodes = $vi['nodes'];
            $widgets = $vi['widgets'];
            foreach ($vi['refs'] as [$ref, $local]) {
                if (isset($known[$ref])) {
                    $depth = max($depth, $local - 1 + ($depthOf[$ref] ?? 0));
                    $nodes = min(self::MAX_EXPANDED_NODES + 1, $nodes + ($nodesOf[$ref] ?? 0));
                    $widgets += $widgetsOf[$ref] ?? 0;
                }
            }
            if ($depth > self::MAX_DEPTH) {
                $this->error($vi['path'], 'too_deep', 'Verschachtelung ist zu tief.');
            }
            if ($nodes > self::MAX_EXPANDED_NODES) {
                $this->error($vi['path'], 'too_complex', 'Layout ist zu komplex.');
            }
            if ($this->strict && $widgets === 0) {
                $this->error($vi['path'], 'variant_empty', 'Die Variante enthält kein Widget.');
            }
        }
    }
}
