<?php

declare(strict_types=1);

namespace App\Service\Media;

/**
 * Kompression: Material kompakt, Werkstatt/Schaden detailreich, Rest dazwischen.
 */
final class MediaCompressionProfile
{
    public const CATALOG_MAX_EDGE = 1600;
    public const CATALOG_QUALITY = 80;
    public const WORKSHOP_MAX_EDGE = 2560;
    public const WORKSHOP_QUALITY = 88;
    public const DEFAULT_MAX_EDGE = 1920;
    public const DEFAULT_QUALITY = 85;

    public function __construct(
        public readonly int $maxEdgePx,
        public readonly int $quality,
    ) {
    }

    public static function catalog(): self
    {
        return new self(self::CATALOG_MAX_EDGE, self::CATALOG_QUALITY);
    }

    public static function workshop(): self
    {
        return new self(self::WORKSHOP_MAX_EDGE, self::WORKSHOP_QUALITY);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_MAX_EDGE, self::DEFAULT_QUALITY);
    }

    public static function forContext(string $context): self
    {
        return match ($context) {
            MediaStorageService::CONTEXT_MATERIAL_ITEM => self::catalog(),
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            MediaStorageService::CONTEXT_ISSUE_REPORT => self::workshop(),
            default => self::default(),
        };
    }
}
