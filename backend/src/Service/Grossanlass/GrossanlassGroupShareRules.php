<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

final class GrossanlassGroupShareRules
{
    /**
     * @param list<string> $sourceBranchIds Quelle inklusive Nachfahren
     * @param list<string> $targetBranchIds Ziel inklusive Nachfahren
     */
    public static function forbiddenReason(
        string $sourceId,
        string $targetId,
        array $sourceBranchIds,
        array $targetBranchIds,
        bool $targetIsBauprojekt,
    ): ?string {
        if ($sourceId === $targetId) {
            return 'Kann nicht mit sich selbst teilen';
        }
        if ($targetIsBauprojekt) {
            return 'Ziel muss ein Ressort oder Bereich sein';
        }
        if (in_array($targetId, $sourceBranchIds, true)) {
            return 'Kann nicht in den eigenen Zweig teilen';
        }
        if (in_array($sourceId, $targetBranchIds, true)) {
            return 'Kann nicht mit einem Unterknoten teilen';
        }

        return null;
    }
}
