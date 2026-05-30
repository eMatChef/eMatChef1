<?php

declare(strict_types=1);

namespace App\Service\Material;

use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Material-Abbildungen unter var/uploads/material/{departmentId}/{materialItemId}/.
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

        return $this->mediaStorage->store(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            $materialId,
            $departmentId,
            $user,
            $file,
            [
                'url_builder' => fn (string $filename): string => $this->buildMaterialPhotoUrl(
                    $materialId,
                    $filename,
                ),
            ],
        );
    }

    public function resolveFilePath(string $departmentId, string $materialId, string $filename): string
    {
        $this->mediaStorage->assertSafePathSegment($departmentId);
        $this->mediaStorage->assertSafePathSegment($materialId);
        $this->mediaStorage->assertSafeFilename($filename);

        $path = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            $departmentId,
            $materialId,
        ) . '/' . $filename;

        if (!is_file($path)) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }

        return $path;
    }

    public function deleteAllForMaterial(MaterialItem $material): void
    {
        $this->mediaStorage->deleteContextFolder(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            $material->getDepartmentId(),
            (string) $material->getId(),
        );
    }

    public function buildMaterialPhotoUrl(string $materialId, string $filename): string
    {
        return sprintf(
            '/api/materials/%s/photos/%s',
            rawurlencode($materialId),
            rawurlencode($filename),
        );
    }
}
