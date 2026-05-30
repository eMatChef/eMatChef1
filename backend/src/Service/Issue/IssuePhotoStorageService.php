<?php

declare(strict_types=1);

namespace App\Service\Issue;

use App\Entity\Activity;
use App\Entity\ActivityIssueReport;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Schadenmeldungs-Fotos unter var/uploads/issues/{departmentId}/{issueReportId}/.
 */
class IssuePhotoStorageService
{
    public const MAX_PHOTOS_PER_REPORT = 3;

    public function __construct(
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function store(
        ActivityIssueReport $report,
        Activity $activity,
        User $user,
        UploadedFile $file,
    ): array {
        $issueId = (string) $report->getId();
        $activityId = $activity->getId();
        $departmentId = $activity->getDepartmentId();

        return $this->mediaStorage->store(
            MediaStorageService::CONTEXT_ISSUE_REPORT,
            $issueId,
            $departmentId,
            $user,
            $file,
            [
                'url_builder' => fn (string $filename): string => $this->buildIssuePhotoUrl(
                    $activityId,
                    $issueId,
                    $filename,
                ),
            ],
        );
    }

    public function resolveFilePath(
        string $departmentId,
        string $issueReportId,
        string $filename,
    ): string {
        $this->mediaStorage->assertSafePathSegment($departmentId);
        $this->mediaStorage->assertSafePathSegment($issueReportId);
        $this->mediaStorage->assertSafeFilename($filename);

        $path = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_ISSUE_REPORT,
            $departmentId,
            $issueReportId,
        ) . '/' . $filename;

        if (!is_file($path)) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }

        return $path;
    }

    public function buildIssuePhotoUrl(string $activityId, string $issueId, string $filename): string
    {
        return sprintf(
            '/api/activities/%s/issues/%s/photos/%s',
            rawurlencode($activityId),
            rawurlencode($issueId),
            rawurlencode($filename),
        );
    }
}
