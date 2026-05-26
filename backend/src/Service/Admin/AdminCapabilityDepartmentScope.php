<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Erweitert Department-Wurzel-IDs um alle Nachkommen (Unter-Departments).
 */
final class AdminCapabilityDepartmentScope
{
    /** @var array<string, Department>|null */
    private ?array $departmentById = null;

    /** @var array<string, list<string>>|null */
    private ?array $childrenByParentId = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $rootDepartmentIds
     *
     * @return list<string>
     */
    public function expandSubtreeDepartmentIds(array $rootDepartmentIds): array
    {
        $roots = array_values(array_unique(array_filter(array_map('strval', $rootDepartmentIds))));
        if ($roots === []) {
            return [];
        }

        $this->ensureDepartmentMaps();

        $result = [];
        foreach ($roots as $rootId) {
            if (!isset($this->departmentById[$rootId])) {
                continue;
            }
            $stack = [$rootId];
            while ($stack !== []) {
                $current = array_pop($stack);
                if (isset($result[$current])) {
                    continue;
                }
                $result[$current] = true;
                foreach ($this->childrenByParentId[$current] ?? [] as $childId) {
                    $stack[] = $childId;
                }
            }
        }

        return array_keys($result);
    }

    /**
     * @param list<string>|null $organisationIds null = keine Org-Einschränkung
     *
     * @return list<string>
     */
    public function departmentIdsForOrganisations(?array $organisationIds): array
    {
        $qb = $this->entityManager->getRepository(Department::class)->createQueryBuilder('d');
        if ($organisationIds !== null && $organisationIds !== []) {
            $qb->where('d.organisationId IN (:orgIds)')->setParameter('orgIds', $organisationIds);
        }
        $rows = $qb->select('d.id')->getQuery()->getSingleColumnResult();

        return array_values(array_unique(array_map('strval', $rows)));
    }

    /**
     * @return list<string>
     */
    public function filterDepartmentIdsWithinOrganisations(array $departmentIds, array $organisationIds): array
    {
        if ($departmentIds === [] || $organisationIds === []) {
            return [];
        }

        $allowedOrgs = array_flip($organisationIds);
        $this->ensureDepartmentMaps();
        $filtered = [];
        foreach ($departmentIds as $id) {
            $dept = $this->departmentById[$id] ?? null;
            if ($dept && isset($allowedOrgs[$dept->getOrganisationId()])) {
                $filtered[] = $id;
            }
        }

        return $filtered;
    }

    private function ensureDepartmentMaps(): void
    {
        if ($this->departmentById !== null) {
            return;
        }

        /** @var Department[] $all */
        $all = $this->entityManager->getRepository(Department::class)->findAll();
        $this->departmentById = [];
        $this->childrenByParentId = [];
        foreach ($all as $department) {
            $id = $department->getId();
            $this->departmentById[$id] = $department;
            $parentId = $department->getParentId();
            if ($parentId !== null && $parentId !== '') {
                $this->childrenByParentId[$parentId][] = $id;
            }
        }
    }
}
