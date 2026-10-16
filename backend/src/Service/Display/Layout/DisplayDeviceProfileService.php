<?php

namespace App\Service\Display\Layout;

/**
 * Bildschirmprofil eines Anzeigegeräts: Format, native Pixelauflösung (nie aus der physischen Grösse abgeleitet),
 * Betrachtungsabstand, Lesbarkeitsprofil und gewählte Layoutvariante. Dient der Variantenwahl und der Schriftskalierung.
 */
final class DisplayDeviceProfileService
{
    public const FONT_PROFILES = ['auto', 'small', 'normal', 'large', 'xlarge'];

    private const FONT_SCALES = ['small' => 0.85, 'normal' => 1.0, 'large' => 1.25, 'xlarge' => 1.5];
    /** Relative Abweichung (logarithmisch), bis zu der eine Variante automatisch als passend gilt. */
    private const ASPECT_TOLERANCE = 0.12;

    /**
     * @param mixed $raw
     *
     * @return array<string, mixed>|null null = Profil entfernen
     */
    public function normalize(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        if (!\is_array($raw) || ($raw !== [] && array_is_list($raw))) {
            throw new \InvalidArgumentException('Profil muss ein Objekt sein.');
        }
        $allowed = ['width_cm', 'height_cm', 'pixel_width', 'pixel_height', 'aspect_ratio', 'viewing_distance_m', 'font_profile', 'variant_id'];
        foreach (array_keys($raw) as $key) {
            if (!\in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException("Unbekanntes Profilfeld «{$key}».");
            }
        }

        $profile = [
            'width_cm' => $this->number($raw['width_cm'] ?? null, 5, 5000, 'width_cm'),
            'height_cm' => $this->number($raw['height_cm'] ?? null, 5, 5000, 'height_cm'),
            'pixel_width' => $this->integer($raw['pixel_width'] ?? null, 320, 16384, 'pixel_width'),
            'pixel_height' => $this->integer($raw['pixel_height'] ?? null, 240, 16384, 'pixel_height'),
            'aspect_ratio' => null,
            'viewing_distance_m' => $this->number($raw['viewing_distance_m'] ?? null, 0.3, 100, 'viewing_distance_m'),
            'font_profile' => 'auto',
            'variant_id' => null,
        ];

        $aspect = $raw['aspect_ratio'] ?? null;
        if ($aspect !== null && $aspect !== '') {
            if (!\is_string($aspect) || !preg_match('/^([1-9]\d?):([1-9]\d?)$/', $aspect)) {
                throw new \InvalidArgumentException('Seitenverhältnis wie «16:9» erwartet.');
            }
            $profile['aspect_ratio'] = $aspect;
        }

        $font = $raw['font_profile'] ?? 'auto';
        if (!\is_string($font) || !\in_array($font, self::FONT_PROFILES, true)) {
            throw new \InvalidArgumentException('Ungültiges Lesbarkeitsprofil.');
        }
        $profile['font_profile'] = $font;

        $variant = $raw['variant_id'] ?? null;
        if ($variant !== null && $variant !== '') {
            if (!\is_string($variant) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $variant)) {
                throw new \InvalidArgumentException('Ungültige Layoutvariante.');
            }
            $profile['variant_id'] = $variant;
        }

        return $profile;
    }

    /**
     * Seitenverhältnis des Geräts als Zahl (Breite/Höhe): explizit, sonst aus der Pixelauflösung, sonst aus der physischen Grösse.
     *
     * @param array<string, mixed>|null $profile
     */
    public function aspectOf(?array $profile): ?float
    {
        if ($profile === null) {
            return null;
        }
        if (!empty($profile['aspect_ratio'])) {
            return self::ratio((string) $profile['aspect_ratio']);
        }
        if (!empty($profile['pixel_width']) && !empty($profile['pixel_height'])) {
            return $profile['pixel_width'] / $profile['pixel_height'];
        }
        if (!empty($profile['width_cm']) && !empty($profile['height_cm'])) {
            return $profile['width_cm'] / $profile['height_cm'];
        }

        return null;
    }

    public static function ratio(string $aspect): float
    {
        [$w, $h] = array_map('intval', explode(':', $aspect));

        return $h > 0 ? $w / $h : 16 / 9;
    }

    /**
     * Variante für das Gerät: ausdrücklich gewählte (falls in der Revision vorhanden), sonst die zum Seitenverhältnis
     * passendste innerhalb der Toleranz, sonst die Standardvariante, sonst die erste. Liefert immer eine gültige ID.
     *
     * @param array<string, mixed> $layout
     * @param array<string, mixed>|null $profile
     */
    public function selectVariant(array $layout, ?array $profile): string
    {
        $variants = $layout['variants'] ?? [];
        $ids = array_column($variants, 'id');
        $explicit = $profile['variant_id'] ?? null;
        if (\is_string($explicit) && \in_array($explicit, $ids, true)) {
            return $explicit;
        }
        $default = \in_array($layout['default_variant_id'] ?? null, $ids, true) ? $layout['default_variant_id'] : ($ids[0] ?? '');

        $aspect = $this->aspectOf($profile);
        if ($aspect === null) {
            return $default;
        }
        $best = $default;
        $bestDelta = INF;
        // Standardvariante zuerst: bei Gleichstand gewinnt sie.
        $ordered = array_merge(
            array_filter($variants, static fn (array $v) => $v['id'] === $default),
            array_filter($variants, static fn (array $v) => $v['id'] !== $default),
        );
        foreach ($ordered as $variant) {
            $delta = abs(log($aspect / self::ratio($variant['aspect_ratio'])));
            if ($delta < $bestDelta) {
                $bestDelta = $delta;
                $best = $variant['id'];
            }
        }

        return $bestDelta <= self::ASPECT_TOLERANCE ? $best : $default;
    }

    /**
     * @param array<string, mixed>|null $profile
     */
    public function fontScale(?array $profile): float
    {
        $font = $profile['font_profile'] ?? 'auto';
        if (isset(self::FONT_SCALES[$font])) {
            return self::FONT_SCALES[$font];
        }
        $distance = $profile['viewing_distance_m'] ?? null;
        if (!\is_numeric($distance)) {
            return 1.0;
        }

        return match (true) {
            $distance <= 2 => 1.0,
            $distance <= 5 => 1.15,
            $distance <= 10 => 1.35,
            default => 1.6,
        };
    }

    private function number(mixed $value, float $min, float $max, string $name): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!\is_int($value) && !\is_float($value) && !(\is_string($value) && is_numeric($value))) {
            throw new \InvalidArgumentException("$name muss eine Zahl sein.");
        }
        $value = (float) $value;
        if ($value < $min || $value > $max) {
            throw new \InvalidArgumentException("$name liegt ausserhalb des erlaubten Bereichs.");
        }

        return round($value, 2);
    }

    private function integer(mixed $value, int $min, int $max, string $name): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!\is_int($value) && !(\is_string($value) && preg_match('/^\d+$/', $value))) {
            throw new \InvalidArgumentException("$name muss eine Ganzzahl sein.");
        }
        $value = (int) $value;
        if ($value < $min || $value > $max) {
            throw new \InvalidArgumentException("$name liegt ausserhalb des erlaubten Bereichs.");
        }

        return $value;
    }
}
