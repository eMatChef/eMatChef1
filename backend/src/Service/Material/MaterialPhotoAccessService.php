<?php

declare(strict_types=1);

namespace App\Service\Material;

use App\Entity\MaterialItem;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Zugriff auf Material-Fotos: Department-Mitglied lesen; MW/DC schreiben.
 */
class MaterialPhotoAccessService
{
    /** @var list<string> */
    private const MANAGE_ROLES = ['mw', 'dc', 'matwart', 'depchef'];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function assertCanViewPhoto(User $user, MaterialItem $material): void
    {
        if ($this->canViewPhoto($user, $material)) {
            return;
        }

        throw new AccessDeniedHttpException('Kein Zugriff auf diese Fotos');
    }

    public function assertCanUploadPhoto(User $user, MaterialItem $material): void
    {
        if ($this->canUploadPhoto($user, $material)) {
            return;
        }

        throw new AccessDeniedHttpException('Kein Zugriff zum Hochladen von Fotos');
    }

    public function canViewPhoto(User $user, MaterialItem $material): bool
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $material->getDepartmentId()]);

        return $membership instanceof Membership;
    }

    public function canUploadPhoto(User $user, MaterialItem $material): bool
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $material->getDepartmentId()]);
        if (!$membership instanceof Membership) {
            return false;
        }

        $role = strtolower(trim((string) ($membership->getRole() ?? '')));

        return \in_array($role, self::MANAGE_ROLES, true);
    }

    public function requireMaterialById(string $materialId): MaterialItem
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
        if (!$material instanceof MaterialItem || $material->getDeletedAt() !== null) {
            throw new \InvalidArgumentException('Material nicht gefunden');
        }

        return $material;
    }
}
