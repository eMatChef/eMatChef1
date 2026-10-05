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
        public ?string $id = null,
        public ?string $label = null,
        public ?string $roleType = null,
        public ?string $groupName = null,
        public ?string $roleName = null,
        public ?string $role = null,
        public ?string $roleClass = null,
    ) {}

    /**
     * @param array<string, mixed> $resource
     */
    public static function fromJsonApi(array $resource, ?string $expectedPersonId = null): self
    {
        $attributes = $resource['attributes'] ?? null;
        if (($resource['type'] ?? null) !== 'roles' || !is_array($attributes)) {
            throw new \UnexpectedValueException('Hitobito role response is malformed');
        }

        $personId = self::normalizeId($attributes['person_id'] ?? null)
            ?? self::normalizeRelationshipId($resource, 'person')
            ?? $expectedPersonId;
        $groupId = self::normalizeId($attributes['group_id'] ?? null)
            ?? self::normalizeRelationshipId($resource, 'group');
        $type = $attributes['role_class'] ?? $attributes['type'] ?? $attributes['role_type'] ?? null;
        $roleClass = $attributes['role_class'] ?? null;
        $id = self::normalizeId($resource['id'] ?? null);
        $label = $attributes['label'] ?? null;
        $roleType = $attributes['role_type'] ?? null;
        if (
            $personId === null
            || ($expectedPersonId !== null && $personId !== $expectedPersonId)
            || $groupId === null
            || !is_string($type)
            || $type === ''
            || ($label !== null && !is_string($label))
            || ($roleType !== null && !is_string($roleType))
        ) {
            throw new \UnexpectedValueException('Hitobito role is missing required attributes');
        }

        return new self(
            $personId,
            $groupId,
            $type,
            self::parseDate($attributes['start_on'] ?? null),
            self::parseDate($attributes['end_on'] ?? null),
            [],
            $id,
            $label,
            $roleType,
            null,
            null,
            null,
            is_string($roleClass) ? $roleClass : null,
        );
    }

    /**
     * @param array<string, mixed> $role
     */
    public static function fromUserInfo(array $role, string $personId): self
    {
        $groupId = self::normalizeId($role['group_id'] ?? null);
        $type = $role['role_class'] ?? $role['type'] ?? $role['role'] ?? null;
        $groupName = $role['group_name'] ?? null;
        $roleName = $role['role_name'] ?? null;
        $roleValue = $role['role'] ?? null;
        $roleClass = $role['role_class'] ?? null;
        $permissions = $role['permissions'] ?? [];
        if (
            $personId === ''
            || $groupId === null
            || !is_string($type)
            || $type === ''
            || !is_array($permissions)
            || array_filter($permissions, static fn (mixed $permission): bool => !is_string($permission)) !== []
            || ($groupName !== null && !is_string($groupName))
            || ($roleName !== null && !is_string($roleName))
            || ($roleValue !== null && !is_string($roleValue))
            || ($roleClass !== null && !is_string($roleClass))
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
            self::normalizeId($role['id'] ?? null),
            is_string($role['label'] ?? null) ? $role['label'] : null,
            is_string($role['role_type'] ?? null) ? $role['role_type'] : null,
            $groupName,
            $roleName,
            $roleValue,
            $roleClass,
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

    /**
     * @param array<string, mixed> $resource
     */
    private static function normalizeRelationshipId(array $resource, string $name): ?string
    {
        $relationships = $resource['relationships'] ?? null;
        if (!is_array($relationships)) {
            return null;
        }
        $relationship = $relationships[$name] ?? null;
        if (!is_array($relationship)) {
            return null;
        }
        $data = $relationship['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        return self::normalizeId($data['id'] ?? null);
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
