<?php

namespace App\Controller\Trait;

use App\Entity\Department;
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

        $role = $m->getRole();
        if (!in_array($role, ['mw', 'dc'], true)) {
            return new JsonResponse(['error' => 'Nur Materialchef oder Departmentchef'], 403);
        }

        return null;
    }
}
