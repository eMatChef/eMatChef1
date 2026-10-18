<?php

namespace App\Service\Display\Layout;

/**
 * Offizielle Bausteine des Infoscreen-Studios (Widget-Gruppen und Layoutbereiche). Sie liegen wie die Vorlagen im Code
 * und werden getrennt von den eigenen Bausteinen eines Grossanlasses verwaltet. Beim Einfügen entsteht immer eine
 * unabhängige Kopie; der Katalog enthält nur Gruppen, die das Layoutschema ohne Sonderfälle abbildet.
 */
final class DisplayBlockCatalog
{
    public const KIND_GROUP = 'group';
    public const KIND_REGION = 'region';

    /**
     * @return list<array{id: string, kind: string, category: string, contexts: list<string>, default_size: array{int, int}, content: array<string, mixed>}>
     */
    public function forContext(string $context): array
    {
        return array_values(array_filter($this->all(), static fn (array $b) => \in_array($context, $b['contexts'], true)));
    }

    /**
     * @return list<array{id: string, kind: string, category: string, contexts: list<string>, default_size: array{int, int}, content: array<string, mixed>}>
     */
    public function all(): array
    {
        $ga = [DisplayWidgetRegistry::CONTEXT_GROSSANLASS];

        return [
            $this->block('group_material_status', self::KIND_GROUP, 'material', $ga, [8, 6], 12, 6, [
                $this->i('i-over', 0, 0, 6, 6, $this->w('ga_material_overview')),
                $this->i('i-pack', 6, 0, 6, 6, $this->w('ga_pack_progress')),
            ]),
            $this->block('group_material_gaps', self::KIND_GROUP, 'material', $ga, [8, 6], 12, 6, [
                $this->i('i-miss', 0, 0, 6, 6, $this->w('ga_missing_material')),
                $this->i('i-recv', 6, 0, 6, 6, $this->w('ga_goods_receipt')),
            ]),
            $this->block('group_logistics_status', self::KIND_GROUP, 'logistics', $ga, [8, 6], 12, 6, [
                $this->i('i-trans', 0, 0, 6, 6, $this->w('ga_transport_overview', ['view' => 'overview'])),
                $this->i('i-tours', 6, 0, 6, 6, $this->w('ga_dispatch_tours', ['view' => 'tours'])),
            ]),
            $this->block('group_project_site', self::KIND_GROUP, 'projects', $ga, [10, 6], 12, 6, [
                $this->i('i-proj', 0, 0, 7, 6, $this->w('ga_projects', ['rotate' => true])),
                $this->i('i-map', 7, 0, 5, 6, $this->w('ga_site_map')),
            ]),
            $this->block('group_activity_alerts', self::KIND_GROUP, 'activity', $ga, [6, 6], 12, 6, [
                $this->i('i-feed', 0, 0, 7, 6, $this->w('ga_activity_feed')),
                $this->i('i-warn', 7, 0, 5, 6, $this->w('ga_warnings')),
            ]),
            $this->block('region_materiallager', self::KIND_REGION, 'regions', $ga, [6, 11], 12, 11, [
                $this->i('i-head', 0, 0, 12, 1, $this->w('header', ['title' => 'Materiallager', 'subtitle' => '', 'show_clock' => false])),
                $this->i('i-over', 0, 1, 7, 5, $this->w('ga_material_overview')),
                $this->i('i-pack', 7, 1, 5, 5, $this->w('ga_pack_progress')),
                $this->i('i-miss', 0, 6, 7, 5, $this->w('ga_missing_material')),
                $this->i('i-recv', 7, 6, 5, 5, $this->w('ga_goods_receipt')),
            ]),
            $this->block('region_disposition', self::KIND_REGION, 'regions', $ga, [6, 11], 12, 11, [
                $this->i('i-head', 0, 0, 12, 1, $this->w('header', ['title' => 'Disposition', 'subtitle' => '', 'show_clock' => false])),
                $this->i('i-trans', 0, 1, 6, 10, $this->w('ga_transport_overview', ['view' => 'transports'])),
                $this->i('i-tours', 6, 1, 6, 10, $this->w('ga_dispatch_tours', ['view' => 'tours'])),
            ]),
            $this->block('region_baubuero', self::KIND_REGION, 'regions', $ga, [6, 11], 12, 11, [
                $this->i('i-head', 0, 0, 12, 1, $this->w('header', ['title' => 'Baubüro', 'subtitle' => '', 'show_clock' => false])),
                $this->i('i-over', 0, 1, 8, 10, $this->w('ga_overview')),
                $this->i('i-tasks', 8, 1, 4, 10, $this->w('ga_tasks')),
            ]),
            $this->block('region_holzbau', self::KIND_REGION, 'regions', $ga, [6, 11], 12, 11, [
                $this->i('i-head', 0, 0, 12, 1, $this->w('header', ['title' => 'Holzbau', 'subtitle' => '', 'show_clock' => false])),
                $this->i('i-proj', 0, 1, 8, 10, $this->w('ga_projects', ['rotate' => true])),
                $this->i('i-map', 8, 1, 4, 5, $this->w('ga_site_map')),
                $this->i('i-warn', 8, 6, 4, 5, $this->w('ga_warnings')),
            ]),
            $this->block('region_activity', self::KIND_REGION, 'regions', $ga, [6, 4], 12, 4, [
                $this->i('i-feed', 0, 0, 8, 4, $this->w('ga_activity_feed')),
                $this->i('i-warn', 8, 0, 4, 4, $this->w('ga_warnings')),
            ]),
        ];
    }

    /**
     * @param list<string> $contexts
     * @param array{int, int} $size
     * @param list<array<string, mixed>> $items
     *
     * @return array{id: string, kind: string, category: string, contexts: list<string>, default_size: array{int, int}, content: array<string, mixed>}
     */
    private function block(string $id, string $kind, string $category, array $contexts, array $size, int $columns, int $rows, array $items): array
    {
        $content = ['type' => 'group', 'rows' => $rows];
        if ($columns !== DisplayLayoutValidator::COLUMNS) {
            $content['columns'] = $columns;
        }
        $content['items'] = $items;

        return ['id' => $id, 'kind' => $kind, 'category' => $category, 'contexts' => $contexts, 'default_size' => $size, 'content' => $content];
    }

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
}
