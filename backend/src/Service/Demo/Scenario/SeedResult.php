<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

final class SeedResult
{
    public const OK = 'ok';
    public const NOT_IMPLEMENTED = 'not_implemented';
    public const SKIPPED = 'skipped';

    private function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly int $created = 0,
        public readonly int $existing = 0,
    ) {
    }

    public static function ok(string $message = '', int $created = 0, int $existing = 0): self
    {
        return new self(self::OK, $message, $created, $existing);
    }

    public static function notImplemented(string $message): self
    {
        return new self(self::NOT_IMPLEMENTED, $message);
    }

    public static function skipped(string $message): self
    {
        return new self(self::SKIPPED, $message);
    }
}
