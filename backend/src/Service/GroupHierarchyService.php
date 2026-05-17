<?php

namespace App\Service;

use App\Entity\Group;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Hierarchie-Operationen für Gruppen innerhalb eines Departments.
 */
class GroupHierarchyService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Erweitert Root-Gruppen um alle Untergruppen (rekursiv).
     *
     * @param list<string> $rootGroupIds
     *
     * @return list<string>
     */
    public function expandWithDescendants(string $departmentId, array $rootGroupIds): array
    {
        $rootGroupIds = array_values(array_unique(array_filter($rootGroupIds)));
        if ($rootGroupIds === []) {
            return [];
        }

        $groups = $this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $departmentId]);
        $childrenByParent = [];
        foreach ($groups as $group) {
            $parentId = $group->getParentId();
            if ($parentId) {
                $childrenByParent[$parentId][] = $group->getId();
            }
        }

        $result = [];
        $queue = $rootGroupIds;
        $seen = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $result[] = $id;
            foreach ($childrenByParent[$id] ?? [] as $childId) {
                if (!isset($seen[$childId])) {
                    $queue[] = $childId;
                }
            }
        }

        return $result;
    }

    /**
     * Liegt die Aktivitäts-Gruppe in der Unterhierarchie einer User-Gruppe?
     *
     * @param list<string> $userRootGroupIds
     */
    public function isActivityGroupUnderUserGroups(string $departmentId, string $activityGroupId, array $userRootGroupIds): bool
    {
        $expanded = $this->expandWithDescendants($departmentId, $userRootGroupIds);

        return in_array($activityGroupId, $expanded, true);
    }
}
