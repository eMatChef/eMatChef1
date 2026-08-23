<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassRound;

/**
 * System- und Custom-Feldtypen für Grossanlass-Wunschformulare.
 */
final class GrossanlassFormFieldCatalog
{
    public const ROLE_INPUT = 'input';
    public const ROLE_META = 'meta';

    /** Einziges System-Eingabefeld für Bauprojekt-Zuordnung (inkl. Neuanlage). */
    public const SYSTEM_BAUPROJEKT = 'bauprojekt';

    /** Ressort-Zuordnung aus System-Ressortbaum (Ressort, Unterressort, Bauprojekt). */
    public const SYSTEM_RESSORT_WAHL = 'ressort_wahl';

    /** @deprecated Legacy — nur noch in bestehenden Formularen; neue Fragen als custom_type anlegen */
    public const SYSTEM_WISH_KIND = 'wish_kind';
    /** @deprecated Legacy */
    public const SYSTEM_LABEL = 'label';
    /** @deprecated Legacy */
    public const SYSTEM_QUANTITY = 'quantity';
    /** @deprecated Legacy */
    public const SYSTEM_LOCATION = 'location';
    /** @deprecated Legacy */
    public const SYSTEM_PERIOD = 'period';
    /** @deprecated Legacy */
    public const SYSTEM_NOTES = 'notes';

    /** Metadaten (nur Anzeige in Liste/Detail, nicht im Formular). */
    public const SYSTEM_SUBMITTER = 'submitter';
    public const SYSTEM_RESSORT = 'ressort';
    public const SYSTEM_CREATED_AT = 'created_at';
    public const SYSTEM_UPDATED_AT = 'updated_at';

    public const CUSTOM_TEXT = 'text';
    public const CUSTOM_NUMBER = 'number';
    public const CUSTOM_SELECT = 'select';
    public const CUSTOM_DATE_RANGE = 'date_range';

    /** @var list<string> */
    public const INPUT_SYSTEM_KEYS = [
        self::SYSTEM_BAUPROJEKT,
        self::SYSTEM_RESSORT_WAHL,
    ];

    /** @var list<string> */
    public const LEGACY_INPUT_SYSTEM_KEYS = [
        self::SYSTEM_WISH_KIND,
        self::SYSTEM_LABEL,
        self::SYSTEM_QUANTITY,
        self::SYSTEM_LOCATION,
        self::SYSTEM_PERIOD,
        self::SYSTEM_NOTES,
    ];

    /** @var list<string> */
    public const SYSTEM_KEYS = [
        self::SYSTEM_BAUPROJEKT,
        self::SYSTEM_RESSORT_WAHL,
        self::SYSTEM_WISH_KIND,
        self::SYSTEM_LABEL,
        self::SYSTEM_QUANTITY,
        self::SYSTEM_LOCATION,
        self::SYSTEM_PERIOD,
        self::SYSTEM_NOTES,
        self::SYSTEM_SUBMITTER,
        self::SYSTEM_RESSORT,
        self::SYSTEM_CREATED_AT,
        self::SYSTEM_UPDATED_AT,
    ];

    /** @var list<string> */
    public const CUSTOM_TYPES = [
        self::CUSTOM_TEXT,
        self::CUSTOM_NUMBER,
        self::CUSTOM_SELECT,
        self::CUSTOM_DATE_RANGE,
    ];

    /** @var list<string> */
    public const META_SYSTEM_KEYS = [
        self::SYSTEM_SUBMITTER,
        self::SYSTEM_RESSORT,
        self::SYSTEM_CREATED_AT,
        self::SYSTEM_UPDATED_AT,
    ];

    /**
     * Standard-Formular: Ressort-Zuordnung + Metadaten. Bauprojekt optional per Builder.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultRessortWuenscheFields(): array
    {
        return array_merge(self::ressortAndMetaFields(), []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultFieldsForPurpose(string $purpose): array
    {
        return match ($purpose) {
            ActivityGrossanlassRound::PURPOSE_COMPANY_TIP => self::defaultCompanyTipFields(),
            ActivityGrossanlassRound::PURPOSE_FREE => self::defaultFreeFields(),
            default => self::defaultRessortWuenscheFields(),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultCompanyTipFields(): array
    {
        return array_merge(self::ressortAndMetaPrefix(), [
            ['role' => self::ROLE_INPUT, 'custom_type' => self::CUSTOM_TEXT, 'label' => 'Firma', 'required' => true, 'enabled' => true, 'sort_order' => 20],
            ['role' => self::ROLE_INPUT, 'custom_type' => self::CUSTOM_TEXT, 'label' => 'Kontakt / E-Mail', 'required' => false, 'enabled' => true, 'sort_order' => 30],
            ['role' => self::ROLE_INPUT, 'custom_type' => self::CUSTOM_TEXT, 'label' => 'Kategorie', 'required' => false, 'enabled' => true, 'sort_order' => 40],
            ['role' => self::ROLE_INPUT, 'custom_type' => self::CUSTOM_TEXT, 'label' => 'Ort', 'required' => false, 'enabled' => true, 'sort_order' => 50],
            ['role' => self::ROLE_INPUT, 'custom_type' => self::CUSTOM_TEXT, 'label' => 'URL', 'required' => false, 'enabled' => true, 'sort_order' => 60],
            ['role' => self::ROLE_INPUT, 'custom_type' => self::CUSTOM_TEXT, 'label' => 'Notiz', 'required' => false, 'enabled' => true, 'sort_order' => 70],
        ], self::metaFields());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultFreeFields(): array
    {
        return array_merge(self::ressortAndMetaPrefix(), [
            ['role' => self::ROLE_INPUT, 'custom_type' => self::CUSTOM_TEXT, 'label' => 'Titel', 'required' => true, 'enabled' => true, 'sort_order' => 20],
            ['role' => self::ROLE_INPUT, 'custom_type' => self::CUSTOM_TEXT, 'label' => 'Idee / Beschreibung', 'required' => true, 'enabled' => true, 'sort_order' => 30],
        ], self::metaFields());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function ressortAndMetaFields(): array
    {
        return array_merge(self::ressortAndMetaPrefix(), self::metaFields());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function ressortAndMetaPrefix(): array
    {
        return [
            ['role' => self::ROLE_INPUT, 'system_key' => self::SYSTEM_RESSORT_WAHL, 'label' => 'Ressort', 'required' => true, 'enabled' => true, 'sort_order' => 10, 'config' => ['leader_scope' => false]],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function metaFields(): array
    {
        return [
            ['role' => self::ROLE_META, 'system_key' => self::SYSTEM_SUBMITTER, 'label' => 'Eingereicht von', 'required' => false, 'enabled' => true, 'sort_order' => 100],
            ['role' => self::ROLE_META, 'system_key' => self::SYSTEM_CREATED_AT, 'label' => 'Erstellt', 'required' => false, 'enabled' => true, 'sort_order' => 110],
            ['role' => self::ROLE_META, 'system_key' => self::SYSTEM_UPDATED_AT, 'label' => 'Zuletzt bearbeitet', 'required' => false, 'enabled' => true, 'sort_order' => 120],
        ];
    }
}
