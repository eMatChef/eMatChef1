<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;

final class DepartmentBreadcrumbBuilder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{type: string, name: string, id?: string, current?: bool}>
     */
    public function buildForDepartment(Department $department, bool $markCurrent = true): array
    {
        $map = $this->loadDepartmentMapForOrganisation($department->getOrganisationId());

        return $this->buildFromMap($department, $map, $markCurrent);
    }

    /**
     * @param array<string, Department> $departmentMap
     *
     * @return list<array{type: string, name: string, id?: string, current?: bool}>
     */
    public function buildFromMap(Department $department, array $departmentMap, bool $markCurrent = true): array
    {
        $segments = [
            [
                'type' => 'organisation',
                'name' => $department->getOrganisation()->getName(),
            ],
        ];

        $ancestors = [];
        $visited = [];
        $parentId = $department->getParentId();
        while ($parentId !== null && $parentId !== '' && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $parent = $departmentMap[$parentId] ?? null;
            if (!$parent instanceof Department) {
                break;
            }
            $ancestors[] = [
                'type' => 'department',
                'name' => $parent->getName(),
                'id' => $parent->getId(),
            ];
            $parentId = $parent->getParentId();
        }

        foreach (array_reverse($ancestors) as $ancestor) {
            $segments[] = $ancestor;
        }

        $segments[] = [
            'type' => 'department',
            'name' => $department->getName(),
            'id' => $department->getId(),
            'current' => $markCurrent,
        ];

        return $segments;
    }

    /**
     * @return array<string, Department>
     */
    public function loadDepartmentMapForOrganisation(string $organisationId): array
    {
        $departments = $this->entityManager->getRepository(Department::class)->findBy([
            'organisationId' => $organisationId,
        ]);

        $map = [];
        foreach ($departments as $department) {
            if ($department instanceof Department) {
                $map[$department->getId()] = $department;
            }
        }

        return $map;
    }

    /**
     * @param list<Department> $departments
     *
     * @return array<string, array<string, Department>>
     */
    public function loadDepartmentMapsByOrganisation(array $departments): array
    {
        $maps = [];
        foreach ($departments as $department) {
            if (!$department instanceof Department) {
                continue;
            }
            $orgId = $department->getOrganisationId();
            if (!isset($maps[$orgId])) {
                $maps[$orgId] = $this->loadDepartmentMapForOrganisation($orgId);
            }
        }

        return $maps;
    }
}
