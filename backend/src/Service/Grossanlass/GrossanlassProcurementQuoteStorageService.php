<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\Department;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Offerten-PDF unter var/uploads/grossanlass-procurement-quote/{departmentId}/{quoteId}/.
 */
class GrossanlassProcurementQuoteStorageService
{
    public function __construct(
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array{filename: string, url: string, bytes: int, original_filename: string}
     */
    public function store(
        Department $department,
        ActivityGrossanlassProcurementQuote $quote,
        User $user,
        UploadedFile $file,
    ): array {
        $departmentId = $department->getId() ?? '';
        $quoteId = $quote->getId();

        $stored = $this->mediaStorage->storeAttachment(
            MediaStorageService::CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE,
            $quoteId,
            $departmentId,
            $user,
            $file,
            [
                'url_builder' => fn (string $filename): string => $this->buildPdfUrl(
                    $departmentId,
                    $quoteId,
                    $filename,
                ),
            ],
        );

        return [
            'filename' => $stored['filename'],
            'url' => $stored['url'],
            'bytes' => $stored['bytes'],
            'original_filename' => $stored['original_filename'],
        ];
    }

    public function resolveFilePath(string $departmentId, string $quoteId, string $filename): string
    {
        $this->mediaStorage->assertSafePathSegment($departmentId);
        $this->mediaStorage->assertSafePathSegment($quoteId);
        $this->mediaStorage->assertSafeFilename($filename);

        $path = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE,
            $departmentId,
            $quoteId,
        ) . '/' . $filename;

        if (!is_file($path)) {
            throw new \InvalidArgumentException('PDF nicht gefunden');
        }

        return $path;
    }

    public function deleteFile(string $departmentId, string $quoteId, string $filename): void
    {
        try {
            $path = $this->resolveFilePath($departmentId, $quoteId, $filename);
            @unlink($path);
        } catch (\InvalidArgumentException) {
            // bereits gelöscht
        }
    }

    public function buildPdfUrl(string $departmentId, string $quoteId, string $filename): string
    {
        return sprintf(
            '/api/departments/%s/grossanlass/beschaffung/quotes/%s/pdf/%s',
            rawurlencode($departmentId),
            rawurlencode($quoteId),
            rawurlencode($filename),
        );
    }
}
