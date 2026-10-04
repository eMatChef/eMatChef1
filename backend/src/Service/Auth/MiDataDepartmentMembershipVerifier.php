<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ExternalStructureIdentityRepository;

class MiDataDepartmentMembershipVerifier
{
    public function __construct(
        private readonly ExternalIdentityRepository $externalIdentities,
        private readonly ExternalStructureIdentityRepository $structureIdentities,
        private readonly HitobitoRoleLookup $roleLookup,
        private readonly HitobitoParentChainResolver $parentChainResolver,
    ) {}

    public function verify(
        User $user,
        Department $department,
        ?HitobitoOAuthSession $session,
    ): MiDataDepartmentVerificationResult {
        $identity = $this->externalIdentities->findOneBy([
            'user' => $user,
            'provider' => 'midata',
        ]);
        if ($identity === null) {
            return new MiDataDepartmentVerificationResult(MiDataDepartmentVerificationStatus::NOT_APPLICABLE);
        }

        $mappings = array_values(array_filter(
            $this->structureIdentities->findByDepartment($department),
            static fn ($mapping): bool => $mapping->getProvider() === 'midata'
                && $mapping->getDepartment() === $department
                && $mapping->getGroup() === null
                && $mapping->getOrganisation() === null,
        ));
        if ($mappings === []) {
            return new MiDataDepartmentVerificationResult(MiDataDepartmentVerificationStatus::NOT_APPLICABLE);
        }
        if (count($mappings) !== 1 || $mappings[0]->getExternalGroupId() === '') {
            return new MiDataDepartmentVerificationResult(MiDataDepartmentVerificationStatus::UNAVAILABLE);
        }

        if (
            $session === null
            || $session->provider !== 'midata'
            || $session->accessToken === ''
            || !hash_equals($identity->getExternalUserId(), $session->userInfo->subject)
        ) {
            return new MiDataDepartmentVerificationResult(MiDataDepartmentVerificationStatus::UNAVAILABLE);
        }

        try {
            $roles = $this->roleLookup->getRolesForPerson('midata', $session->accessToken, $identity->getExternalUserId());
            $today = new \DateTimeImmutable('today');
            foreach ($roles as $role) {
                if ($role->personId !== $identity->getExternalUserId() || !$role->isActiveOn($today)) {
                    continue;
                }
                if (
                    $role->groupId === $mappings[0]->getExternalGroupId()
                    || $this->parentChainResolver->isDescendantOrSelfForVerification(
                        'midata',
                        $role->groupId,
                        $mappings[0]->getExternalGroupId(),
                        $session->accessToken,
                    )
                ) {
                    return new MiDataDepartmentVerificationResult(
                        MiDataDepartmentVerificationStatus::CONFIRMED,
                        $role,
                    );
                }
            }
        } catch (HitobitoApiException) {
            return new MiDataDepartmentVerificationResult(MiDataDepartmentVerificationStatus::UNAVAILABLE);
        }

        return new MiDataDepartmentVerificationResult(MiDataDepartmentVerificationStatus::NOT_CONFIRMED);
    }
}
