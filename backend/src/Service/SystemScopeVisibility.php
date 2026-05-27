<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Department;
use App\Entity\Organisation;
use App\Service\Bootstrap\GlobalSystemSeedDefaults;

/**
 * System-Organisation (Global System) und Global Suppliers sind technische Entitäten —
 * keine User-Zuordnung in Admin-UI.
 */
final class SystemScopeVisibility
{
    /** @var list<string> */
    private const HIDDEN_ORGANISATION_IDS = [
        GlobalSystemSeedDefaults::ORGANISATION_ID,
        'org_js000000',
    ];

    public static function isOrganisationVisibleForAssignment(Organisation $org): bool
    {
        return OrganisationUserPickerFilter::isVisibleForUserPickers($org);
    }

    public static function isOrganisationIdVisibleForAssignment(string $organisationId): bool
    {
        if (\in_array($organisationId, self::HIDDEN_ORGANISATION_IDS, true)) {
            return false;
        }

        return true;
    }

    public static function isDepartmentVisibleForAssignment(Department $department): bool
    {
        if ($department->getId() === GlobalSystemSeedDefaults::DEPARTMENT_ID) {
            return false;
        }

        $orgId = $department->getOrganisationId();
        if ($orgId !== null && !self::isOrganisationIdVisibleForAssignment($orgId)) {
            return false;
        }

        $name = mb_strtolower($department->getName());
        if (str_contains($name, 'global suppliers')) {
            return false;
        }

        return true;
    }

    public static function isDepartmentIdVisibleForAssignment(string $departmentId): bool
    {
        if ($departmentId === GlobalSystemSeedDefaults::DEPARTMENT_ID) {
            return false;
        }

        return true;
    }

    /**
     * @param list<string> $organisationIds
     *
     * @return list<string>
     */
    public static function filterOrganisationIds(array $organisationIds): array
    {
        return array_values(array_filter(
            $organisationIds,
            static fn (string $id): bool => self::isOrganisationIdVisibleForAssignment($id)
        ));
    }

    /**
     * @param list<string> $departmentIds
     *
     * @return list<string>
     */
    public static function filterDepartmentIds(array $departmentIds): array
    {
        return array_values(array_filter(
            $departmentIds,
            static fn (string $id): bool => self::isDepartmentIdVisibleForAssignment($id)
        ));
    }
}
