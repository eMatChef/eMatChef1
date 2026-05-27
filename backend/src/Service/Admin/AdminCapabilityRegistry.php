<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Service\SystemScopeVisibility;

/**
 * Globale Admin-Rechte für Organisations-/Suborgchef (profile.admin_capabilities).
 * Superadmin bypassed alle Checks in AdminCapabilityChecker.
 */
final class AdminCapabilityRegistry
{
    public const GLOBAL_ROLE_NONE = 'none';
    public const GLOBAL_ROLE_ORG = 'org';
    public const GLOBAL_ROLE_SUB = 'sub';
    public const GLOBAL_ROLE_SUPERADMIN = 'superadmin';

    /** @return list<string> */
    public static function allDotKeys(): array
    {
        return [
            'organisations.view',
            'organisations.create',
            'organisations.edit',
            'departments.view',
            'departments.create',
            'departments.edit',
            'support_requests.assign',
            'users.global_manage',
            'security_monitoring.view',
            'mail.settings',
            'integrations.manage',
            'system_jobs.view',
            'global_addresses.manage',
        ];
    }

    public static function resolveGlobalRole(array $profileRoles): string
    {
        if (\in_array('ROLE_SUPERADMIN', $profileRoles, true)) {
            return self::GLOBAL_ROLE_SUPERADMIN;
        }
        if (\in_array('ROLE_ORGANISATIONSCHEF', $profileRoles, true)) {
            return self::GLOBAL_ROLE_ORG;
        }
        if (\in_array('ROLE_SUBORGCHEF', $profileRoles, true)) {
            return self::GLOBAL_ROLE_SUB;
        }

        return self::GLOBAL_ROLE_NONE;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultCapabilities(string $globalRole): array
    {
        $none = self::emptyCapabilities();

        return match ($globalRole) {
            self::GLOBAL_ROLE_ORG => self::mergeCapabilities($none, [
                'organisations' => ['view' => true, 'create' => true, 'edit' => true],
                'departments' => ['view' => true, 'create' => true, 'edit' => true],
                'support_requests' => ['assign' => true],
                'security_monitoring' => ['view' => true],
            ]),
            self::GLOBAL_ROLE_SUB => self::mergeCapabilities($none, [
                'organisations' => ['view' => true, 'create' => false, 'edit' => false],
                'departments' => ['view' => true, 'create' => true, 'edit' => true],
                'support_requests' => ['assign' => true],
                'security_monitoring' => ['view' => true],
            ]),
            default => $none,
        };
    }

    /**
     * @param array<string, mixed>|null $stored
     *
     * @return array<string, mixed>
     */
    public static function normalizeStored(?array $stored, string $globalRole): array
    {
        $defaults = self::defaultCapabilities($globalRole);
        if ($stored === null || $stored === []) {
            return $defaults;
        }

        return self::mergeCapabilities($defaults, $stored);
    }

    /**
     * @param array<string, mixed> $capabilities
     */
    public static function can(array $capabilities, string $dotKey): bool
    {
        $parts = explode('.', $dotKey);
        $node = $capabilities;
        foreach ($parts as $part) {
            if (!\is_array($node) || !\array_key_exists($part, $node)) {
                return false;
            }
            $node = $node[$part];
        }

        return (bool) $node;
    }

    /**
     * @param array<string, mixed> $capabilities
     *
     * @return list<string>
     */
    public static function scopedOrganisationIds(array $capabilities): array
    {
        $scope = $capabilities['scope'] ?? [];
        if (!\is_array($scope)) {
            return [];
        }
        $ids = $scope['organisation_ids'] ?? [];
        if (!\is_array($ids)) {
            return [];
        }

        return SystemScopeVisibility::filterOrganisationIds(
            array_values(array_unique(array_filter(array_map('strval', $ids))))
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function sanitizePayload(array $payload, string $globalRole): array
    {
        if ($globalRole === self::GLOBAL_ROLE_NONE) {
            return self::emptyCapabilities();
        }

        $defaults = self::defaultCapabilities($globalRole);
        $merged = self::mergeCapabilities($defaults, $payload);

        foreach (self::allDotKeys() as $dotKey) {
            if (!self::can($merged, $dotKey)) {
                continue;
            }
            // Superadmin-only capabilities cannot be granted to org/sub via UI.
            if ($globalRole !== self::GLOBAL_ROLE_SUPERADMIN && \in_array($dotKey, [
                'mail.settings',
                'integrations.manage',
                'system_jobs.view',
            ], true)) {
                self::setDot($merged, $dotKey, false);
            }
        }

        $merged['scope'] = [
            'organisation_ids' => self::scopedOrganisationIds($merged),
            'department_root_ids' => self::scopedDepartmentRootIds($merged),
        ];

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyCapabilities(): array
    {
        return [
            'organisations' => ['view' => false, 'create' => false, 'edit' => false],
            'departments' => ['view' => false, 'create' => false, 'edit' => false],
            'support_requests' => ['assign' => false],
            'users' => ['global_manage' => false],
            'security_monitoring' => ['view' => false],
            'mail' => ['settings' => false],
            'integrations' => ['manage' => false],
            'system_jobs' => ['view' => false],
            'global_addresses' => ['manage' => false],
            'scope' => ['organisation_ids' => [], 'department_root_ids' => []],
        ];
    }

    /**
     * @param array<string, mixed> $capabilities
     *
     * @return list<string>
     */
    public static function scopedDepartmentRootIds(array $capabilities): array
    {
        $scope = $capabilities['scope'] ?? [];
        if (!\is_array($scope)) {
            return [];
        }
        $ids = $scope['department_root_ids'] ?? [];
        if (!\is_array($ids)) {
            return [];
        }

        return SystemScopeVisibility::filterDepartmentIds(
            array_values(array_unique(array_filter(array_map('strval', $ids))))
        );
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function mergeCapabilities(array $base, array $overrides): array
    {
        $result = $base;
        foreach ($overrides as $key => $value) {
            if ($key === 'scope' && \is_array($value)) {
                $result['scope'] = [
                    'organisation_ids' => \is_array($value['organisation_ids'] ?? null)
                        ? array_values(array_unique(array_filter(array_map('strval', $value['organisation_ids']))))
                        : [],
                    'department_root_ids' => \is_array($value['department_root_ids'] ?? null)
                        ? array_values(array_unique(array_filter(array_map('strval', $value['department_root_ids']))))
                        : [],
                ];
                continue;
            }
            if (\is_array($value) && isset($result[$key]) && \is_array($result[$key])) {
                $result[$key] = self::mergeCapabilities($result[$key], $value);
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $capabilities
     */
    private static function setDot(array &$capabilities, string $dotKey, mixed $value): void
    {
        $parts = explode('.', $dotKey);
        $node = &$capabilities;
        $last = array_pop($parts);
        foreach ($parts as $part) {
            if (!isset($node[$part]) || !\is_array($node[$part])) {
                $node[$part] = [];
            }
            $node = &$node[$part];
        }
        $node[$last] = $value;
    }
}
