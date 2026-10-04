<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Repository\ExternalStructureIdentityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class MiDataGroupMembershipSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalStructureIdentityRepository $structureIdentities,
        private readonly LoggerInterface $logger,
    ) {}

    public function sync(
        User $user,
        Department $department,
        MiDataDepartmentVerificationResult $verification,
    ): MiDataGroupMembershipSyncResult {
        if ($verification->status !== MiDataDepartmentVerificationStatus::CONFIRMED) {
            return new MiDataGroupMembershipSyncResult();
        }

        $departmentId = $department->getId();
        $personId = $verification->externalPersonId;
        $departmentGroupId = $verification->externalDepartmentGroupId;
        if ($departmentId === null || $personId === null || $departmentGroupId === null || $personId === '') {
            $this->logger->error('MiData group membership sync received incomplete verified department data', [
                'user_id' => $user->getId(),
                'department_id' => $departmentId,
            ]);

            return new MiDataGroupMembershipSyncResult(errors: ['incomplete_verification']);
        }

        $today = new \DateTimeImmutable('today');
        $rolesByExternalGroupId = [];
        $conflicts = [];
        foreach ($verification->verifiedRoles as $role) {
            if ($role->personId !== $personId || !$role->isActiveOn($today)) {
                $conflicts[] = $role->groupId;
                $this->logger->warning('MiData verified role did not match its verified person or active period', [
                    'user_id' => $user->getId(),
                    'department_id' => $departmentId,
                    'external_group_id' => $role->groupId,
                ]);
                continue;
            }
            if ($role->groupId === $departmentGroupId) {
                continue;
            }

            $rolesByExternalGroupId[$role->groupId] ??= $role;
        }

        $created = [];
        $existing = [];
        $unmapped = [];
        $processedGroupIds = [];
        foreach ($rolesByExternalGroupId as $externalGroupIdValue => $_role) {
            $externalGroupId = (string) $externalGroupIdValue;
            $mapping = $this->structureIdentities->findOneByProviderAndExternalGroupId('midata', $externalGroupId);
            if (
                $mapping === null
                || $mapping->getProvider() !== 'midata'
                || $mapping->getExternalGroupId() !== $externalGroupId
                || $mapping->getGroup() === null
            ) {
                $unmapped[] = $externalGroupId;
                continue;
            }

            $group = $mapping->getGroup();
            $groupId = $group->getId();
            if (
                !$mapping->hasExactlyOneInternalTarget()
                || $groupId === null
                || $group->getDepartmentId() !== $departmentId
            ) {
                $conflicts[] = $externalGroupId;
                $this->logger->error('MiData group mapping conflicts with the verified department', [
                    'user_id' => $user->getId(),
                    'department_id' => $departmentId,
                    'external_group_id' => $externalGroupId,
                    'mapped_group_id' => $groupId,
                    'mapped_department_id' => $group->getDepartmentId(),
                ]);
                continue;
            }
            if (isset($processedGroupIds[$groupId])) {
                continue;
            }
            $processedGroupIds[$groupId] = true;

            $membership = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
                'userId' => $user->getId(),
                'groupId' => $groupId,
            ]);
            if ($membership instanceof GroupMembership) {
                $existing[] = $groupId;
                continue;
            }

            $membership = new GroupMembership();
            $membership->setUser($user);
            $membership->setGroup($group);
            $membership->setRole('member');
            $membership->setCanProcure(false);
            $membership->setIsPrimary(false);
            $this->entityManager->persist($membership);
            $created[] = $groupId;
        }

        if ($created !== []) {
            $this->entityManager->flush();
        }

        $result = new MiDataGroupMembershipSyncResult(
            $created,
            $existing,
            array_values(array_unique($unmapped)),
            array_values(array_unique($conflicts)),
        );
        $this->logger->info('MiData group membership sync completed', [
            'user_id' => $user->getId(),
            'department_id' => $departmentId,
            'created_group_ids' => $result->created,
            'existing_group_ids' => $result->existing,
            'unmapped_external_group_ids' => $result->unmapped,
            'conflicting_external_group_ids' => $result->conflicts,
        ]);

        return $result;
    }
}
