<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Membership;
use App\Entity\User;

/**
 * Regeln für PATCH /api/users/{id}/admin.
 *
 * Gilt für alle Akteure: Superadmin-Ziele gesperrt, nur gültige `state`-Werte.
 * Zusätzlich für Nicht-Superadmins (Org/Sub mit users.global_manage):
 * - Admin-Ziele nur Org → Sub, und nur wenn der Scope des Ziels im eigenen liegt;
 * - normale User nur ohne Membership oder mit mindestens einer Membership im eigenen Scope;
 * - Membership-Änderungen nur in zugänglichen Departments (unveränderte Einträge bleiben erlaubt);
 * - keine Änderung der Login-E-Mail.
 *
 * Scope und Hierarchie kommen ausschliesslich aus AdminCapabilityChecker.
 */
final class AdminUserUpdatePolicy
{
    public const ALLOWED_STATES = ['active', 'inactive', 'disabled'];

    public function __construct(
        private readonly AdminCapabilityChecker $adminCapabilityChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param list<Membership>     $existingMemberships
     *
     * @throws AdminUserUpdateDeniedException
     */
    public function assertUpdateAllowed(User $actor, User $target, array $data, array $existingMemberships): void
    {
        if ($target->getProfile()?->hasSuperAdminRole()) {
            throw new AdminUserUpdateDeniedException('Superadmin-Konten werden hier nicht verwaltet');
        }

        $this->assertValidState($data);

        if ($this->adminCapabilityChecker->isSuperAdmin($actor)) {
            return;
        }

        $this->assertTargetWithinScope($actor, $target, $existingMemberships);
        $this->assertMembershipChangesWithinScope($actor, $data, $existingMemberships);

        if ($this->requestedEmailChange($target, $data) !== null) {
            throw new AdminUserUpdateDeniedException('Nur Superadmins dürfen die Login-E-Mail ändern');
        }
    }

    /**
     * Normalisierte neue E-Mail, wenn sie von der aktuellen Login-E-Mail abweicht; sonst null.
     *
     * @param array<string, mixed> $data
     */
    public function requestedEmailChange(User $target, array $data): ?string
    {
        if (!\array_key_exists('email', $data)) {
            return null;
        }

        $requested = strtolower(trim((string) $data['email']));
        $current = strtolower(trim((string) ($target->getProfile()?->getEmail() ?? '')));

        return $requested !== $current ? $requested : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertValidState(array $data): void
    {
        if (!\array_key_exists('state', $data)) {
            return;
        }
        if (!\in_array($data['state'], self::ALLOWED_STATES, true)) {
            throw new AdminUserUpdateDeniedException('Ungültiger Status', 400);
        }
    }

    /**
     * @param list<Membership> $existingMemberships
     */
    private function assertTargetWithinScope(User $actor, User $target, array $existingMemberships): void
    {
        $targetRole = $this->adminCapabilityChecker->getGlobalRole($target);
        $isAdminTarget = \in_array($targetRole, [
            AdminCapabilityRegistry::GLOBAL_ROLE_ORG,
            AdminCapabilityRegistry::GLOBAL_ROLE_SUB,
        ], true);

        if ($isAdminTarget) {
            $actorRole = $this->adminCapabilityChecker->getGlobalRole($actor);
            $isOrgOverSub = $actorRole === AdminCapabilityRegistry::GLOBAL_ROLE_ORG
                && $targetRole === AdminCapabilityRegistry::GLOBAL_ROLE_SUB;
            if (!$isOrgOverSub) {
                throw new AdminUserUpdateDeniedException('Keine Berechtigung, diesen Admin zu bearbeiten');
            }

            // Ein Admin-Konto ohne Zuweisung hat keinen Verwaltungsbereich: nicht Teil des Bereichs eines anderen Admins.
            if (!$this->adminCapabilityChecker->hasAdministrativeScope($target)) {
                throw new AdminUserUpdateDeniedException('Der Bereich dieses Admins liegt ausserhalb deines Verwaltungsbereichs');
            }

            $withinScope = self::isSubset(
                $this->adminCapabilityChecker->getAccessibleOrganisationIds($target),
                $this->adminCapabilityChecker->getAccessibleOrganisationIds($actor)
            ) && self::isSubset(
                $this->adminCapabilityChecker->getAccessibleDepartmentIds($target),
                $this->adminCapabilityChecker->getAccessibleDepartmentIds($actor)
            );
            if (!$withinScope) {
                throw new AdminUserUpdateDeniedException('Der Bereich dieses Admins liegt ausserhalb deines Verwaltungsbereichs');
            }

            return;
        }

        if ($existingMemberships === []) {
            return;
        }

        foreach ($existingMemberships as $membership) {
            if ($this->adminCapabilityChecker->canAccessDepartment($actor, $membership->getDepartmentId())) {
                return;
            }
        }

        throw new AdminUserUpdateDeniedException('Dieser Benutzer liegt ausserhalb deines Verwaltungsbereichs');
    }

    /**
     * @param array<string, mixed> $data
     * @param list<Membership>     $existingMemberships
     */
    private function assertMembershipChangesWithinScope(User $actor, array $data, array $existingMemberships): void
    {
        if (!\array_key_exists('memberships', $data) || !\is_array($data['memberships'])) {
            return;
        }

        $existing = [];
        foreach ($existingMemberships as $membership) {
            $existing[$membership->getDepartmentId()] = [
                'role' => $membership->getRole(),
                'is_primary' => $membership->getIsPrimary(),
            ];
        }

        // Gleiche Normalisierung wie UserController::updateAdminUser (Rolle klein, erste Zeile primär, falls keine).
        $requested = [];
        $hasPrimary = false;
        foreach ($data['memberships'] as $row) {
            if (!\is_array($row) || empty($row['department_id'])) {
                continue;
            }
            $isPrimary = (bool) ($row['is_primary'] ?? false);
            $hasPrimary = $hasPrimary || $isPrimary;
            $requested[(string) $row['department_id']] = [
                'role' => strtolower((string) ($row['role'] ?? 'u')),
                'is_primary' => $isPrimary,
            ];
        }
        if (!$hasPrimary && $requested !== []) {
            $requested[array_key_first($requested)]['is_primary'] = true;
        }

        $changedDepartmentIds = [];
        foreach ($requested as $departmentId => $row) {
            if (($existing[$departmentId] ?? null) !== $row) {
                $changedDepartmentIds[] = (string) $departmentId;
            }
        }
        foreach (array_keys($existing) as $departmentId) {
            if (!isset($requested[$departmentId])) {
                $changedDepartmentIds[] = (string) $departmentId;
            }
        }

        foreach ($changedDepartmentIds as $departmentId) {
            if (!$this->adminCapabilityChecker->canAccessDepartment($actor, $departmentId)) {
                throw new AdminUserUpdateDeniedException(
                    'Department-Zuordnungen ausserhalb deines Verwaltungsbereichs dürfen nicht geändert werden'
                );
            }
        }
    }

    /**
     * null = alle (unbeschränkt).
     *
     * @param list<string>|null $candidate
     * @param list<string>|null $container
     */
    private static function isSubset(?array $candidate, ?array $container): bool
    {
        if ($container === null) {
            return true;
        }
        if ($candidate === null) {
            return false;
        }

        return array_diff($candidate, $container) === [];
    }
}
