<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ExternalStructureIdentityRepository;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Imports MiData sub-groups (Stufen and their sub-groups) of a mapped Abteilung as eMatChef groups. MiData is only a
 * source: nothing is written there and no people or group memberships are imported.
 *
 * No MiData token is stored. The OAuth callback loads the importable tree with a fresh token and keeps this
 * server-built snapshot briefly; the import only accepts external ids contained in that snapshot.
 */
final class MiDataGroupImportService
{
    public const PROVIDER = 'midata';
    public const SNAPSHOT_TTL_SECONDS = 900;
    private const MAX_DEPTH = 3;
    private const MAX_NODES = 300;
    private const ALLOWED_ROLES = ['mw', 'dc'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalStructureIdentityRepository $structureIdentities,
        private readonly ExternalIdentityRepository $externalIdentities,
        private readonly MiDataDepartmentMembershipVerifier $membershipVerifier,
        private readonly HitobitoApiClient $apiClient,
        private readonly PbsGroupTypeClassifier $classifier,
        private readonly CacheItemPoolInterface $cache,
    ) {}

    /** Mapped Abteilung group id when the user may import for this department, otherwise null. */
    public function resolveImportableDepartmentGroupId(User $user, Department $department): ?string
    {
        if ($this->externalIdentities->findOneBy(['user' => $user, 'provider' => self::PROVIDER]) === null) {
            return null;
        }
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);
        if (!$membership instanceof Membership || !\in_array(strtolower($membership->getRole()), self::ALLOWED_ROLES, true)) {
            return null;
        }

        $mappings = array_values(array_filter(
            $this->structureIdentities->findByDepartment($department),
            static fn (ExternalStructureIdentity $m): bool => $m->getProvider() === self::PROVIDER
                && $m->getDepartment() === $department
                && $m->getGroup() === null
                && $m->getOrganisation() === null,
        ));

