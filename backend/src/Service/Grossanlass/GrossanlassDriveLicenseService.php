<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\User;
use App\Entity\UserDriveLicense;
use App\Service\Media\MediaStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class GrossanlassDriveLicenseService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaStorageService $mediaStorage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFor(User $user): array
    {
        return $this->serialize($this->find($user));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function save(User $user, array $data): array
    {
        $row = $this->find($user) ?? $this->create($user);
        if (array_key_exists('drive_classes', $data) && is_array($data['drive_classes'])) {
            $row->setDriveClasses(GrossanlassDriveCategories::sanitize($data['drive_classes']));
        }
        if (array_key_exists('valid_until', $data)) {
            $raw = trim((string) ($data['valid_until'] ?? ''));
            $row->setValidUntil($raw !== '' ? new \DateTime($raw) : null);
        }
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadProof(User $user, UploadedFile $file): array
    {
        $row = $this->find($user) ?? $this->create($user);
        if ($row->getDocumentFilename() !== '') {
            $this->deleteProofFile($user, $row->getDocumentFilename());
        }
        $stored = $this->mediaStorage->storeAttachment(
            MediaStorageService::CONTEXT_USER_DRIVE_LICENSE,
            $user->getId(),
            $user->getId(),
            $user,
            $file,
            [],
        );
        $row->setDocumentFilename($stored['filename']);
        $row->setDocumentOriginalName($stored['original_filename'] ?? $stored['filename']);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeProof(User $user): array
    {
        $row = $this->find($user);
        if ($row instanceof UserDriveLicense && $row->getDocumentFilename() !== '') {
            $this->deleteProofFile($user, $row->getDocumentFilename());
            $row->setDocumentFilename('');
            $row->setDocumentOriginalName('');
            $this->entityManager->flush();
        }

        return $this->serialize($row);
    }

    public function find(User $user): ?UserDriveLicense
    {
        $row = $this->entityManager->getRepository(UserDriveLicense::class)->find($user->getId());

        return $row instanceof UserDriveLicense ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(?UserDriveLicense $row): array
    {
        if (!$row instanceof UserDriveLicense) {
            return [
                'drive_classes' => [],
                'valid_until' => null,
                'document' => null,
            ];
        }
        $document = null;
        if ($row->getDocumentFilename() !== '') {
            $document = [
                'filename' => $row->getDocumentFilename(),
                'original_name' => $row->getDocumentOriginalName() ?: $row->getDocumentFilename(),
                'url' => $this->mediaStorage->buildPublicMediaUrl(
                    MediaStorageService::CONTEXT_USER_DRIVE_LICENSE,
                    $row->getUserId(),
                    $row->getUserId(),
                    $row->getDocumentFilename(),
                ),
            ];
        }

        return [
            'drive_classes' => $row->getDriveClasses(),
            'valid_until' => $row->getValidUntil()?->format('Y-m-d'),
            'document' => $document,
        ];
    }

    private function create(User $user): UserDriveLicense
    {
        $row = new UserDriveLicense();
        $row->setUser($user);
        $this->entityManager->persist($row);

        return $row;
    }

    private function deleteProofFile(User $user, string $filename): void
    {
        try {
            $path = $this->mediaStorage->resolveStoredFilePath(
                MediaStorageService::CONTEXT_USER_DRIVE_LICENSE,
                $user->getId(),
                $user->getId(),
                $filename,
            );
            @unlink($path);
        } catch (\InvalidArgumentException) {
        }
    }
}
