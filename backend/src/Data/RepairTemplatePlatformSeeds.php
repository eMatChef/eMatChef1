<?php

namespace App\Data;

/**
 * Plattform-Stamm für Zeltblatt-Templates (Paket 22).
 *
 * Struktur orientiert am OMC-Zeltblatt: Aussenzelt, Innenzelt, Vordach, optional Apsis, Sonderposten.
 * Preise werden vom Department nach Import gesetzt.
 */
final class RepairTemplatePlatformSeeds
{
    private const SECTION_ITEMS = [
        'oese' => 'Öse / Ring',
        'reissverschluss' => 'Reissverschluss',
        'naht' => 'Naht',
        'planenflick' => 'Planenflicken',
        'heringlasche' => 'Heringlasche',
        'abspann' => 'Abspannöse / Schnur',
        'klett' => 'Klettband',
    ];

    private const SONDERPOSTEN_ITEMS = [
        'gestaenge' => 'Gestänge / Gummiring',
        'bodenplane' => 'Bodenplane',
        'verstaerkung' => 'Verstärkungsband',
        'sonstiges' => 'Sonstiges (Aufwand)',
    ];

    /**
     * @return list<array{
     *     id: string,
     *     template_key: string,
     *     name: string,
     *     structure_json: array<string, mixed>,
     *     diagram_json: array<string, mixed>
     * }>
     */
    public static function all(): array
    {
        return [
            self::spatz(),
            self::phoenix(),
            self::hajk(),
            self::wico(),
        ];
    }

    /**
     * @return array{id: string, template_key: string, name: string, structure_json: array<string, mixed>, diagram_json: array<string, mixed>}
     */
    private static function spatz(): array
    {
        return [
            'id' => 'f4e5d6c7b801',
            'template_key' => 'spatz',
            'name' => 'Spatz',
            'structure_json' => self::structure(
                sections: [
                    'aussenzelt' => 'Aussenzelt',
                    'innenzelt' => 'Innenzelt',
                    'vordach' => 'Vordach',
                    'apsis' => 'Apsis',
                    'sonderposten' => 'Sonderposten',
                ],
                sonderpostenExtra: []
            ),
            'diagram_json' => self::diagram(includeApsis: true),
        ];
    }

    /**
     * @return array{id: string, template_key: string, name: string, structure_json: array<string, mixed>, diagram_json: array<string, mixed>}
     */
    private static function phoenix(): array
    {
        return [
            'id' => 'f4e5d6c7b802',
            'template_key' => 'phoenix',
            'name' => 'Phönix (Zelthangar)',
            'structure_json' => self::structure(
                sections: [
                    'aussenzelt' => 'Aussenzelt',
                    'innenzelt' => 'Innenzelt',
                    'vordach' => 'Vordach',
                    'sonderposten' => 'Sonderposten',
                ],
                sonderpostenExtra: [
                    'hochstelleinheit' => 'Hochstelleinheit',
                    'winkel' => 'Winkel-Element',
                ]
            ),
            'diagram_json' => self::diagram(includeApsis: false),
        ];
    }

    /**
     * @return array{id: string, template_key: string, name: string, structure_json: array<string, mixed>, diagram_json: array<string, mixed>}
     */
    private static function hajk(): array
    {
        return [
            'id' => 'f4e5d6c7b803',
            'template_key' => 'hajk',
            'name' => 'hajk',
            'structure_json' => self::structure(
                sections: [
                    'aussenzelt' => 'Aussenzelt',
                    'innenzelt' => 'Innenzelt',
                    'vordach' => 'Vordach',
                    'sonderposten' => 'Sonderposten',
                ],
                sonderpostenExtra: []
            ),
            'diagram_json' => self::diagram(includeApsis: false),
        ];
    }

    /**
     * @return array{id: string, template_key: string, name: string, structure_json: array<string, mixed>, diagram_json: array<string, mixed>}
     */
    private static function wico(): array
    {
        return [
            'id' => 'f4e5d6c7b804',
            'template_key' => 'wico',
            'name' => 'Wico',
            'structure_json' => self::structure(
                sections: [
                    'aussenzelt' => 'Aussenzelt',
                    'innenzelt' => 'Innenzelt',
                    'vordach' => 'Vordach',
                    'sonderposten' => 'Sonderposten',
                ],
                sonderpostenExtra: []
            ),
            'diagram_json' => self::diagram(includeApsis: false),
        ];
    }

    /**
     * @param array<string, string> $sections
     * @param array<string, string> $sonderpostenExtra
     *
     * @return array<string, mixed>
     */
    private static function structure(array $sections, array $sonderpostenExtra): array
    {
        $result = [];

        foreach ($sections as $sectionKey => $sectionLabel) {
            if ($sectionKey === 'sonderposten') {
                $items = [];
                foreach (self::SONDERPOSTEN_ITEMS as $itemKey => $itemLabel) {
                    $items[] = [
                        'key' => $sectionKey . '_' . $itemKey,
                        'label' => $itemLabel,
                    ];
                }
                foreach ($sonderpostenExtra as $itemKey => $itemLabel) {
                    $items[] = [
                        'key' => $sectionKey . '_' . $itemKey,
                        'label' => $itemLabel,
                    ];
                }
                $result[] = [
                    'key' => $sectionKey,
                    'label' => $sectionLabel,
                    'items' => $items,
                ];
                continue;
            }

            $items = [];
            foreach (self::SECTION_ITEMS as $itemKey => $itemLabel) {
                $items[] = [
                    'key' => $sectionKey . '_' . $itemKey,
                    'label' => $itemLabel,
                    'diagram_marker' => self::diagramMarkerForSection($sectionKey),
                ];
            }
            $result[] = [
                'key' => $sectionKey,
                'label' => $sectionLabel,
                'items' => $items,
            ];
        }

        return [
            'sections' => $result,
            'whole_unit_option' => true,
        ];
    }

    private static function diagramMarkerForSection(string $sectionKey): string
    {
        return match ($sectionKey) {
            'aussenzelt' => 'ridge_green',
            'innenzelt' => 'inner_orange',
            'vordach' => 'awning_purple',
            'apsis' => 'apse_teal',
            default => 'special_slate',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function diagram(bool $includeApsis): array
    {
        $markers = [
            [
                'id' => 'marker_aussenzelt',
                'section_key' => 'aussenzelt',
                'x' => 200,
                'y' => 105,
                'label' => 'AZ',
                'color' => 'green',
            ],
            [
                'id' => 'marker_innenzelt',
                'section_key' => 'innenzelt',
                'x' => 200,
                'y' => 175,
                'label' => 'IZ',
                'color' => 'orange',
            ],
            [
                'id' => 'marker_vordach',
                'section_key' => 'vordach',
                'x' => 200,
                'y' => 228,
                'label' => 'VD',
                'color' => 'purple',
            ],
            [
                'id' => 'marker_sonderposten',
                'section_key' => 'sonderposten',
                'x' => 72,
                'y' => 198,
                'label' => 'SP',
                'color' => 'slate',
            ],
        ];

        if ($includeApsis) {
            array_splice($markers, 3, 0, [[
                'id' => 'marker_apsis',
                'section_key' => 'apsis',
                'x' => 200,
                'y' => 52,
                'label' => 'AP',
                'color' => 'teal',
            ]]);
        }

        return [
            'viewBox' => '0 0 400 260',
            'markers' => $markers,
        ];
    }
}
