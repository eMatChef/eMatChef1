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
        $departmentId = $activity->getDepartmentId();

        return $this->mediaStorage->store(
            MediaStorageService::CONTEXT_ISSUE_REPORT,
            $issueId,
            $departmentId,
            $user,
            $file,
            [],
        );
    }

    public function resolveFilePath(
        string $departmentId,
        string $issueReportId,
        string $filename,
    ): string {
        return $this->mediaStorage->resolveStoredFilePath(
            MediaStorageService::CONTEXT_ISSUE_REPORT,
            $departmentId,
            $issueReportId,
            $filename,
        );
    }

    public function buildIssuePhotoUrl(string $departmentId, string $issueId, string $filename): string
    {
        return $this->mediaStorage->buildPublicMediaUrl(
            MediaStorageService::CONTEXT_ISSUE_REPORT,
            $departmentId,
            $issueId,
            $filename,
        );
    }
}
