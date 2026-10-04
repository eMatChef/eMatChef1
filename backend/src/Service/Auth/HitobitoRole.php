<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class HitobitoRole
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public string $personId,
        public string $groupId,
        public string $type,
        public ?\DateTimeImmutable $startOn,
        public ?\DateTimeImmutable $endOn,
        public array $permissions = [],
    ) {}

    /**
     * @param array<string, mixed> $resource
     */
    public static function fromJsonApi(array $resource): self
    {
        $attributes = $resource['attributes'] ?? null;
        if (($resource['type'] ?? null) !== 'roles' || !is_array($attributes)) {
            throw new \UnexpectedValueException('Hitobito role response is malformed');
        }

        $personId = self::normalizeId($attributes['person_id'] ?? null);
        $groupId = self::normalizeId($attributes['group_id'] ?? null);
        $type = $attributes['type'] ?? null;
        if ($personId === null || $groupId === null || !is_string($type) || $type === '') {
            throw new \UnexpectedValueException('Hitobito role is missing required attributes');
        }

        return new self(
            $personId,
            $groupId,
            $type,
            self::parseDate($attributes['start_on'] ?? null),
            self::parseDate($attributes['end_on'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $role
     */
    public static function fromUserInfo(array $role, string $personId): self
    {
        $groupId = self::normalizeId($role['group_id'] ?? null);
        $type = $role['role_class'] ?? $role['type'] ?? $role['role'] ?? null;
        $permissions = $role['permissions'] ?? [];
        if (
            $personId === ''
            || $groupId === null
            || !is_string($type)
            || $type === ''
            || !is_array($permissions)
            || array_filter($permissions, static fn (mixed $permission): bool => !is_string($permission)) !== []
        ) {
            throw new \UnexpectedValueException('Hitobito userinfo role is malformed');
        }

        return new self(
            $personId,
            $groupId,
            $type,
            self::parseDate($role['start_on'] ?? null),
            self::parseDate($role['end_on'] ?? null),
            array_values($permissions),
        );
    }

    public function isActiveOn(\DateTimeImmutable $date): bool
    {
        $day = $date->format('Y-m-d');

        return ($this->startOn === null || $this->startOn->format('Y-m-d') <= $day)
            && ($this->endOn === null || $this->endOn->format('Y-m-d') >= $day);
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

    private static function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new \UnexpectedValueException('Hitobito role date is malformed');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new \UnexpectedValueException('Hitobito role date is invalid');
        }

        return $date;
    }
}