        return count($mappings) === 1 && $mappings[0]->getExternalGroupId() !== '' ? $mappings[0]->getExternalGroupId() : null;
    }

    public function hasImportedGroups(Department $department): bool
    {
        return $this->structureIdentities->countGroupMappingsForDepartment(self::PROVIDER, $department) > 0;
    }

    /**
     * Loads the importable tree with the fresh token of the OAuth callback and keeps the snapshot.
     *
     * @return string ok|denied|unavailable
     */
    public function loadSnapshotFromOAuthCallback(User $user, Department $department, HitobitoOAuthSession $session): string
    {
        $rootId = $this->resolveImportableDepartmentGroupId($user, $department);
        if ($rootId === null) {
            return 'denied';
        }
        // The user's own active MiData role inside the mapped Abteilung must be confirmed with this token.
        $verification = $this->membershipVerifier->verify($user, $department, $session);
        if ($verification->status === MiDataDepartmentVerificationStatus::UNAVAILABLE) {
            return 'unavailable';
        }
        if ($verification->status !== MiDataDepartmentVerificationStatus::CONFIRMED) {
            return 'denied';
        }

        try {
            $nodes = [];
            $this->collect($session->accessToken, $rootId, $rootId, 1, $nodes);
        } catch (HitobitoApiException) {
            return 'unavailable';
        }

        $item = $this->cache->getItem($this->snapshotKey($user, $department));
        $item->set(['root' => $rootId, 'nodes' => $nodes])->expiresAfter(self::SNAPSHOT_TTL_SECONDS);
        $this->cache->save($item);

        return 'ok';
    }

    /**
     * Snapshot nodes (parent before child) with the internal group when already imported; null without a snapshot.
     *
     * @return list<array{external_group_id: string, parent_external_group_id: string, name: string, type: string, imported: bool, group_id: ?string}>|null
     */
    public function getTree(User $user, Department $department): ?array
    {
        $snapshot = $this->readSnapshot($user, $department);
        if ($snapshot === null) {
            return null;
        }

        $tree = [];
        foreach ($snapshot['nodes'] as $node) {
            $mapping = $this->structureIdentities->findOneByProviderAndExternalGroupId(self::PROVIDER, $node['external_group_id']);
            $tree[] = $node + [
                'imported' => $mapping?->getGroup() instanceof Group,
                'group_id' => $mapping?->getGroup()?->getId(),
            ];
        }

        return $tree;
    }

    /**
     * @param list<string> $externalGroupIds only references into the snapshot; unmapped ancestors are added
     *
     * @return array{status: string, imported: int, skipped: int}
     */
    public function import(User $user, Department $department, array $externalGroupIds): array
    {
        $snapshot = $this->readSnapshot($user, $department);
        if ($snapshot === null) {
            return ['status' => 'snapshot_missing', 'imported' => 0, 'skipped' => 0];
        }

        $byId = [];
        foreach ($snapshot['nodes'] as $node) {
            $byId[$node['external_group_id']] = $node;
        }
        $selected = [];
        foreach ($externalGroupIds as $id) {
            if (!is_string($id) || !isset($byId[$id])) {
                return ['status' => 'invalid_selection', 'imported' => 0, 'skipped' => 0];
            }
            for ($cursor = $id; isset($byId[$cursor]); $cursor = $byId[$cursor]['parent_external_group_id']) {
                $selected[$cursor] = true;
            }
        }

        return $this->entityManager->wrapInTransaction(function () use ($department, $snapshot, $selected): array {
            $this->structureIdentities->lockExternalGroupForStructureChange(self::PROVIDER, $snapshot['root']);

            $groupsByExternalId = [];
            $imported = 0;
            $skipped = 0;
            $sortOrder = 0;
            foreach ($snapshot['nodes'] as $node) {
                $externalId = $node['external_group_id'];
                if (!isset($selected[$externalId])) {
                    continue;
                }
                $existing = $this->structureIdentities->findOneByProviderAndExternalGroupId(self::PROVIDER, $externalId);
                if ($existing instanceof ExternalStructureIdentity) {
                    // An existing mapping is never overwritten or re-linked.
                    $group = $existing->getGroup();
                    if ($group instanceof Group && $group->getDepartmentId() === $department->getId()) {
                        $groupsByExternalId[$externalId] = $group;
                    }
                    ++$skipped;
                    continue;
                }

                $parentExternalId = $node['parent_external_group_id'];
                $parent = $groupsByExternalId[$parentExternalId] ?? null;
                if ($parentExternalId !== $snapshot['root'] && !$parent instanceof Group) {
                    ++$skipped; // parent could not be resolved safely; never attach to a guessed parent
                    continue;
                }

                $group = new Group();
                $group->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, Group::class, 'grp'));
                $group->setDepartment($department);
                $group->setName(mb_substr($node['name'], 0, 255));
                $group->setParent($parent);
                $group->setSortOrder(++$sortOrder);
                $this->entityManager->persist($group);

                $mapping = new ExternalStructureIdentity();
                $mapping->setId(IdGenerator::generateUnique($this->entityManager, ExternalStructureIdentity::class));
                $mapping->setProvider(self::PROVIDER);
                $mapping->setExternalGroupId($externalId);
                $mapping->setExternalType($node['type']);
                $mapping->setExternalName($node['name']);
                $mapping->setExternalParentId($parentExternalId);
                $mapping->setGroup($group);
                $this->entityManager->persist($mapping);

                $groupsByExternalId[$externalId] = $group;
                ++$imported;
            }
            $this->entityManager->flush();

            return ['status' => 'ok', 'imported' => $imported, 'skipped' => $skipped];
        });
    }

    /**
     * Only business group types (Stufen) are imported, recursively below imported ones; administrative or helper
     * groups (Gremien, Elternrat, …) are not classified as groups and are skipped.
     *
     * @param list<array{external_group_id: string, parent_external_group_id: string, name: string, type: string}> $nodes
     */
    private function collect(string $accessToken, string $rootId, string $parentId, int $depth, array &$nodes): void
    {
        foreach ($this->apiClient->getChildGroups(self::PROVIDER, $accessToken, $parentId) as $child) {
            if ($this->classifier->classify($child->type) !== PbsGroupTypeClassifier::GROUP || trim($child->name) === '') {
                continue;
            }
            if (count($nodes) >= self::MAX_NODES) {
                return;
            }
            $nodes[] = [
                'external_group_id' => $child->id,
                'parent_external_group_id' => $parentId,
                'name' => trim($child->name),
                'type' => $child->type,
            ];
            if ($depth < self::MAX_DEPTH) {
                $this->collect($accessToken, $rootId, $child->id, $depth + 1, $nodes);
            }
        }
    }

    /**
     * @return array{root: string, nodes: list<array{external_group_id: string, parent_external_group_id: string, name: string, type: string}>}|null
     */
    private function readSnapshot(User $user, Department $department): ?array
    {
        $rootId = $this->resolveImportableDepartmentGroupId($user, $department);
        if ($rootId === null) {
            return null;
        }
        $item = $this->cache->getItem($this->snapshotKey($user, $department));
        $snapshot = $item->isHit() ? $item->get() : null;

        return is_array($snapshot) && ($snapshot['root'] ?? null) === $rootId && is_array($snapshot['nodes'] ?? null)
            ? $snapshot
            : null;
    }

    private function snapshotKey(User $user, Department $department): string
    {
        return 'midata_group_import_' . $user->getId() . '_' . $department->getId();
    }
}
