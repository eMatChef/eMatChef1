<?php

declare(strict_types=1);

namespace App\Service\Accounting;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Beleg-Dateien unter var/uploads/accounting-followup/{departmentId}/{followUpId}/.
 */
class AccountingAcquisitionFollowUpReceiptStorageService
{
    public const MAX_RECEIPTS = 5;

    public function __construct(
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function store(AccountingAcquisitionFollowUp $followUp, User $user, UploadedFile $file): array
    {
        $followUpId = (string) $followUp->getId();
        $departmentId = $followUp->getDepartment()->getId() ?? '';

        return $this->mediaStorage->storeAttachment(
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
            $followUpId,
            $departmentId,
            $user,
            $file,
            [],
        );
    }

    public function resolveFilePath(string $departmentId, string $followUpId, string $filename): string
    {
        return $this->mediaStorage->resolveStoredFilePath(
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
            $departmentId,
            $followUpId,
            $filename,
        );
    }

    public function deleteAllForFollowUp(AccountingAcquisitionFollowUp $followUp): void
    {
        $this->mediaStorage->deleteContextFolder(
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
            $followUp->getDepartment()->getId() ?? '',
            (string) $followUp->getId(),
        );
    }

    public function buildReceiptUrl(string $departmentId, string $followUpId, string $filename): string
    {
        return $this->mediaStorage->buildPublicMediaUrl(
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
            $departmentId,
            $followUpId,
            $filename,
        );
    }
}
