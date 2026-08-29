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
     * Gleiche Stammdaten wie «Firma erfassen» / CSV — fliessen in Anfragen-Platzhalter.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultCompanyTipFields(): array
    {
        return array_merge(self::ressortAndMetaPrefix(), self::companyTipInquiryFieldDefinitions(), self::metaFields());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function companyTipInquiryFieldDefinitions(): array
    {
        return [
            self::inquiryInput('name', self::CUSTOM_TEXT, 'Firma', 20, true),
            self::inquiryInput('place', self::CUSTOM_TEXT, 'Ort', 30),
            self::inquiryInput('website', self::CUSTOM_TEXT, 'Webseite', 40),
            self::inquiryInput('categories', self::CUSTOM_SELECT, 'Bereiche', 50, false, ['choices' => [], 'multiple' => true]),
            self::inquiryInput('offering', self::CUSTOM_TEXT, 'Was', 60, false, null, ['multiline' => true]),
            self::inquiryInput('notes', self::CUSTOM_TEXT, 'Hinweise', 70, false, null, ['multiline' => true]),
            self::inquiryInput('contact_salutation', self::CUSTOM_SELECT, 'Anrede', 80, false, ['choices' => ['Herr', 'Frau']]),
            self::inquiryInput('contact_first_name', self::CUSTOM_TEXT, 'Vorname', 90),
            self::inquiryInput('contact_last_name', self::CUSTOM_TEXT, 'Nachname', 100),
            self::inquiryInput('email', self::CUSTOM_TEXT, 'E-Mail', 110),
            self::inquiryInput('phone', self::CUSTOM_TEXT, 'Telefon', 120),
        ];
    }

    /**
     * @param list<array{label: string, config?: array<string, mixed>|null, text: string}> $answers
     * @return array{
     *     name: string,
     *     email: string,
     *     place: string,
     *     website: string,
     *     offering: string,
     *     notes: string,
     *     contact_salutation: string,
     *     contact_first_name: string,
     *     contact_last_name: string,
     *     phone: string,
     *     categories: list<string>
     * }
     */
    public static function extractInquiryFieldsFromAnswers(array $answers, string $fallbackName = '', string $fallbackPlace = ''): array
    {
        $out = [
            'name' => trim($fallbackName),
            'email' => '',
            'place' => trim($fallbackPlace),
            'website' => '',
            'offering' => '',
            'notes' => '',
            'contact_salutation' => '',
            'contact_first_name' => '',
            'contact_last_name' => '',
            'phone' => '',
            'categories' => [],
        ];
        foreach ($answers as $answer) {
            $text = trim((string) ($answer['text'] ?? ''));
            $json = $answer['json'] ?? null;
            $config = is_array($answer['config'] ?? null) ? $answer['config'] : null;
            $key = self::inquiryKeyFromField($config, (string) ($answer['label'] ?? ''));
            if ($key === null) {
                continue;
            }
            if ($key === 'categories') {
                $tokens = [];
                if (is_array($json) && array_is_list($json)) {
                    $tokens = $json;
                } elseif ($text !== '') {
                    $tokens = preg_split('/[,;]+/', $text) ?: [];
                }
                foreach ($tokens as $part) {
                    $part = self::normalizeCategoryToken((string) $part);
                    if ($part !== '') {
                        $out['categories'][] = $part;
                    }
                }
                continue;
            }
            if ($text === '') {
                continue;
            }
            if ($key === 'email') {
                if (filter_var($text, FILTER_VALIDATE_EMAIL) || str_contains($text, '@')) {
                    $out['email'] = strtolower($text);
                }
                continue;
            }
            if (!array_key_exists($key, $out) || $key === 'categories') {
                continue;
            }
            if ($key === 'name' || $out[$key] === '') {
                $out[$key] = $text;
            }
        }

        return $out;
    }

    public static function normalizeCategoryToken(string $raw): string
    {
        $part = trim($raw);
        $part = preg_replace('/^↳\s*/u', '', $part) ?? $part;

        return trim($part);
    }

    /**
     * @param array<string, mixed>|null $options
     * @param array<string, mixed> $extraConfig
     * @return array<string, mixed>
     */
    private static function inquiryInput(
        string $inquiryKey,
        string $customType,
        string $label,
        int $sortOrder,
        bool $required = false,
        ?array $options = null,
        array $extraConfig = [],
    ): array {
        return [
            'role' => self::ROLE_INPUT,
            'custom_type' => $customType,
            'label' => $label,
            'required' => $required,
            'enabled' => true,
            'sort_order' => $sortOrder,
            'options' => $options,
            'config' => array_merge(['inquiry_key' => $inquiryKey], $extraConfig),
        ];
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public static function inquiryKeyFromField(?array $config, string $label): ?string
    {
        $fromConfig = is_array($config) ? ($config['inquiry_key'] ?? null) : null;
        if (is_string($fromConfig) && trim($fromConfig) !== '') {
            return trim($fromConfig);
        }

        return self::inquiryKeyFromLabel($label);
    }

    public static function inquiryKeyFromLabel(string $label): ?string
    {
        $folded = mb_strtolower(trim($label), 'UTF-8');
        $folded = str_replace(['ä', 'ö', 'ü', 'ß'], ['a', 'o', 'u', 'ss'], $folded);
        $norm = preg_replace('/[^a-z0-9]+/', '', $folded) ?? '';
        if ($norm === '') {
            return null;
        }
        if (str_contains($norm, 'anrede')) {
            return 'contact_salutation';
        }
        if (str_contains($norm, 'vorname')) {
            return 'contact_first_name';
        }
        if (str_contains($norm, 'nachname')) {
            return 'contact_last_name';
        }
        if (str_contains($norm, 'telefon') || $norm === 'phone' || $norm === 'tel' || $norm === 'handy') {
            return 'phone';
        }
        if (str_contains($norm, 'email') || $norm === 'mail') {
            return 'email';
        }
        if (str_contains($norm, 'webseite') || str_contains($norm, 'website') || $norm === 'url' || $norm === 'www' || $norm === 'homepage') {
            return 'website';
        }
        if ($norm === 'was' || str_contains($norm, 'angebot')) {
            return 'offering';
        }
        if (str_contains($norm, 'hinweis') || $norm === 'notiz' || $norm === 'notes' || $norm === 'bemerkung' || $norm === 'bemerkungen') {
            return 'notes';
        }
        if (in_array($norm, ['firma', 'name', 'company', 'unternehmen'], true)) {
            return 'name';
        }
        if (str_contains($norm, 'ort') || $norm === 'place' || $norm === 'stadt' || $norm === 'city') {
            return 'place';
        }
        if (str_contains($norm, 'bereich') || str_contains($norm, 'kategorie') || $norm === 'paket' || $norm === 'pakete') {
            return 'categories';
        }
        if (str_contains($norm, 'kontakt')) {
            return 'email';
        }

        return null;
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
            ['role' => self::ROLE_META, 'system_key' => self::SYSTEM_SUBMITTER, 'label' => 'Eingereicht von', 'required' => false, 'enabled' => true, 'sort_order' => 200],
            ['role' => self::ROLE_META, 'system_key' => self::SYSTEM_CREATED_AT, 'label' => 'Erstellt', 'required' => false, 'enabled' => true, 'sort_order' => 210],
            ['role' => self::ROLE_META, 'system_key' => self::SYSTEM_UPDATED_AT, 'label' => 'Zuletzt bearbeitet', 'required' => false, 'enabled' => true, 'sort_order' => 220],
        ];
    }
}
