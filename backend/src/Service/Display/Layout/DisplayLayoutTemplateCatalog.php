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
            ['id' => 'ga_materiallager', 'version' => 2, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS], 'layout' => $this->materiallager()],
            ['id' => 'ga_baubuero', 'version' => 2, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS], 'layout' => $this->baubuero()],
            ['id' => 'ga_projekt', 'version' => 2, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS], 'layout' => $this->projekt()],
            ['id' => 'ga_disposition', 'version' => 2, 'contexts' => [DisplayWidgetRegistry::CONTEXT_GROSSANLASS], 'layout' => $this->disposition()],
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
     * Kopfzeile wie in den GA-Anzeigebeispielen: Logo, Titel, Untertitel und Uhr.
     *
     * @return array<string, mixed>
     */
    private function head(string $title, string $subtitle): array
    {
        return $this->i('i-head', 0, 0, 12, 1, $this->w('header', ['title' => $title, 'subtitle' => $subtitle]));
    }

    /** @return array<string, mixed> */
    private function blank(): array
    {
        return $this->doc(
            [$this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main'))],
            [$this->page('p-main', 'Hauptseite', [])],
        );
    }

    /**
     * Die vier Vorlagen bilden die GA-Anzeigebeispiele (views/grossanlass/displays) nach: gleiche Komponenten, gleiche
     * Filter und Rotationsdauern. Die Umschalter der Beispiele werden durch automatische Rotation ersetzt.
     *
     * @return array<string, mixed>
     */
    private function materiallager(): array
    {
        $rotation = $this->rotation(12, [
            [$this->w('ga_material_overview'), null],
            [$this->w('ga_pack_progress'), null],
            [$this->w('ga_missing_material'), null],
            [$this->w('ga_transport_overview', ['view' => 'material']), null],
            [$this->w('ga_goods_receipt'), null],
        ]);
        $subtitle = 'Zentrallager, Eingang · Material-Leitstand';

        return $this->doc(
            [
                $this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main')),
                $this->variant('v-compact', 'Kompakt', '16:9', $this->ref('p-compact')),
            ],
            [
                $this->page('p-main', 'Materiallager', [
                    $this->head('Materiallager', $subtitle),
                    $this->i('i-rot', 0, 1, 9, 11, $rotation),
                    $this->i('i-feed', 9, 1, 3, 11, $this->w('ga_activity_feed', ['area' => 'material'])),
                ]),
                $this->page('p-compact', 'Materiallager kompakt', [
                    $this->head('Materiallager', $subtitle),
                    $this->i('i-rot', 0, 1, 12, 11, $rotation),
                ]),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function baubuero(): array
    {
        $rotation = $this->rotation(15, [
            [$this->w('ga_overview'), null],
            [$this->w('ga_site_map'), null],
        ]);

        return $this->doc(
            [$this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main'))],
            [
                $this->page('p-main', 'Baubüro', [
                    $this->head('Baubüro', 'Baubüro · Gesamt-Leitstand'),
                    $this->i('i-rot', 0, 1, 9, 11, $rotation),
                    $this->i('i-feed', 9, 1, 3, 11, $this->w('ga_activity_feed')),
                ]),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function projekt(): array
    {
        return $this->doc(
            [$this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main'))],
            [
                $this->page('p-main', 'Projekt', [
                    $this->head('Projekt', 'Projektkarte · Ressort, Bereich und Bauauftrag im Widget wählen'),
                    $this->i('i-projects', 0, 1, 12, 11, $this->w('ga_projects', [
                        'rotate' => true,
                    ])),
                ]),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function disposition(): array
    {
        $rotation = $this->rotation(12, [
            [$this->w('ga_transport_overview', ['view' => 'overview']), null],
            [$this->w('ga_transport_overview', ['view' => 'transports']), null],
            [$this->w('ga_dispatch_tours', ['view' => 'tours']), null],
            [$this->w('ga_dispatch_tours', ['view' => 'resources']), null],
        ]);
        $subtitle = 'Disposition, Büro · Logistik-Leitstand';

        return $this->doc(
            [
                $this->variant('v-std', 'Standard 16:9', '16:9', $this->ref('p-main')),
                $this->variant('v-compact', 'Kompakt', '16:9', $this->ref('p-compact')),
            ],
            [
                $this->page('p-main', 'Disposition', [
                    $this->head('Disposition', $subtitle),
                    $this->i('i-rot', 0, 1, 9, 11, $rotation),
                    $this->i('i-feed', 9, 1, 3, 11, $this->w('ga_activity_feed', ['area' => 'logistics'])),
                ]),
                $this->page('p-compact', 'Disposition kompakt', [
                    $this->head('Disposition', $subtitle),
                    $this->i('i-rot', 0, 1, 12, 11, $rotation),
                ]),
            ],
        );
    }
}
