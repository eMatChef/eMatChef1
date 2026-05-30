<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Department;
use App\Entity\Organisation;

/**
 * System-Organisationen/Departments ohne User-Zuordnung in Admin-UI ausblenden (z. B. J&S).
 */
final class SystemScopeVisibility
{
    /** @var list<string> */
    private const HIDDEN_ORGANISATION_IDS = [
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
        $orgId = $department->getOrganisationId();
        if ($orgId !== null && !self::isOrganisationIdVisibleForAssignment($orgId)) {
            return false;
        }

        $name = mb_strtolower($department->getName());
        if (str_contains($name, 'global suppliers') || str_contains($name, 'global system')) {
            return false;
        }
        if (str_contains($name, 'j&s') || str_contains($name, 'j+s') || str_contains($name, 'leih-material')) {
            return false;
        }

        return true;
    }

    public static function isDepartmentIdVisibleForAssignment(string $departmentId): bool
    {
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
