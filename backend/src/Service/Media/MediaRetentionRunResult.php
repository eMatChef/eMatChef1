<?php

declare(strict_types=1);

namespace App\Service\Media;

/**
 * Ergebnis eines Retention-Laufs (Dry-run oder Live).
 */
final class MediaRetentionRunResult
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public int $ticketsMatched,
        public int $ticketsProcessed,
        public int $issueReportsProcessed,
        public int $filesDeleted,
        public int $bytesFreed,
        public array $items,
    ) {
    }

    public function megabytesFreed(): float
    {
        return round($this->bytesFreed / 1024 / 1024, 2);
    }
}
