<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\SupplierCompany;
use App\Entity\SupplierMembership;
use App\Entity\User;
use App\Repository\SupplierMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Supplier-Firmenzugriff: Membership + status=active.
 */
class SupplierCompanyAccessService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierMembershipRepository $supplierMembershipRepository,
    ) {
    }

    public function canAccessActiveCompany(User $user, string $companyId): bool
    {
        $membership = $this->supplierMembershipRepository->findOneBy([
            'userId' => $user->getId(),
            'supplierCompanyId' => $companyId,
        ]);

        if (!$membership instanceof SupplierMembership) {
            return false;
        }

        return $membership->getSupplierCompany()->getStatus() === SupplierCompany::STATUS_ACTIVE;
    }

    public function assertSupplierCompanyAccess(User $user, string $companyId): void
    {
        if (!$this->canAccessActiveCompany($user, $companyId)) {
            throw new AccessDeniedHttpException('Kein Zugriff auf diese Lieferanten-Firma');
        }
    }

    public function userHasActiveSupplierMembership(User $user): bool
    {
        return \count($this->loadMembershipsForUser($user)) > 0;
    }

    /**
     * @return list<SupplierMembership>
     */
    public function loadMembershipsForUser(User $user): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('sm', 'c')
            ->from(SupplierMembership::class, 'sm')
            ->innerJoin('sm.supplierCompany', 'c')
            ->where('sm.userId = :userId')
            ->andWhere('c.status = :status')
            ->setParameter('userId', $user->getId())
            ->setParameter('status', SupplierCompany::STATUS_ACTIVE)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{id: string, name: string, role: string, status: string, capabilities: list<string>, is_primary: bool}>
     */
    public function serializeCompaniesForUser(User $user): array
    {
        $memberships = $this->entityManager->createQueryBuilder()
            ->select('sm', 'c')
            ->from(SupplierMembership::class, 'sm')
            ->innerJoin('sm.supplierCompany', 'c')
            ->where('sm.userId = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($memberships as $membership) {
            if (!$membership instanceof SupplierMembership) {
                continue;
            }
            $company = $membership->getSupplierCompany();
            $items[] = [
                'id' => (string) $company->getId(),
                'name' => $company->getName(),
                'role' => $membership->getRole(),
                'status' => $company->getStatus(),
                'capabilities' => $company->getCapabilities(),
                'is_primary' => $membership->getIsPrimary(),
            ];
        }

        return $items;
    }

    public function resolveLastUsedSupplierCompanyId(User $user, array $supplierCompanies): ?string
    {
        if ($supplierCompanies === []) {
            return null;
        }

        $allowedIds = array_map(static fn (array $c): string => $c['id'], $supplierCompanies);
        $stored = $user->getLastUsedSupplierCompanyId();
        if ($stored !== null && \in_array($stored, $allowedIds, true)) {
            $storedCompany = array_values(array_filter(
                $supplierCompanies,
                static fn (array $c): bool => $c['id'] === $stored && $c['status'] === SupplierCompany::STATUS_ACTIVE
            ));
            if ($storedCompany !== []) {
                return $stored;
            }
        }

        foreach ($supplierCompanies as $company) {
            if ($company['status'] === SupplierCompany::STATUS_ACTIVE && !empty($company['is_primary'])) {
                return $company['id'];
            }
        }

        foreach ($supplierCompanies as $company) {
            if ($company['status'] === SupplierCompany::STATUS_ACTIVE) {
                return $company['id'];
            }
        }

        return null;
    }
}
