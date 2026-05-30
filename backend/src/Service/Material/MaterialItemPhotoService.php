<?php

declare(strict_types=1);

namespace App\Service\Material;

use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\Media\MediaPhotoNormalizer;
use App\Service\Media\MediaUrlImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Primary-Foto für MaterialItem (max. 1 — Ersetzen beim erneuten Upload).
 */
class MaterialItemPhotoService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MaterialPhotoStorageService $photoStorage,
        private MaterialPhotoAccessService $photoAccess,
        private MediaPhotoNormalizer $photoNormalizer,
        private MediaUrlImportService $urlImport,
    ) {
    }

    /**
     * @return array{photos: list<array<string, mixed>>, image_url: string|null}
     */
    public function replacePrimaryPhoto(MaterialItem $material, User $user, UploadedFile $file): array
    {
        $this->photoAccess->assertCanUploadPhoto($user, $material);

        $this->photoStorage->deleteAllForMaterial($material);

        $photo = $this->photoStorage->store($material, $user, $file);
        $material->setPhotos([$photo]);
        $material->updateTimestamps();
        $this->entityManager->flush();

        $photos = $this->photoNormalizer->normalizeOutgoing($material->getPhotos());

        return [
            'photos' => $photos,
            'image_url' => $material->getPrimaryPhotoUrl(),
        ];
    }

    /**
     * @return array{photos: list<array<string, mixed>>, image_url: string|null}
     */
    public function replacePrimaryPhotoFromUrl(MaterialItem $material, User $user, string $url): array
    {
        $file = $this->urlImport->toUploadedFile($url);

        try {
            return $this->replacePrimaryPhoto($material, $user, $file);
        } finally {
            $path = $file->getPathname();
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function deletePhotosForMaterial(MaterialItem $material): void
    {
        $this->photoStorage->deleteAllForMaterial($material);
        $material->setPhotos([]);
    }
}
