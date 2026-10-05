<?php

declare(strict_types=1);

namespace App\Service\Auth;

/**
 * Confirms an active direct supported PBS role ({@see MiDataSupportedRoleCatalog}: Materialwart, Abteilungsleitung)
 * for exactly one MiData Abteilung and resolves its structure Abteilung → Region* → Kantonalverband → Bund (→ Root, ignored).
 *
 * Only the Hitobito JSON:API is trusted here: userinfo role claims, role/group names
 * and browser input are never authorization inputs.
 */
class MiDataMaterialwartVerifier
{
    public const MATERIALWART_ROLE_CLASS = MiDataSupportedRoleCatalog::MATERIALWART;
    public const DEPARTMENT_GROUP_TYPE = 'Group::Abteilung';
    public const REGION_GROUP_TYPE = 'Group::Region';
    public const KANTONALVERBAND_GROUP_TYPE = 'Group::Kantonalverband';
    public const BUND_GROUP_TYPE = 'Group::Bund';
    public const ROOT_GROUP_TYPE = 'Group::Root';

    public function __construct(
        private readonly HitobitoRoleLookup $roleLookup,
        private readonly HitobitoGroupLookup $groupLookup,
        private readonly HitobitoParentChainResolver $parentChainResolver,
    ) {}

    /**
     * Loads the person's roles once so several candidates can be verified with a single /api/roles pagination.
     *
     * @return list<HitobitoRole>
     *
     * @throws HitobitoApiException
     */
    public function loadRoles(HitobitoOAuthSession $session, string $externalPersonId): array
    {
        if (
            $session->provider !== 'midata'
            || $session->accessToken === ''
            || $externalPersonId === ''
            || !hash_equals($externalPersonId, $session->userInfo->subject)
        ) {
            throw new HitobitoApiException('invalid_session', 'MiData session does not match the person');
        }

        return $this->roleLookup->getRolesForPerson('midata', $session->accessToken, $externalPersonId);
    }

    /**
     * The verified role is the highest-priority active supported role in that group; a stored offer's role class
     * is only intent. With $requiredRoleClass, exactly that role must also be active (search-mode selection).
     *
     * @param list<HitobitoRole>|null $roles roles from {@see loadRoles()} of the same session; loaded here when null
     */
    public function verify(
        HitobitoOAuthSession $session,
        string $externalPersonId,
        string $externalDepartmentGroupId,
        ?\DateTimeImmutable $today = null,
        ?array $roles = null,
        ?string $requiredRoleClass = null,
    ): MiDataMaterialwartVerification {
        if (
            $session->provider !== 'midata'
            || $session->accessToken === ''
            || $externalPersonId === ''
            || $externalDepartmentGroupId === ''
            || !hash_equals($externalPersonId, $session->userInfo->subject)
        ) {
            return new MiDataMaterialwartVerification(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED);
        }

        $today ??= new \DateTimeImmutable('today');
        try {
            $activeRoles = $this->findActiveSupportedRoles(
                $roles ?? $this->roleLookup->getRolesForPerson('midata', $session->accessToken, $externalPersonId),
                $externalPersonId,
                $externalDepartmentGroupId,
                $today,
            );
            $role = $activeRoles[0] ?? null;
            if (
                $role === null
                || ($requiredRoleClass !== null && !in_array(
                    $requiredRoleClass,
                    array_map(static fn (HitobitoRole $active): string => $active->type, $activeRoles),
                    true,
                ))
            ) {
                return new MiDataMaterialwartVerification(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED);
            }

            $department = $this->groupLookup->getGroup('midata', $session->accessToken, $externalDepartmentGroupId);
            if (
                $department === null
                || $department->id !== $externalDepartmentGroupId
                || $department->type !== MiDataSupportedRoleCatalog::expectedGroupType($role->type)
            ) {
                return new MiDataMaterialwartVerification(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED);
            }

            $chain = $this->parentChainResolver->getParentChain(
                'midata',
                $externalDepartmentGroupId,
                $session->accessToken,
                $department,
            );
        } catch (HitobitoApiException) {
            return new MiDataMaterialwartVerification(MiDataMaterialwartVerificationStatus::UNAVAILABLE);
        }

        $structure = $this->parseStructure($chain);
        if ($structure === null) {
            return new MiDataMaterialwartVerification(MiDataMaterialwartVerificationStatus::UNSUPPORTED_STRUCTURE);
        }

        return new MiDataMaterialwartVerification(
            MiDataMaterialwartVerificationStatus::CONFIRMED,
            $externalPersonId,
            $department,
            $structure['regions'],
            $structure['kantonalverband'],
            $structure['bund'],
            $role,
        );
    }

    /**
     * Expects [Abteilung, Region*, Kantonalverband, Bund, Root?] from the Abteilung upwards.
     *
     * @param list<HitobitoGroup> $chain
     *
     * @return array{regions: list<HitobitoGroup>, kantonalverband: HitobitoGroup, bund: HitobitoGroup}|null
     */
    private function parseStructure(array $chain): ?array
    {
        $index = 1;
        $regionsBottomUp = [];
        while (isset($chain[$index]) && $chain[$index]->type === self::REGION_GROUP_TYPE) {
            $regionsBottomUp[] = $chain[$index];
            $index++;
        }

        $kantonalverband = $chain[$index] ?? null;
        $bund = $chain[$index + 1] ?? null;
        if (
            $kantonalverband?->type !== self::KANTONALVERBAND_GROUP_TYPE
            || $bund?->type !== self::BUND_GROUP_TYPE
        ) {
            return null;
        }
        foreach (array_slice($chain, $index + 2) as $ancestor) {
            if ($ancestor->type !== self::ROOT_GROUP_TYPE) {
                return null;
            }
        }

        return [
            'regions' => array_reverse($regionsBottomUp),
            'kantonalverband' => $kantonalverband,
            'bund' => $bund,
        ];
    }

    public static function isMaterialwartRole(HitobitoRole $role): bool
    {
        return $role->type === self::MATERIALWART_ROLE_CLASS;
    }

    public static function isSupportedRole(HitobitoRole $role): bool
    {
        return MiDataSupportedRoleCatalog::isSupported($role->type);
    }

    /**
     * Active supported roles of the person in exactly this group, best priority first.
     *
     * @param list<HitobitoRole> $roles
     *
     * @return list<HitobitoRole>
     */
    private function findActiveSupportedRoles(
        array $roles,
        string $externalPersonId,
        string $externalDepartmentGroupId,
        \DateTimeImmutable $today,
    ): array {
        $active = array_values(array_filter(
            $roles,
            static fn (HitobitoRole $role): bool => $role->personId === $externalPersonId
                && $role->groupId === $externalDepartmentGroupId
                && self::isSupportedRole($role)
                && $role->isActiveOn($today),
        ));
        usort($active, static fn (HitobitoRole $a, HitobitoRole $b): int => MiDataSupportedRoleCatalog::priority($a->type)
            <=> MiDataSupportedRoleCatalog::priority($b->type));

        return $active;
    }
}
