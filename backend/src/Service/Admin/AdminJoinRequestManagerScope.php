<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\AdminJoinRequest;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\MembershipRoleCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Scope of a department manager (mw/dc without global admin role) for admin join requests.
 *
 * A manager of department D only handles requests of D's organisation whose requested parent
 * department is D or one of its sub-departments. Requests without organisation or without a
 * resolvable parent department belong to global admins.
 */
final class AdminJoinRequestManagerScope
{
    public const VIEW_ROLES = ['mw', 'dc'];
    public const ASSIGN_ROLES = ['mw'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminCapabilityDepartmentScope $departmentScope,
    ) {}

    /**
     * @param list<string> $allowedRoles
     */
    public function resolve(User $actor, string $departmentId, array $allowedRoles): ?AdminJoinRequestManagerScopeContext
    {
        if ($departmentId === '') {
            return null;
        }
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $actor->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership instanceof Membership || !in_array($membership->getRole(), $allowedRoles, true)) {
            return null;
        }
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            return null;
        }

        $organisationId = $department->getOrganisationId();
        $departmentIds = $this->departmentScope->filterDepartmentIdsWithinOrganisations(
            $this->departmentScope->expandSubtreeDepartmentIds([$departmentId]),
            [$organisationId],
        );

        return new AdminJoinRequestManagerScopeContext(
            $departmentId,
            $organisationId,
            $membership->getRole(),
            $departmentIds,
        );
    }

    public function restrictQuery(QueryBuilder $queryBuilder, string $alias, AdminJoinRequestManagerScopeContext $scope): void
    {
        $queryBuilder
            ->andWhere(sprintf('%s.requestedOrganisationId = :managerScopeOrganisationId', $alias))
            ->andWhere(sprintf('%s.requestedParentDepartmentId IN (:managerScopeDepartmentIds)', $alias))
            ->setParameter('managerScopeOrganisationId', $scope->organisationId)
            ->setParameter('managerScopeDepartmentIds', $scope->departmentIds !== [] ? $scope->departmentIds : ['']);
    }

    public function contains(AdminJoinRequest $adminRequest, AdminJoinRequestManagerScopeContext $scope): bool
    {
        $parentDepartmentId = $adminRequest->getRequestedParentDepartmentId();

        return $adminRequest->getRequestedOrganisationId() === $scope->organisationId
            && $parentDepartmentId !== null
            && $scope->containsDepartment($parentDepartmentId);
    }

    /**
     * @return array{status: int, error: string}|null null when the manager may assign the role there
     */
    public function assignmentDenial(
        AdminJoinRequestManagerScopeContext $scope,
        Department $targetDepartment,
        string $role,
    ): ?array {
        if (
            $targetDepartment->getOrganisationId() !== $scope->organisationId
            || !$scope->containsDepartment((string) $targetDepartment->getId())
        ) {
            return ['status' => 403, 'error' => 'Ziel-Department liegt ausserhalb deines Bereichs'];
        }
        if (!MembershipRoleCatalog::isAllowed($targetDepartment, $role)) {
            return ['status' => 400, 'error' => 'Diese Rolle ist in diesem Department nicht erlaubt'];
        }
        if (!MembershipRoleCatalog::canAssign($scope->role, $role, $targetDepartment->isGrossanlass())) {
            return ['status' => 403, 'error' => 'Diese Rolle darfst du nicht vergeben'];
        }

        return null;
    }
}
