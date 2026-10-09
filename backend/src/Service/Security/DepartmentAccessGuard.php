<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\Membership;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Mandantentrennung für Department-Daten: Zugriff nur mit Mitgliedschaft im Department oder mit Verwaltungszuständigkeit
 * (Superadmin, Orgchef/Suborgchef im Scope-Baum). Eine Mitgliedschaft in Department A öffnet nie Department B, und eine
 * Verwaltungsrolle ersetzt keine operative Rolle; rollenspezifische Rechte prüfen die Aufrufer zusätzlich.
 */
final class DepartmentAccessGuard
{
    /**
     * Basis-Mitgliedsrollen ohne Zugang zu Department-Einstellungen. Entspricht dem Frontend-Modell
     * (`DEPARTMENT_BASIC_MEMBER_ROLES`, `denyDepartmentRoles` der Einstellungsseiten): Mitglied und Leader 1–3.
     */
    public const BASIC_MEMBER_ROLES = ['u', 'user', 'l1', 'l2', 'l3'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminCapabilityChecker $adminCapabilities,
    ) {
    }

    public function canAccess(?User $user, ?string $departmentId): bool
    {
        if (!$user instanceof User || $departmentId === null || $departmentId === '') {
            return false;
        }
        if ($this->adminCapabilities->canAdministerDepartment($user, $departmentId)) {
            return true;
        }

        return $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]) instanceof Membership;
    }

    /**
     * Schreibzugriff auf Department-Einstellungen, Kategorien und eigene Vorlagen: Verwaltungszuständige im Baum oder
     * Mitglieder mit einer Rolle ausserhalb der Basis-Rollen (mw, cmw, dc, bl, komm, spon, lw, clw). Normale Mitglieder
     * (`u`) und Leader 1–3 lesen nur.
     */
    public function canManage(?User $user, ?string $departmentId): bool
    {
        if (!$user instanceof User || $departmentId === null || $departmentId === '') {
            return false;
        }
        if ($this->adminCapabilities->canAdministerDepartment($user, $departmentId)) {
            return true;
        }
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$membership instanceof Membership) {
            return false;
        }

        return !\in_array(strtolower(trim((string) $membership->getRole())), self::BASIC_MEMBER_ROLES, true);
    }

    public function denyManage(?User $user, ?string $departmentId): ?JsonResponse
    {
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if ($this->canManage($user, $departmentId)) {
            return null;
        }

        return new JsonResponse([
            'error' => $this->canAccess($user, $departmentId)
                ? 'Keine Berechtigung, diese Daten des Departments zu ändern'
                : 'Kein Zugriff auf dieses Department',
        ], 403);
    }

    public function deny(?User $user, ?string $departmentId): ?JsonResponse
    {
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        return $this->canAccess($user, $departmentId)
            ? null
            : new JsonResponse(['error' => 'Kein Zugriff auf dieses Department'], 403);
    }
}
