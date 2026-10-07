<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\ExternalStructureIdentity;

final class HitobitoParentChainResolver
{
    public const MAX_DEPTH = 64;

    public function __construct(
        private readonly HitobitoGroupLookup $apiClient,
        private readonly int $maxDepth = self::MAX_DEPTH,
    ) {
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException('Maximum Hitobito parent-chain depth must be positive');
        }
    }

    public function isDescendantOrSelf(
        string $provider,
        string $candidateGroupId,
        string $expectedAncestorGroupId,
        string $accessToken,
    ): bool {
        return $this->resolve($provider, $candidateGroupId, $expectedAncestorGroupId, $accessToken, false);
    }

    public function isDescendantOrSelfForVerification(
        string $provider,
        string $candidateGroupId,
        string $expectedAncestorGroupId,
        string $accessToken,
    ): bool {
        return $this->resolve($provider, $candidateGroupId, $expectedAncestorGroupId, $accessToken, true);
    }

    private function resolve(
        string $provider,
        string $candidateGroupId,
        string $expectedAncestorGroupId,
        string $accessToken,
        bool $strict,
    ): bool {
        if ($candidateGroupId === '' || $expectedAncestorGroupId === '' || $accessToken === '') {
            if ($strict) {
                throw new HitobitoApiException('hierarchy_unavailable', 'Hitobito hierarchy inputs are missing');
            }

            return false;
        }

        $currentId = $candidateGroupId;
        $visited = [];
        for ($depth = 0; $depth <= $this->maxDepth; $depth++) {
            if ($currentId === $expectedAncestorGroupId) {
                return true;
            }
            if (isset($visited[$currentId])) {
                if ($strict) {
                    throw new HitobitoApiException('hierarchy_cycle', 'Hitobito hierarchy contains a cycle');
                }

                return false;
            }
            if ($depth === $this->maxDepth) {
                if ($strict) {
                    throw new HitobitoApiException('hierarchy_depth_limit', 'Hitobito hierarchy exceeded the safety limit');
                }

                return false;
            }
            $visited[$currentId] = true;

            $group = $this->apiClient->getGroup($provider, $accessToken, $currentId);
            if ($group === null) {
                if ($strict) {
                    throw new HitobitoApiException('group_not_found', 'A required Hitobito group could not be loaded', 404);
                }

                return false;
            }
            if ($group->id !== $currentId) {
                if ($strict) {
                    throw new HitobitoApiException('malformed_hierarchy', 'Hitobito returned a different group id');
                }

                return false;
            }
            if ($group->parentId === null) {
                return false;
            }
            $currentId = $group->parentId;
        }

        return false;
    }

    public function isDescendantOrSelfOfMapping(
        string $provider,
        string $candidateGroupId,
        string $accessToken,
        ExternalStructureIdentity $mapping,
    ): bool {
        if ($mapping->getProvider() !== $provider) {
            return false;
        }

        return $this->isDescendantOrSelf(
            $provider,
            $candidateGroupId,
            $mapping->getExternalGroupId(),
            $accessToken,
        );
    }

    /**
     * @return list<HitobitoGroup>
     */
    public function getParentChain(
        string $provider,
        string $candidateGroupId,
        string $accessToken,
        ?HitobitoGroup $initialGroup = null,
    ): array {
        if ($candidateGroupId === '' || $accessToken === '') {
            throw new HitobitoApiException('hierarchy_unavailable', 'Hitobito hierarchy inputs are missing');
        }
        if ($initialGroup !== null && $initialGroup->id !== $candidateGroupId) {
            throw new HitobitoApiException('malformed_hierarchy', 'Initial Hitobito group does not match the requested id');
        }

        $chain = [];
        $visited = [];
        $currentId = $candidateGroupId;
        for ($depth = 0; $depth < $this->maxDepth; $depth++) {
            if (isset($visited[$currentId])) {
                throw new HitobitoApiException('hierarchy_cycle', 'Hitobito hierarchy contains a cycle');
            }
            $visited[$currentId] = true;
            $group = $depth === 0 && $initialGroup !== null
                ? $initialGroup
                : $this->apiClient->getGroup($provider, $accessToken, $currentId);
            if ($group === null) {
                throw new HitobitoApiException('group_not_found', 'A required Hitobito group could not be loaded', 404);
            }
            if ($group->id !== $currentId) {
                throw new HitobitoApiException('malformed_hierarchy', 'Hitobito returned a different group id');
            }

            $chain[] = $group;
            if ($group->parentId === null) {
                return $chain;
            }
            $currentId = $group->parentId;
        }

        throw new HitobitoApiException('hierarchy_depth_limit', 'Hitobito hierarchy exceeded the safety limit');
    }
}
