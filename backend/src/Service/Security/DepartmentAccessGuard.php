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
