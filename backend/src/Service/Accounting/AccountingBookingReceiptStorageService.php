<?php

declare(strict_types=1);

namespace App\Service\Accounting;

use App\Entity\AccountingBooking;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Beleg-Dateien unter var/uploads/accounting/{departmentId}/{bookingId}/.
 */
class AccountingBookingReceiptStorageService
{
    public const MAX_RECEIPTS = 5;

    public function __construct(
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function store(AccountingBooking $booking, User $user, UploadedFile $file): array
    {
        $bookingId = (string) $booking->getId();
        $departmentId = $booking->getDepartment()->getId() ?? '';

        return $this->mediaStorage->storeAttachment(
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
            $bookingId,
            $departmentId,
            $user,
            $file,
            [
                'url_builder' => fn (string $filename): string => $this->buildReceiptUrl(
                    $departmentId,
                    $bookingId,
                    $filename,
                ),
            ],
        );
    }

    public function resolveFilePath(string $departmentId, string $bookingId, string $filename): string
    {
        $this->mediaStorage->assertSafePathSegment($departmentId);
        $this->mediaStorage->assertSafePathSegment($bookingId);
        $this->mediaStorage->assertSafeFilename($filename);

        $path = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
            $departmentId,
            $bookingId,
        ) . '/' . $filename;

        if (!is_file($path)) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }

        return $path;
    }

    public function deleteAllForBooking(AccountingBooking $booking): void
    {
        $this->mediaStorage->deleteContextFolder(
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
            $booking->getDepartment()->getId() ?? '',
            (string) $booking->getId(),
        );
    }

    public function buildReceiptUrl(string $departmentId, string $bookingId, string $filename): string
    {
        return sprintf(
            '/api/departments/%s/accounting/bookings/%s/receipts/%s',
            rawurlencode($departmentId),
            rawurlencode($bookingId),
            rawurlencode($filename),
        );
    }
}
