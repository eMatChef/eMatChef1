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
 * 4b. cmw                - Co-Materialchef (Grossanlass)
 * 5. depchef             - Chef des Departments (Grossanlass: OK-Leitung)
 * 5b. komm / spon        - Kommunikation / Sponsoring (Grossanlass, Postfach)
 * 6. leader1             - Hierarchische Leiter-Funktion (Ebene 1)
 * 7. leader2             - Hierarchische Leiter-Funktion (Ebene 2)
 * 8. leader3             - Hierarchische Leiter-Funktion (Ebene 3)
 * 9. user                - Basis-User / Helfer
 *
 * J+S-Coach ist kein Rollenwert, sondern Flag membership.is_js_coach.
 */
enum DepartmentRole: string
{
    case SUPERADMIN = 'sa';
    case ORGANISATIONSCHEF = 'org';
    case SUBORGCHEF = 'sub';
    case MATWART = 'mw';
    case CO_MATWART = 'cmw';
    case DEPCHEF = 'dc';
    case KOMMUNIKATION = 'komm';
    case SPONSORING = 'spon';
    case LEADER1 = 'l1';
    case LEADER2 = 'l2';
    case LEADER3 = 'l3';
    case USER = 'u';

    public static function all(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    public function getLevel(): int
    {
        return match($this) {
            self::SUPERADMIN => 0,
            self::ORGANISATIONSCHEF => 1,
            self::SUBORGCHEF => 2,
            self::MATWART => 3,
            self::CO_MATWART => 4,
            self::DEPCHEF => 5,
            self::KOMMUNIKATION => 6,
            self::SPONSORING => 6,
            self::LEADER1 => 7,
            self::LEADER2 => 8,
            self::LEADER3 => 9,
            self::USER => 10,
        };
    }

    public function canManageRole(DepartmentRole $otherRole): bool
    {
        if ($this === self::SUPERADMIN) {
            return true;
        }

        return $this->getLevel() < $otherRole->getLevel();
    }

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

    public function toSymfonyRole(): string
    {
        return match($this) {
            self::SUPERADMIN => 'ROLE_SUPERADMIN',
            self::ORGANISATIONSCHEF => 'ROLE_ORGANISATIONSCHEF',
            self::SUBORGCHEF => 'ROLE_SUBORGCHEF',
            self::MATWART => 'ROLE_MATWART',
            self::CO_MATWART => 'ROLE_CO_MATWART',
            self::DEPCHEF => 'ROLE_DEPCHEF',
            self::KOMMUNIKATION => 'ROLE_KOMMUNIKATION',
            self::SPONSORING => 'ROLE_SPONSORING',
            self::LEADER1 => 'ROLE_LEADER1',
            self::LEADER2 => 'ROLE_LEADER2',
            self::LEADER3 => 'ROLE_LEADER3',
            self::USER => 'ROLE_USER',
        };
    }

    public function getFullName(): string
    {
        return match($this) {
            self::SUPERADMIN => 'superadmin',
            self::ORGANISATIONSCHEF => 'organisationschef',
            self::SUBORGCHEF => 'suborgchef',
            self::MATWART => 'matwart',
            self::CO_MATWART => 'co_matwart',
            self::DEPCHEF => 'depchef',
            self::KOMMUNIKATION => 'kommunikation',
            self::SPONSORING => 'sponsoring',
            self::LEADER1 => 'leader1',
            self::LEADER2 => 'leader2',
            self::LEADER3 => 'leader3',
            self::USER => 'user',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::SUPERADMIN => 'Superadmin',
            self::ORGANISATIONSCHEF => 'Organisationschef',
            self::SUBORGCHEF => 'Suborgchef',
            self::MATWART => 'Materialchef',
            self::CO_MATWART => 'Co-Materialchef',
            self::DEPCHEF => 'Departmentchef',
            self::KOMMUNIKATION => 'Kommunikation',
            self::SPONSORING => 'Sponsoring',
            self::LEADER1 => 'Leader 1',
            self::LEADER2 => 'Leader 2',
            self::LEADER3 => 'Leader 3',
            self::USER => 'User',
        };
    }
}
