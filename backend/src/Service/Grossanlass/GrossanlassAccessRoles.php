<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Reine Rollen-Matrix für Grossanlass (ohne DB). membership.role → Checks §4.
 */
final class GrossanlassAccessRoles
{
    public static function normalize(string $role): string
    {
        $value = strtolower(trim($role));

        return match ($value) {
            'matwart' => 'mw',
            'depchef' => 'dc',
            default => $value,
        };
    }

    /**
     * @param list<string> $allowed
     */
    public static function isOneOf(string $role, array $allowed): bool
    {
        return in_array(self::normalize($role), $allowed, true);
    }

    public static function canWorkMailbox(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw', 'komm', 'spon']);
    }

    public static function canTakeInquiry(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw']);
    }

    public static function canCreateMailDrafts(string $role): bool
    {
        return self::isOneOf($role, ['mw']);
    }

    public static function canSendMail(string $role): bool
    {
        return self::isOneOf($role, ['mw']);
    }

    public static function canConnectGmail(string $role): bool
    {
        return self::isOneOf($role, ['mw']);
    }

    public static function canApproveEinsatz(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw', 'dc']);
    }

    public static function canReleaseTrip(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw']);
    }

    public static function canManageProcurement(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw']);
    }

    public static function canSeeAnlassOverview(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw', 'dc']);
    }

    public static function canOperateAusgabe(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw']);
    }

    public static function canVerifyDriveCard(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw']);
    }

    /** MW/CMW/OK-L reichen Einsatz direkt frei; Leader nur einreichen (pending). */
    public static function submitsEinsatzDirectlyFree(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw', 'dc']);
    }
}
