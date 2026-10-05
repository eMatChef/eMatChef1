<?php

declare(strict_types=1);

namespace App\Service\Auth;

/**
 * The only MiData/PBS roles that may lead to a privileged eMatChef membership.
 *
 * Keyed by the exact technical Hitobito role class; localized role names are never used. A lower priority
 * value wins when one Abteilung has several supported roles. Group::Abteilung::AbteilungsleitungStv is
 * deliberately not supported.
 */
final class MiDataSupportedRoleCatalog
{
    public const MATERIALWART = 'Group::Abteilung::Materialwart';
    public const ABTEILUNGSLEITUNG = 'Group::Abteilung::Abteilungsleitung';

    /**
     * @var array<string, array{group_type: string, membership_role: string, label: string, priority: int}>
     */
    private const ROLES = [
        self::MATERIALWART => [
            'group_type' => 'Group::Abteilung',
            'membership_role' => 'mw',
            'label' => 'materialwart',
            'priority' => 0,
        ],
        self::ABTEILUNGSLEITUNG => [
            'group_type' => 'Group::Abteilung',
            'membership_role' => 'dc',
            'label' => 'abteilungsleitung',
            'priority' => 1,
        ],
    ];

    public static function isSupported(string $roleClass): bool
    {
        return isset(self::ROLES[$roleClass]);
    }

    public static function expectedGroupType(string $roleClass): ?string
    {
        return self::ROLES[$roleClass]['group_type'] ?? null;
    }

    /**
     * Internal membership role (mw, dc) granted for a verified role.
     */
    public static function membershipRole(string $roleClass): ?string
    {
        return self::ROLES[$roleClass]['membership_role'] ?? null;
    }

    /**
     * Stable, non-technical role key for the frontend.
     */
    public static function label(string $roleClass): ?string
    {
        return self::ROLES[$roleClass]['label'] ?? null;
    }

    public static function priority(string $roleClass): int
    {
        return self::ROLES[$roleClass]['priority'] ?? PHP_INT_MAX;
    }

    /**
     * True when $candidate wins over $current (lower priority value).
     */
    public static function outranks(string $candidate, string $current): bool
    {
        return self::priority($candidate) < self::priority($current);
    }
}
