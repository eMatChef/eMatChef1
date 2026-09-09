<?php

namespace App\Service;

use App\Entity\Department;

/**
 * Erlaubte membership.role-Werte und Vergabe-Rang (streng niedriger = vergeben).
 * Pfadi und Grossanlass teilen nicht dieselbe Liste (kein L1–L3 im GA; CMW/Komm/Spon nur dort).
 */
final class MembershipRoleCatalog
{
    /** @var list<string> */
    public const PFADI = ['mw', 'dc', 'l1', 'l2', 'l3', 'u'];

    /** @var list<string> */
    public const GROSSANLASS = ['mw', 'cmw', 'dc', 'komm', 'spon', 'u'];

    /** @var list<string> */
    public const ALL = ['mw', 'cmw', 'dc', 'komm', 'spon', 'l1', 'l2', 'l3', 'u'];

    /** @var array<string, int> */
    private const PFADI_RANK = [
        'mw' => 0,
        'dc' => 1,
        'l1' => 2,
        'l2' => 3,
        'l3' => 4,
        'u' => 5,
    ];

    /** @var array<string, int> komm ≈ spon */
    private const GROSSANLASS_RANK = [
        'mw' => 0,
        'cmw' => 1,
        'dc' => 2,
        'komm' => 3,
        'spon' => 3,
        'u' => 4,
    ];

    /**
     * @return list<string>
     */
    public static function allowedFor(?Department $department): array
    {
        if ($department instanceof Department && $department->isGrossanlass()) {
            return self::GROSSANLASS;
        }

        return self::PFADI;
    }

    public static function isAllowed(?Department $department, string $role): bool
    {
        return in_array($role, self::allowedFor($department), true);
    }

    public static function canAssign(string $actorRole, string $targetRole, bool $grossanlass): bool
    {
        $ranks = $grossanlass ? self::GROSSANLASS_RANK : self::PFADI_RANK;
        if (!isset($ranks[$actorRole], $ranks[$targetRole])) {
            return false;
        }

        return $ranks[$targetRole] > $ranks[$actorRole];
    }
}
