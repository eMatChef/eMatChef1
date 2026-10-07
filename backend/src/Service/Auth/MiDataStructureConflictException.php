<?php

declare(strict_types=1);

namespace App\Service\Auth;

/**
 * Existing eMatChef data does not fit the verified external structure; nothing is repaired automatically.
 */
final class MiDataStructureConflictException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $externalGroupId,
        string $message = 'MiData structure conflicts with existing eMatChef data',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
