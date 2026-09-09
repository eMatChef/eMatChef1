<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Ausweis-/Ausbildungsnachweis unter var/uploads/.../grossanlass-user-card/{userId}/.
 */
class GrossanlassUserCardDriveProofStorageService
{
    public function __construct(
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array{filename: string, url: string, bytes: int, original_filename: string}
     */
    public function store(Department $department, User $subject, User $actor, UploadedFile $file): array
    {
        $stored = $this->mediaStorage->storeAttachment(
            MediaStorageService::CONTEXT_GROSSANLASS_USER_CARD,
            $subject->getId(),
            $department->getId(),
            $actor,
            $file,
            [],
        );

        return [
            'filename' => $stored['filename'],
            'url' => $stored['url'],
            'bytes' => $stored['bytes'],
            'original_filename' => $stored['original_filename'],
        ];
    }

    public function deleteFile(string $departmentId, string $userId, string $filename): void
    {
        if ($filename === '') {
            return;
        }
        try {
            $path = $this->mediaStorage->resolveStoredFilePath(
                MediaStorageService::CONTEXT_GROSSANLASS_USER_CARD,
                $departmentId,
                $userId,
                $filename,
            );
            @unlink($path);
        } catch (\InvalidArgumentException) {
            // bereits gelöscht
        }
    }

    public function buildUrl(string $departmentId, string $userId, string $filename): string
    {
        return $this->mediaStorage->buildPublicMediaUrl(
            MediaStorageService::CONTEXT_GROSSANLASS_USER_CARD,
            $departmentId,
            $userId,
            $filename,
        );
    }
}
