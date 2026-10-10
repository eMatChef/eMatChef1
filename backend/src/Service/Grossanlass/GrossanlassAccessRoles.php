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
            'bereichsleitung' => 'bl',
            'logistikwart' => 'lw',
            'co_logistikwart' => 'clw',
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

    /** Ersteinrichtung bearbeiten (Stammdaten, Ressorts, Mitglieder): MW, Co-MW und OK-Leitung. */
    public static function canSetup(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw', 'dc']);
    }

    /** Ersteinrichtung freigeben: MW und OK-Leitung, nicht der Co-MW. */
    public static function canReleaseSetup(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'dc']);
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

    /** Ressorts/Bauprojekte und Mitglieder anlassweit — nicht Planung/Postfach. */
    public static function canManageStruktur(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw', 'dc']);
    }

    /** Standard-Mailtexte lesen: Postfach oder OK-Überblick. */
    public static function canSeeMailSettings(string $role): bool
    {
        return self::canWorkMailbox($role) || self::canSeeAnlassOverview($role);
    }

    public static function canOperateAusgabe(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw']);
    }

    public static function canVerifyDriveCard(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw']);
    }

    /** Bereichsleitung: Systemrolle `bl`, nicht der Leader-Stern. */
    public static function isBereichsleitung(string $role): bool
    {
        return self::normalize($role) === 'bl';
    }

    /** MW/CMW/OK legen geplant an. Bereichsleitung reicht ein (pending). */
    public static function submitsEinsatzDirectlyFree(string $role): bool
    {
        return self::isOneOf($role, ['mw', 'cmw', 'dc']);
    }
}
