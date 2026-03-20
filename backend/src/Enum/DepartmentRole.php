<?php

namespace App\Enum;

/**
 * Department Rollen - Hierarchische Struktur
 * 
 * Hierarchie (von oben nach unten):
 * 1. superadmin          - Chef über alles
 * 2. organisationschef   - Über seine Organisation und untere Departments
 * 3. suborgchef          - Kann seine Departments verwalten, User hinzufügen/löschen
 * 4. matwart             - Materialchef des Departments
 * 5. depchef             - Chef des Departments
 * 6. leader1             - Hierarchische Leiter-Funktion (Ebene 1)
 * 7. leader2             - Hierarchische Leiter-Funktion (Ebene 2)
 * 8. leader3             - Hierarchische Leiter-Funktion (Ebene 3)
 * 9. user                - Basis-User
 */
enum DepartmentRole: string
{
    case SUPERADMIN = 'sa';           // superadmin
    case ORGANISATIONSCHEF = 'org';   // organisationschef
    case SUBORGCHEF = 'sub';          // suborgchef
    case MATWART = 'mw';              // matwart
    case DEPCHEF = 'dc';              // depchef
    case LEADER1 = 'l1';              // leader1
    case LEADER2 = 'l2';              // leader2
    case LEADER3 = 'l3';              // leader3
    case USER = 'u';                  // user

    /**
     * Gibt alle verfügbaren Rollen zurück
     */
    public static function all(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Prüft ob eine Rolle gültig ist
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    /**
     * Gibt die Hierarchie-Ebene zurück (0 = höchste, 8 = niedrigste)
     */
    public function getLevel(): int
    {
        return match($this) {
            self::SUPERADMIN => 0,
            self::ORGANISATIONSCHEF => 1,
            self::SUBORGCHEF => 2,
            self::MATWART => 3,
            self::DEPCHEF => 3,
            self::LEADER1 => 4,
            self::LEADER2 => 5,
            self::LEADER3 => 6,
            self::USER => 7,
        };
    }

    /**
     * Prüft ob diese Rolle eine andere Rolle verwalten kann
     * Eine Rolle kann nur Rollen verwalten, die unter ihr in der Hierarchie stehen
     */
    public function canManageRole(DepartmentRole $otherRole): bool
    {
        // Superadmin kann alles verwalten
        if ($this === self::SUPERADMIN) {
            return true;
        }

        // Eine Rolle kann nur Rollen verwalten, die eine höhere Level-Nummer haben (also tiefer in der Hierarchie)
        return $this->getLevel() < $otherRole->getLevel();
    }

    /**
     * Gibt alle Rollen zurück, die diese Rolle verwalten kann
     */
    public function getManageableRoles(): array
    {
        if ($this === self::SUPERADMIN) {
            return self::all();
        }

        return array_filter(
            self::cases(),
            fn($role) => $this->canManageRole($role)
        );
    }

    /**
     * Gibt die Symfony ROLE_* Konstante zurück
     */
    public function toSymfonyRole(): string
    {
        return match($this) {
            self::SUPERADMIN => 'ROLE_SUPERADMIN',
            self::ORGANISATIONSCHEF => 'ROLE_ORGANISATIONSCHEF',
            self::SUBORGCHEF => 'ROLE_SUBORGCHEF',
            self::MATWART => 'ROLE_MATWART',
            self::DEPCHEF => 'ROLE_DEPCHEF',
            self::LEADER1 => 'ROLE_LEADER1',
            self::LEADER2 => 'ROLE_LEADER2',
            self::LEADER3 => 'ROLE_LEADER3',
            self::USER => 'ROLE_USER',
        };
    }

    /**
     * Gibt den vollständigen Namen der Rolle zurück (für Anzeige)
     */
    public function getFullName(): string
    {
        return match($this) {
            self::SUPERADMIN => 'superadmin',
            self::ORGANISATIONSCHEF => 'organisationschef',
            self::SUBORGCHEF => 'suborgchef',
            self::MATWART => 'matwart',
            self::DEPCHEF => 'depchef',
            self::LEADER1 => 'leader1',
            self::LEADER2 => 'leader2',
            self::LEADER3 => 'leader3',
            self::USER => 'user',
        };
    }

    /**
     * Gibt die Anzeige-Bezeichnung zurück
     */
    public function getLabel(): string
    {
        return match($this) {
            self::SUPERADMIN => 'Superadmin',
            self::ORGANISATIONSCHEF => 'Organisationschef',
            self::SUBORGCHEF => 'Suborgchef',
            self::MATWART => 'Materialchef',
            self::DEPCHEF => 'Departmentchef',
            self::LEADER1 => 'Leader 1',
            self::LEADER2 => 'Leader 2',
            self::LEADER3 => 'Leader 3',
            self::USER => 'User',
        };
    }
}
