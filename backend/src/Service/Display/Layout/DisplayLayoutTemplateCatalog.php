<?php

namespace App\Service\Display\Layout;

/**
 * Offizielle Vorlagen für Infoscreen-Layouts. Sie liegen bewusst im Code (versioniert, testbar) und nicht in der
 * Datenbank. Eine Vorlage wird immer als vollständig unabhängige Kopie in den Entwurf des Infoscreens übernommen;
 * spätere Änderungen an der Vorlage berühren bestehende Layouts nicht (der Entwurf speichert nur Vorlagen-ID und -Version
 * als Herkunftsangabe).
 */
final class DisplayLayoutTemplateCatalog
{
    public const BLANK = 'blank';

    /**
     * @return list<array{id: string, version: int, contexts: list<string>, layout: array<string, mixed>}>
     */
    public function all(): array
    {
        return [
            ['id' => self::BLANK, 'version' => 1, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS, DisplayWidgetRegistry::CONTEXT_DEPARTMENT], 'layout' => $this->blank()],
            ['id' => 'ga_materiallager', 'version' => 1, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS], 'layout' => $this->materiallager()],
            ['id' => 'ga_baubuero', 'version' => 1, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS], 'layout' => $this->baubuero()],
            ['id' => 'ga_holzbau', 'version' => 1, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS], 'layout' => $this->holzbau()],
            ['id' => 'ga_disposition', 'version' => 1, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS], 'layout' => $this->disposition()],
        ];
    }

    /**
     * @return list<array{id: string, version: int, contexts: list<string>, layout: array<string, mixed>}>
     */
    public function forContext(string $context): array
    {
        return array_values(array_filter($this->all(), static fn (array $t) => \in_array($context, $t['contexts'], true)));
    }

    /**
     * @return array{id: string, version: int, contexts: list<string>, layout: array<string, mixed>}|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }

        return null;
    }

    // --- Bausteine -----------------------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function w(string $type, array $config = []): array
    {
        return ['type' => 'widget', 'widget' => $type, 'widget_version' => 1, 'config' => $config];
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    private function i(string $id, int $x, int $y, int $w, int $h, array $content): array
    {
        return ['id' => $id, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'content' => $content];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function page(string $id, string $name, array $items): array
    {
        return ['id' => $id, 'name' => $name, 'rows' => DisplayLayoutValidator::DEFAULT_ROWS, 'items' => $items];
    }

    /**
     * @param array<string, mixed> $root
     *
     * @return array<string, mixed>
     */
    private function variant(string $id, string $name, string $aspect, array $root): array
    {
        return ['id' => $id, 'name' => $name, 'aspect_ratio' => $aspect, 'root' => $root];
    }

    /**
     * @param list<array<string, mixed>> $variants
     * @param list<array<string, mixed>> $pages
     *
     * @return array<string, mixed>
     */
    private function doc(array $variants, array $pages): array
    {
        return ['schema_version' => DisplayLayoutValidator::SCHEMA_VERSION, 'default_variant_id' => $variants[0]['id'], 'variants' => $variants, 'pages' => $pages];
    }

    /**
     * @param list<array{0: array<string, mixed>, 1: ?int}> $steps
     *
     * @return array<string, mixed>
     */
    private function rotation(int $interval, array $steps, bool $enabled = true): array
    {
        return [
            'type' => 'rotation',
            'enabled' => $enabled,
            'interval_sec' => $interval,
            'steps' => array_map(static fn (array $s) => ['duration_sec' => $s[1], 'content' => $s[0]], $steps),
        ];
    }

    /** @return array<string, mixed> */
    private function ref(string $pageId): array
    {
        return ['type' => 'page', 'page_id' => $pageId];
    }

    /**
     * Kopfzeile mit Titel und Uhr.
     *
     * @return list<array<string, mixed>>
     */
    private function head(string $title): array
    {
        return [
            $this->i('i-title', 0, 0, 9, 1, $this->w('text', ['text' => $title, 'level' => 'title'])),
            $this->i('i-clock', 9, 0, 3, 1, $this->w('clock', ['format' => 'time'])),
        ];
    }

    // --- Vorlagen ------------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function blank(): array
    {
        return $this->doc(
            [$this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main'))],
            [$this->page('p-main', 'Hauptseite', [])],
        );
    }

    /** @return array<string, mixed> */
    private function materiallager(): array
    {
        $rotation = $this->rotation(12, [
            [$this->w('ga_material_overview'), null],
            [$this->w('ga_pack_progress'), null],
            [$this->w('ga_missing_material'), null],
            [$this->w('ga_transport_overview', ['view' => 'material']), null],
            [$this->w('ga_goods_receipt'), null],
        ]);

        return $this->doc(
            [
                $this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main')),
                $this->variant('v-compact', 'Kompakt', '16:9', $this->ref('p-compact')),
            ],
            [
                $this->page('p-main', 'Materiallager', [
                    ...$this->head('Materiallager'),
                    $this->i('i-rot', 0, 1, 8, 11, $rotation),
                    $this->i('i-feed', 8, 1, 4, 11, $this->w('ga_activity_feed', ['area' => 'material'])),
                ]),
                $this->page('p-compact', 'Materiallager kompakt', [
                    ...$this->head('Materiallager'),
                    $this->i('i-rot', 0, 1, 12, 11, $rotation),
                ]),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function baubuero(): array
    {
        return $this->doc(
            [$this->variant('v-std', 'Standard 16:9', '16:9', $this->rotation(15, [[$this->ref('p-dashboard'), null], [$this->ref('p-map'), null]]))],
            [
                $this->page('p-dashboard', 'Übersicht', [
                    ...$this->head('Baubüro'),
                    $this->i('i-projects', 0, 1, 7, 7, $this->w('ga_projects')),
                    $this->i('i-warnings', 7, 1, 5, 3, $this->w('ga_warnings')),
                    $this->i('i-tasks', 7, 4, 5, 4, $this->w('ga_tasks')),
                    $this->i('i-feed', 0, 8, 12, 4, $this->w('ga_activity_feed')),
                ]),
                $this->page('p-map', 'Lageplan', [
                    ...$this->head('Baubüro'),
                    $this->i('i-map', 0, 1, 12, 11, $this->w('ga_site_map')),
                ]),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function holzbau(): array
    {
        return $this->doc(
            [$this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main'))],
            [
                $this->page('p-main', 'Holzbau', [
                    ...$this->head('Holzbau'),
                    $this->i('i-projects', 0, 1, 12, 11, $this->w('ga_projects', [
                        'filter_ressort' => 'Demo-Bauten',
                        'filter_bereich' => 'Bühne & Gerüst',
                        'rotate' => true,
                    ])),
                ]),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function disposition(): array
    {
        $main = [
            ...$this->head('Disposition'),
            $this->i('i-transport', 0, 1, 6, 11, $this->w('ga_transport_overview', ['view' => 'transports'])),
            $this->i('i-tours', 6, 1, 6, 6, $this->w('ga_dispatch_tours', ['view' => 'tours'])),
            $this->i('i-resources', 6, 7, 6, 5, $this->w('ga_dispatch_tours', ['view' => 'resources'])),
        ];

        return $this->doc(
            [
                $this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main')),
                $this->variant('v-compact', 'Kompakt', '16:9', $this->rotation(15, [[$this->ref('p-transport'), null], [$this->ref('p-tours'), null]])),
            ],
            [
                $this->page('p-main', 'Disposition', $main),
                $this->page('p-transport', 'Transporte', [
                    ...$this->head('Disposition'),
                    $this->i('i-transport', 0, 1, 12, 11, $this->w('ga_transport_overview', ['view' => 'transports'])),
                ]),
                $this->page('p-tours', 'Touren', [
                    ...$this->head('Disposition'),
                    $this->i('i-tours', 0, 1, 12, 11, $this->w('ga_dispatch_tours', ['view' => 'tours'])),
                ]),
            ],
        );
    }
}
