<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\MiDataDepartmentOnboarding;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\User;
use App\Repository\DepartmentRepository;
use App\Repository\ExternalStructureIdentityRepository;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\AuditLogger;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use App\Util\IdGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates or reuses the eMatChef structure for a verified PBS Abteilung.
 *
 * Bund → existing Organisation (administrative mapping, never created here),
 * Kantonalverband → root Department, Region → Department below its parent level,
 * Abteilung → Department below the last Region or the Kantonalverband. Root is ignored.
 * Links use stable external IDs only; names never link.
 */
class MiDataDepartmentStructureProvisioner
{
    private const PROVIDER = 'midata';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalStructureIdentityRepository $structureIdentities,
        private readonly DepartmentRepository $departments,
        private readonly AccountingCostCenterBootstrapService $costCenterBootstrap,
        private readonly WorkshopSparePartsCategoryBootstrapService $sparePartsBootstrap,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Returns the Organisation the Bund is administratively mapped to.
     *
     * @throws MiDataStructureConflictException when the mapping is missing or targets something else
     */
    public function findMappedBundOrganisation(HitobitoGroup $bund): Organisation
    {
        $mapping = $this->structureIdentities->findOneByProviderAndExternalGroupId(self::PROVIDER, $bund->id);
        if ($mapping === null) {
            throw new MiDataStructureConflictException('bund_not_mapped', $bund->id);
        }
        $organisation = $mapping->getOrganisation();
        if (!$mapping->hasExactlyOneInternalTarget() || $organisation === null) {
            throw new MiDataStructureConflictException('bund_mapping_target', $bund->id);
        }

        return $organisation;
    }

    /**
     * @throws MiDataStructureConflictException when existing mappings or names do not fit; the transaction is rolled back
     * @throws UniqueConstraintViolationException when a concurrent request inserted the same mapping first
     */
    public function provision(
        User $user,
        MiDataMaterialwartVerification $verification,
        ?MiDataDepartmentOnboarding $onboarding = null,
    ): MiDataDepartmentProvisioningResult {
        if (!$verification->isConfirmed()) {
            throw new \LogicException('MiData department provisioning requires a confirmed role verification');
        }
        // The freshly verified role decides the membership role (mw, dc), never a stored intent or a role name.
        $membershipRole = MiDataSupportedRoleCatalog::membershipRole($verification->role->type)
            ?? throw new \LogicException('Verified MiData role is not supported');
        $externalDepartment = $verification->department;
        $externalRegions = $verification->regions;
        $externalKantonalverband = $verification->kantonalverband;
        $externalBund = $verification->bund;

        return $this->entityManager->wrapInTransaction(function () use (
            $user,
            $externalDepartment,
            $externalRegions,
            $externalKantonalverband,
            $externalBund,
            $onboarding,
            $membershipRole,
        ): MiDataDepartmentProvisioningResult {
            // Every structure created by this flow lies below one Kantonalverband.
            $this->structureIdentities->lockExternalGroupForStructureChange(self::PROVIDER, $externalKantonalverband->id);

            $organisation = $this->findMappedBundOrganisation($externalBund);
            $createdExternalGroupIds = [];
            $parent = $this->resolveDepartment($externalKantonalverband, $organisation, null, $createdExternalGroupIds);
            foreach ($externalRegions as $externalRegion) {
                $parent = $this->resolveDepartment($externalRegion, $organisation, $parent, $createdExternalGroupIds);
            }
            $createdBeforeDepartment = count($createdExternalGroupIds);
            $department = $this->resolveDepartment($externalDepartment, $organisation, $parent, $createdExternalGroupIds);
            $departmentCreated = count($createdExternalGroupIds) > $createdBeforeDepartment;

            $membershipCreated = $this->ensureMembership($user, $department, $membershipRole);
            $onboarding?->setCompletedAt(new \DateTime());
            $this->entityManager->flush();

            return new MiDataDepartmentProvisioningResult(
                $department,
                $departmentCreated,
                $createdExternalGroupIds,
                $membershipCreated,
            );
        });
    }

    /**
     * @param list<string> $createdExternalGroupIds
     */
    private function resolveDepartment(
        HitobitoGroup $externalGroup,
        Organisation $organisation,
        ?Department $expectedParent,
        array &$createdExternalGroupIds,
    ): Department {
        $mapping = $this->structureIdentities->findOneByProviderAndExternalGroupId(self::PROVIDER, $externalGroup->id);
        if ($mapping !== null) {
            $department = $mapping->getDepartment();
            if (
                !$mapping->hasExactlyOneInternalTarget()
                || $department === null
                || $department->getOrganisationId() !== $organisation->getId()
                || $department->getParentId() !== $expectedParent?->getId()
                || $department->isGrossanlass()
            ) {
                throw new MiDataStructureConflictException('department_mapping_hierarchy', $externalGroup->id);
            }

            return $department;
        }

        $name = $this->requireName($externalGroup);
        $sibling = $this->departments->findConflictingSiblingByName(
            (string) $organisation->getId(),
            $expectedParent?->getId(),
            $name,
        );
        if ($sibling !== null) {
            throw new MiDataStructureConflictException('department_name_conflict', $externalGroup->id);
        }

        $department = new Department();
        $department->setId(IdGenerator::generateUnique($this->entityManager, Department::class));
        $department->setName($name);
        $department->setOrganisation($organisation);
        $department->setParent($expectedParent);
        $this->entityManager->persist($department);
        $this->persistMapping($externalGroup, $department);
        $this->entityManager->flush();

        $this->costCenterBootstrap->ensureDefaultCostCenters($this->entityManager, $department);
        $this->sparePartsBootstrap->ensure($department);
        $createdExternalGroupIds[] = $externalGroup->id;

        return $department;
    }

    /**
     * Adds the verified role (mw, dc). An existing membership is never changed, so nothing is downgraded.
     */
    private function ensureMembership(User $user, Department $department, string $role): bool
    {
        $memberships = $this->entityManager->getRepository(Membership::class);
        if ($memberships->findOneBy(['userId' => $user->getId(), 'departmentId' => $department->getId()]) instanceof Membership) {
            return false;
        }

        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);
        $membership->setRole($role);
        $membership->setIsPrimary($memberships->count(['userId' => $user->getId()]) === 0);
        $this->auditLogger->log(
            'membership',
            AuditLogger::buildMembershipEntityId($user->getId(), $department->getId()),
            'membership_created',
            $user,
            $user,
            $department,
            [
                'role' => ['old' => null, 'new' => $role],
                'is_primary' => ['old' => null, 'new' => $membership->getIsPrimary()],
                'source' => ['old' => null, 'new' => 'midata_materialwart_onboarding'],
            ],
        );
        $this->entityManager->persist($membership);

        return true;
    }

    private function persistMapping(HitobitoGroup $externalGroup, Department $department): void
    {
        $mapping = new ExternalStructureIdentity();
        $mapping->setId(IdGenerator::generateUnique($this->entityManager, ExternalStructureIdentity::class));
        $mapping->setProvider(self::PROVIDER);
        $mapping->setExternalGroupId($externalGroup->id);
        $mapping->setExternalType($externalGroup->type);
        $mapping->setExternalName($externalGroup->name);
        $mapping->setExternalParentId($externalGroup->parentId);
        $mapping->setDepartment($department);
        $this->entityManager->persist($mapping);
    }

    private function requireName(HitobitoGroup $externalGroup): string
    {
        $name = trim($externalGroup->name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new MiDataStructureConflictException('invalid_external_name', $externalGroup->id);
        }

        return $name;
    }
}
