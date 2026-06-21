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
     * Erweitert Root-Gruppen um alle übergeordneten Gruppen (rekursiv bis Wurzel).
     *
     * @param list<string> $rootGroupIds
     *
     * @return list<string>
     */
    public function expandWithAncestors(string $departmentId, array $rootGroupIds): array
    {
        $rootGroupIds = array_values(array_unique(array_filter($rootGroupIds)));
        if ($rootGroupIds === []) {
            return [];
        }

        $groups = $this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $departmentId]);
        $parentById = [];
        foreach ($groups as $group) {
            $parentById[$group->getId()] = $group->getParentId();
        }

        $result = [];
        foreach ($rootGroupIds as $id) {
            $current = $id;
            $seen = [];
            while ($current !== null && $current !== '' && !isset($seen[$current])) {
                $seen[$current] = true;
                $result[] = $current;
                $current = $parentById[$current] ?? null;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Liegt die Aktivitäts-Gruppe in der Unterhierarchie einer User-Gruppe?
     * (User in Parent sieht Child-Aktivitäten.)
     *
     * @param list<string> $userRootGroupIds
     */
    public function isActivityGroupUnderUserGroups(string $departmentId, string $activityGroupId, array $userRootGroupIds): bool
    {
        $expanded = $this->expandWithDescendants($departmentId, $userRootGroupIds);

        return in_array($activityGroupId, $expanded, true);
    }

    /**
     * Liegt eine User-Gruppe in der Unterhierarchie der Aktivitäts-Gruppe?
     * (User in Untergruppe sieht Parent-Aktivitäten, z. B. Lager/Event der Gruppe darüber.)
     *
     * @param list<string> $userRootGroupIds
     */
    public function isUserGroupUnderActivityGroup(string $departmentId, string $activityGroupId, array $userRootGroupIds): bool
    {
        if ($userRootGroupIds === []) {
            return false;
        }

        $activityBranch = $this->expandWithDescendants($departmentId, [$activityGroupId]);
        foreach ($userRootGroupIds as $userRootId) {
            if (in_array($userRootId, $activityBranch, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gleicher Gruppenzweig: Aktivität in User-Unterbaum oder User in Aktivitäts-Unterbaum.
     *
     * @param list<string> $userRootGroupIds
     */
    public function isInSameGroupBranch(string $departmentId, string $activityGroupId, array $userRootGroupIds): bool
    {
        return $this->isActivityGroupUnderUserGroups($departmentId, $activityGroupId, $userRootGroupIds)
            || $this->isUserGroupUnderActivityGroup($departmentId, $activityGroupId, $userRootGroupIds);
    }

    /**
     * Tiefe eines Knotens (Wurzel = 1).
     */
    public function computeDepth(string $departmentId, string $groupId): int
    {
        return count($this->expandWithAncestors($departmentId, [$groupId]));
    }

    /**
     * Maximale Tiefe im Subtree (Wurzel des Subtrees = 1).
     */
    public function computeMaxSubtreeDepth(string $departmentId, string $rootGroupId): int
    {
        $descendants = $this->expandWithDescendants($departmentId, [$rootGroupId]);
        $max = 0;
        foreach ($descendants as $id) {
            $max = max($max, $this->computeDepth($departmentId, $id));
        }

        return $max;
    }
}
