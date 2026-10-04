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
        if ($candidateGroupId === '' || $expectedAncestorGroupId === '' || $accessToken === '') {
            return false;
        }

        $currentId = $candidateGroupId;
        $visited = [];
        for ($depth = 0; $depth <= $this->maxDepth; $depth++) {
            if ($currentId === $expectedAncestorGroupId) {
                return true;
            }
            if (isset($visited[$currentId]) || $depth === $this->maxDepth) {
                return false;
            }
            $visited[$currentId] = true;

            $group = $this->apiClient->getGroup($provider, $accessToken, $currentId);
            if ($group === null || $group->id !== $currentId || $group->parentId === null) {
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
}
