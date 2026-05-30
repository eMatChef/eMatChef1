<?php

declare(strict_types=1);

namespace App\Service\Issue;

use App\Entity\Activity;
use App\Entity\ActivityIssueReport;
use App\Entity\User;
use App\Service\Media\MediaPhotoNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Foto-Upload und Serialisierung für ActivityIssueReport.
 */
class IssueReportPhotoService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssuePhotoStorageService $photoStorage,
        private IssuePhotoAccessService $photoAccess,
        private MediaPhotoNormalizer $photoNormalizer,
    ) {
    }

    /**
     * @return array<string, mixed> serialisierte Meldung
     */
    public function addPhoto(
        Activity $activity,
        ActivityIssueReport $report,
        User $user,
        UploadedFile $file,
    ): array {
        $this->photoAccess->assertCanUploadPhoto($user, $activity, $report);

        $photos = $report->getPhotos() ?? [];
        if (\count($photos) >= IssuePhotoStorageService::MAX_PHOTOS_PER_REPORT) {
            throw new \InvalidArgumentException(sprintf(
                'Maximal %d Fotos pro Meldung erlaubt',
                IssuePhotoStorageService::MAX_PHOTOS_PER_REPORT,
            ));
        }

        $photo = $this->photoStorage->store($report, $activity, $user, $file);
        $photos[] = $photo;
        $report->setPhotos($photos);
        $report->syncPrimaryPhotoUrl();

        $this->entityManager->flush();

        return $this->serializeIssueReport($report);
    }

    /** @return array<string, mixed> */
    public function serializeIssueReport(ActivityIssueReport $report): array
    {
        $mi = $report->getMaterialItem();
        $reporter = $report->getReportedByUser();
        $resolver = $report->getResolvedByUser();
        $photos = $this->photoNormalizer->normalizeOutgoing($report->getPhotos());

        return [
            'id' => $report->getId(),
            'activity_id' => $report->getActivityId(),
            'material_item_id' => $report->getMaterialItemId(),
            'material_name' => $mi?->getName(),
            'type' => $report->getType(),
            'type_label' => $report->getTypeLabel(),
            'quantity' => $report->getQuantity(),
            'description' => $report->getDescription(),
            'photo_url' => $report->getPrimaryPhotoUrl(),
            'photos' => $photos,
            'notes' => $report->getNotes(),
            'resolved' => $report->isResolved(),
            'resolved_at' => $report->getResolvedAt()?->format('c'),
            'resolved_by' => $resolver?->getId(),
            'reported_by' => $reporter?->getId(),
            'reported_by_display_name' => $this->displayUserName($reporter),
            'reported_at' => $report->getReportedAt()->format('c'),
            'created_at' => $report->getCreatedAt()->format('c'),
            'is_js_material' => $mi?->getIsJsMaterial() ?? false,
            'external_source' => $mi?->getExternalSource(),
        ];
    }

    private function displayUserName(?User $user): ?string
    {
        if (!$user instanceof User) {
            return null;
        }
        $profile = $user->getProfile();
        if ($profile && trim($profile->getDisplayName()) !== '') {
            return trim($profile->getDisplayName());
        }

        return trim($user->getEmail() ?? 'Unbekannt');
    }
}
