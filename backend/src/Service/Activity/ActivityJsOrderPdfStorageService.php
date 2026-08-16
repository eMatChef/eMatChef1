<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\ActivityJsOrder;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use App\Util\IdGenerator;

/**
 * J+S-Bestell-PDF unter var/uploads/activity-js-order/{departmentId}/{orderId}/.
 */
class ActivityJsOrderPdfStorageService
{
    public function __construct(
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array{id: string, filename: string, url: string, bytes: int}
     */
    public function store(ActivityJsOrder $order, User $user, string $pdfBinary): array
    {
        $orderId = (string) $order->getId();
        $departmentId = $order->getActivity()->getDepartmentId();

        $mediaId = IdGenerator::generate();
        $filename = $mediaId . '.pdf';
        $targetDir = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER,
            $departmentId,
            $orderId,
        );

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('PDF-Verzeichnis konnte nicht angelegt werden');
        }

        $path = $targetDir . '/' . $filename;
        if (file_put_contents($path, $pdfBinary) === false) {
            throw new \RuntimeException('PDF konnte nicht gespeichert werden');
        }

        return [
            'id' => $mediaId,
            'filename' => $filename,
            'url' => $this->mediaStorage->buildPublicMediaUrl(
                MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER,
                $departmentId,
                $orderId,
                $filename,
            ),
            'bytes' => strlen($pdfBinary),
        ];
    }

    public function resolveFilePath(string $activityId, string $orderId, string $departmentId, string $filename): string
    {
        $this->mediaStorage->assertSafePathSegment($activityId);

        return $this->mediaStorage->resolveStoredFilePath(
            MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER,
            $departmentId,
            $orderId,
            $filename,
        );
    }

    public function deleteByMediaId(ActivityJsOrder $order, string $mediaId): void
    {
        if ($mediaId === '') {
            return;
        }

        try {
            $path = $this->resolveFilePath(
                $order->getActivityId(),
                (string) $order->getId(),
                $order->getActivity()->getDepartmentId(),
                $mediaId . '.pdf',
            );
            @unlink($path);
        } catch (\InvalidArgumentException) {
            // bereits gelöscht
        }
    }

    public function buildPdfUrl(string $departmentId, string $orderId, string $filename): string
    {
        return $this->mediaStorage->buildPublicMediaUrl(
            MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER,
            $departmentId,
            $orderId,
            $filename,
        );
    }
}
