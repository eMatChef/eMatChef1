<?php

namespace App\Controller\Trait;

use App\Entity\Department;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

trait AccountingMwOrDcTrait
{
    protected function assertAccountingMwOrDc(EntityManagerInterface $entityManager, string $departmentId): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $dept = $entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $m = $entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$m) {
            return new JsonResponse(['error' => 'Kein Zugriff auf dieses Department'], 403);
        }

        $role = strtolower(trim((string) $m->getRole()));
        if (!in_array($role, ['mw', 'dc', 'matwart', 'depchef'], true)) {
            return new JsonResponse(['error' => 'Nur Materialchef oder Departmentchef'], 403);
        }

        return null;
    }

    /**
     * Lesender Zugriff: MW/DC, Leiter 1–3 oder Gruppenchef im Department.
     */
    protected function assertAccountingGroupReportAccess(EntityManagerInterface $entityManager, string $departmentId): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $dept = $entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $m = $entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$m) {
            return new JsonResponse(['error' => 'Kein Zugriff auf dieses Department'], 403);
        }

        $role = strtolower(trim((string) $m->getRole()));
        if (in_array($role, ['mw', 'dc', 'matwart', 'depchef', 'l1', 'l2', 'l3'], true)) {
            return null;
        }

        if ($this->isGroupLeaderInDepartment($entityManager, $user, $departmentId)) {
            return null;
        }

        return new JsonResponse(['error' => 'Keine Berechtigung für Gruppen-Auswertung'], 403);
    }

    /**
     * @return list<string> Gruppen-IDs, in denen der User Gruppenchef ist (Department-Scope).
     */
    protected function ledGroupIdsInDepartment(EntityManagerInterface $entityManager, User $user, string $departmentId): array
    {
        $rows = $entityManager->createQueryBuilder()
            ->select('g.id')
            ->from(GroupMembership::class, 'gm')
            ->innerJoin('gm.group', 'g')
            ->where('gm.userId = :uid')
            ->andWhere('gm.role = :leader')
            ->andWhere('g.departmentId = :did')
            ->setParameter('uid', $user->getId())
            ->setParameter('leader', 'leader')
            ->setParameter('did', $departmentId)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter(array_map(static fn ($id) => (string) $id, $rows)));
    }

    protected function isGroupLeaderInDepartment(EntityManagerInterface $entityManager, User $user, string $departmentId): bool
    {
        return $this->ledGroupIdsInDepartment($entityManager, $user, $departmentId) !== [];
    }

    /**
     * @return 'full'|'leader'|'leader_limited'
     */
    protected function accountingGroupReportScope(EntityManagerInterface $entityManager, string $departmentId): string
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return 'leader_limited';
        }

        $m = $entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$m) {
            return 'leader_limited';
        }

        $role = strtolower(trim((string) $m->getRole()));
        if (in_array($role, ['mw', 'dc', 'matwart', 'depchef', 'l1', 'l2', 'l3'], true)) {
            return 'full';
        }

        return 'leader_limited';
    }
}
