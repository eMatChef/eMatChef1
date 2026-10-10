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
        /** @var list<string> Hinweise: Abweichungen, Konflikte, verwaiste Seed-Einträge */
        public readonly array $notes = [],
        /** @var array<string, mixed> maschinenlesbare Details (z. B. Reset-Plan) */
        public readonly array $data = [],
    ) {
    }

    /** @param list<string> $notes */
    public static function ok(string $message = '', int $created = 0, int $existing = 0, array $notes = []): self
    {
        return new self(self::OK, $message, $created, $existing, $notes);
    }

    /**
     * @param list<string>         $notes
     * @param array<string, mixed> $data
     */
    public static function okWithData(string $message, array $data, array $notes = [], int $created = 0, int $existing = 0): self
    {
        return new self(self::OK, $message, $created, $existing, $notes, $data);
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
