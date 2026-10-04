<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class HitobitoGroup
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public string $type,
        public string $name,
    ) {}

    /**
     * @param array<string, mixed> $resource
     */
    public static function fromJsonApi(array $resource): self
    {
        $attributes = $resource['attributes'] ?? null;
        if (!is_array($attributes)) {
            throw new \UnexpectedValueException('Hitobito group attributes are malformed');
        }
        $id = self::normalizeId($resource['id'] ?? null);
        $parentId = self::normalizeId($attributes['parent_id'] ?? null);
        $type = $attributes['type'] ?? null;
        $name = $attributes['name'] ?? null;

        if (
            ($resource['type'] ?? null) !== 'groups'
            || $id === null
            || !array_key_exists('parent_id', $attributes)
            || !is_string($type)
            || $type === ''
            || !is_string($name)
        ) {
            throw new \UnexpectedValueException('Hitobito group response is malformed');
        }

        if (array_key_exists('parent_id', $attributes) && $attributes['parent_id'] !== null && $parentId === null) {
            throw new \UnexpectedValueException('Hitobito group parent id is malformed');
        }

        return new self($id, $parentId, $type, $name);
    }

    private static function normalizeId(mixed $value): ?string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
