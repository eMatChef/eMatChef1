<?php

declare(strict_types=1);

namespace App\Service\Material;

use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Material-Abbildungen unter var/uploads/{departmentId}/photos/material/{materialItemId}/.
 */
class MaterialPhotoStorageService
{
    public const MAX_PHOTOS = 1;

    public function __construct(
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function store(MaterialItem $material, User $user, UploadedFile $file): array
    {
        $materialId = (string) $material->getId();
        $departmentId = $material->getDepartmentId();

        $options = [];
        $materialName = trim($material->getName());
        if ($materialName !== '') {
            $options['original_filename'] = $materialName;
        }

        return $this->mediaStorage->store(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            $materialId,
            $departmentId,
            $user,
            $file,
            $options,
        );
    }

    public function resolveFilePath(string $departmentId, string $materialId, string $filename): string
    {
        return $this->mediaStorage->resolveStoredFilePath(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            $departmentId,
            $materialId,
            $filename,
        );
    }

    public function deleteAllForMaterial(MaterialItem $material): void
    {
        $this->mediaStorage->deleteContextFolder(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            $material->getDepartmentId(),
            (string) $material->getId(),
        );
    }

    public function buildMaterialPhotoUrl(string $departmentId, string $materialId, string $filename): string
    {
        return $this->mediaStorage->buildPublicMediaUrl(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            $departmentId,
            $materialId,
            $filename,
        );
    }
}
