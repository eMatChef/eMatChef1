<?php

declare(strict_types=1);

namespace App\Service\Print;

final class MaterialQrExportRow
{
    public function __construct(
        public readonly string $materialName,
        public readonly string $lineLabel,
        public readonly string $publicCode,
        public readonly string $publicUrl,
        public readonly string $batchId = '',
    ) {
    }
}
