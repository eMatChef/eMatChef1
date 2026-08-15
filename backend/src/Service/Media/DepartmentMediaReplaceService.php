<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\ActivityIssueReport;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\Accounting\AccountingAcquisitionFollowUpReceiptStorageService;
use App\Service\Accounting\AccountingBookingReceiptStorageService;
use App\Service\Issue\IssuePhotoAccessService;
use App\Service\Issue\IssuePhotoStorageService;
use App\Service\Material\MaterialItemPhotoService;
use App\Service\Material\MaterialPhotoAccessService;
use App\Service\Workshop\WorkshopPhotoAccessService;
use App\Service\Workshop\WorkshopPhotoStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DepartmentMediaReplaceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentMediaBrowserService $browser,
        private MediaPhotoNormalizer $photoNormalizer,
        private MediaStorageService $mediaStorage,
        private MaterialItemPhotoService $materialPhotoService,
        private MaterialPhotoAccessService $materialPhotoAccess,
        private WorkshopPhotoStorageService $workshopPhotoStorage,
        private WorkshopPhotoAccessService $workshopPhotoAccess,
        private IssuePhotoStorageService $issuePhotoStorage,
        private IssuePhotoAccessService $issuePhotoAccess,
        private AccountingBookingReceiptStorageService $bookingReceiptStorage,
        private AccountingAcquisitionFollowUpReceiptStorageService $followUpReceiptStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function replace(
        User $user,
        string $departmentId,
        string $context,
        string $contextId,
        string $filename,
        UploadedFile $file,
    ): array {
        return match ($context) {
            MediaStorageService::CONTEXT_MATERIAL_ITEM => $this->replaceMaterial($user, $departmentId, $contextId, $file),
            MediaStorageService::CONTEXT_WORKSHOP_TICKET => $this->replaceWorkshop($user, $departmentId, $contextId, $filename, $file),
            MediaStorageService::CONTEXT_ISSUE_REPORT => $this->replaceIssue($user, $departmentId, $contextId, $filename, $file),
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING => $this->replaceBooking($user, $departmentId, $contextId, $filename, $file),
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP => $this->replaceFollowUp($user, $departmentId, $contextId, $filename, $file),
            default => throw new \InvalidArgumentException('Diese Datei kann hier nicht ersetzt werden'),
        };
    }

    /** @return array<string, mixed> */
    private function replaceMaterial(User $user, string $departmentId, string $materialId, UploadedFile $file): array
    {
        $material = $this->materialPhotoAccess->requireMaterialById($materialId);
        if ($material->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }

        $result = $this->materialPhotoService->replacePrimaryPhoto($material, $user, $file);
        $photo = $result['photos'][0] ?? null;
        if (!\is_array($photo)) {
            throw new \RuntimeException('Foto konnte nicht gespeichert werden');
        }

        $mapped = $this->browser->mapStoredFile(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            $departmentId,
            $materialId,
            $material->getName(),
            '/materials/' . $materialId,
            $photo,
        );
        if ($mapped === null) {
            throw new \RuntimeException('Foto konnte nicht gespeichert werden');
        }

        return $mapped;
    }

    /** @return array<string, mixed> */
    private function replaceWorkshop(
        User $user,
        string $departmentId,
        string $ticketId,
        string $filename,
        UploadedFile $file,
    ): array {
        $ticket = $this->workshopPhotoAccess->requireTicketById($ticketId);
        if ($ticket->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $this->workshopPhotoAccess->assertCanUploadTicketPhotos($user, $ticket);

        $photos = $this->photoNormalizer->normalizeIncoming($ticket->getPhotos() ?? []);
        $index = $this->indexOfFilename($photos, $filename);
        $legacyCompanyId = $this->workshopPhotoAccess->resolveLegacySupplierCompanyIdForPhoto($ticket, $filename);
        $this->mediaStorage->deleteStoredFile(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $departmentId,
            $ticketId,
            $filename,
            $legacyCompanyId,
        );

        $newPhoto = $this->workshopPhotoStorage->storeForDepartmentMember($ticket, $user, $file);
        $photos[$index] = $newPhoto;
        $ticket->setPhotos(array_values($photos));
        $ticket->updateTimestamps();
        $this->entityManager->flush();

        $material = $ticket->getMaterialItem();
        $links = [
            ['kind' => 'workshop', 'label' => $ticket->getTitle(), 'path' => '/workshop?ticket=' . rawurlencode($ticketId)],
            ['kind' => 'material', 'label' => $material->getName(), 'path' => '/materials/' . $material->getId()],
        ];
        $mapped = $this->browser->mapStoredFile(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $departmentId,
            $ticketId,
            $ticket->getTitle(),
            '/workshop?ticket=' . rawurlencode($ticketId),
            $newPhoto,
            null,
            $links,
        );
        if ($mapped === null) {
            throw new \RuntimeException('Foto konnte nicht gespeichert werden');
        }

        return $mapped;
    }

    /** @return array<string, mixed> */
    private function replaceIssue(
        User $user,
        string $departmentId,
        string $issueId,
        string $filename,
        UploadedFile $file,
    ): array {
        $report = $this->entityManager->find(ActivityIssueReport::class, $issueId);
        if (!$report instanceof ActivityIssueReport) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $activity = $report->getActivity();
        if ($activity->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $this->issuePhotoAccess->assertCanUploadPhoto($user, $activity, $report);

        $photos = $this->photoNormalizer->normalizeIncoming($report->getPhotos() ?? []);
        $index = $this->indexOfFilename($photos, $filename);
        $this->mediaStorage->deleteStoredFile(
            MediaStorageService::CONTEXT_ISSUE_REPORT,
            $departmentId,
            $issueId,
            $filename,
        );

        $newPhoto = $this->issuePhotoStorage->store($report, $activity, $user, $file);
        $photos[$index] = $newPhoto;
        $report->setPhotos(array_values($photos));
        $report->syncPrimaryPhotoUrl();
        $this->entityManager->flush();

        $links = [[
            'kind' => 'activity',
            'label' => $activity->getName(),
            'path' => '/activities/' . $activity->getId(),
        ]];
        $material = $report->getMaterialItem();
        if ($material instanceof MaterialItem) {
            $links[] = [
                'kind' => 'material',
                'label' => $material->getName(),
                'path' => '/materials/' . $material->getId(),
            ];
        }

        $mapped = $this->browser->mapStoredFile(
            MediaStorageService::CONTEXT_ISSUE_REPORT,
            $departmentId,
            $issueId,
            $activity->getName(),
            '/activities/' . $activity->getId(),
            $newPhoto,
            null,
            $links,
        );
        if ($mapped === null) {
            throw new \RuntimeException('Foto konnte nicht gespeichert werden');
        }

        return $mapped;
    }

    /** @return array<string, mixed> */
    private function replaceBooking(
        User $user,
        string $departmentId,
        string $bookingId,
        string $filename,
        UploadedFile $file,
    ): array {
        $booking = $this->entityManager->find(AccountingBooking::class, $bookingId);
        if (!$booking instanceof AccountingBooking || $booking->getDepartment()->getId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }

        $photos = $this->photoNormalizer->normalizeIncoming($booking->getReceipts() ?? []);
        $index = $this->indexOfFilename($photos, $filename);
        $this->mediaStorage->deleteStoredFile(
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
            $departmentId,
            $bookingId,
            $filename,
        );

        $newPhoto = $this->bookingReceiptStorage->store($booking, $user, $file);
        $photos[$index] = $newPhoto;
        $booking->setReceipts(array_values($photos));
        $booking->touchUpdatedAt();
        $this->entityManager->flush();

        $label = $booking->getReceiptLabel() ?: ('Buchung ' . $booking->getBookedAt()->format('Y-m-d'));
        $mapped = $this->browser->mapStoredFile(
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
            $departmentId,
            $bookingId,
            $label,
            '/accounting/bookings',
            $newPhoto,
        );
        if ($mapped === null) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden');
        }

        return $mapped;
    }

    /** @return array<string, mixed> */
    private function replaceFollowUp(
        User $user,
        string $departmentId,
        string $followUpId,
        string $filename,
        UploadedFile $file,
    ): array {
        $followUp = $this->entityManager->find(AccountingAcquisitionFollowUp::class, $followUpId);
        if (!$followUp instanceof AccountingAcquisitionFollowUp || $followUp->getDepartment()->getId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        if ($followUp->getStatus() !== AccountingAcquisitionFollowUp::STATUS_PENDING) {
            throw new \InvalidArgumentException('Anschaffungs-Auftrag ist bereits erfasst');
        }

        $photos = $this->photoNormalizer->normalizeIncoming($followUp->getReceipts() ?? []);
        $index = $this->indexOfFilename($photos, $filename);
        $this->mediaStorage->deleteStoredFile(
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
            $departmentId,
            $followUpId,
            $filename,
        );

        $newPhoto = $this->followUpReceiptStorage->store($followUp, $user, $file);
        $photos[$index] = $newPhoto;
        $followUp->setReceipts(array_values($photos));
        $followUp->touchUpdatedAt();
        $this->entityManager->flush();

        $label = $followUp->getReceiptLabel() ?: 'Anschaffung';
        $mapped = $this->browser->mapStoredFile(
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
            $departmentId,
            $followUpId,
            $label,
            '/accounting/bookings',
            $newPhoto,
        );
        if ($mapped === null) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden');
        }

        return $mapped;
    }

    /**
     * Nur Anzeigename (`original_filename`), Datei auf der Platte bleibt.
     *
     * @return array<string, mixed>
     */
    public function rename(
        string $departmentId,
        string $context,
        string $contextId,
        string $filename,
        string $originalFilename,
    ): array {
        if (trim($originalFilename) === '') {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }
        $name = $this->mediaStorage->sanitizeOriginalFilename($originalFilename);

        return match ($context) {
            MediaStorageService::CONTEXT_MATERIAL_ITEM => $this->renameMaterial($departmentId, $contextId, $filename, $name),
            MediaStorageService::CONTEXT_WORKSHOP_TICKET => $this->renameWorkshop($departmentId, $contextId, $filename, $name),
            MediaStorageService::CONTEXT_ISSUE_REPORT => $this->renameIssue($departmentId, $contextId, $filename, $name),
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING => $this->renameBooking($departmentId, $contextId, $filename, $name),
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP => $this->renameFollowUp($departmentId, $contextId, $filename, $name),
            default => throw new \InvalidArgumentException('Dieser Name kann hier nicht geändert werden'),
        };
    }

    /** @return array<string, mixed> */
    private function renameMaterial(string $departmentId, string $materialId, string $filename, string $name): array
    {
        $material = $this->materialPhotoAccess->requireMaterialById($materialId);
        if ($material->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $photos = $this->setOriginalFilename($material->getPhotos() ?? [], $filename, $name);
        $material->setPhotos($photos);
        $material->updateTimestamps();
        $this->entityManager->flush();
        $photo = $photos[$this->indexOfFilename($photos, $filename)];

        return $this->requireMapped(
            $this->browser->mapStoredFile(
                MediaStorageService::CONTEXT_MATERIAL_ITEM,
                $departmentId,
                $materialId,
                $material->getName(),
                '/materials/' . $materialId,
                $photo,
            ),
        );
    }

    /** @return array<string, mixed> */
    private function renameWorkshop(string $departmentId, string $ticketId, string $filename, string $name): array
    {
        $ticket = $this->workshopPhotoAccess->requireTicketById($ticketId);
        if ($ticket->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $photos = $this->setOriginalFilename($ticket->getPhotos() ?? [], $filename, $name);
        $ticket->setPhotos($photos);
        $ticket->updateTimestamps();
        $this->entityManager->flush();
        $photo = $photos[$this->indexOfFilename($photos, $filename)];
        $material = $ticket->getMaterialItem();
        $links = [
            ['kind' => 'workshop', 'label' => $ticket->getTitle(), 'path' => '/workshop?ticket=' . rawurlencode($ticketId)],
            ['kind' => 'material', 'label' => $material->getName(), 'path' => '/materials/' . $material->getId()],
        ];

        return $this->requireMapped(
            $this->browser->mapStoredFile(
                MediaStorageService::CONTEXT_WORKSHOP_TICKET,
                $departmentId,
                $ticketId,
                $ticket->getTitle(),
                '/workshop?ticket=' . rawurlencode($ticketId),
                $photo,
                null,
                $links,
            ),
        );
    }

    /** @return array<string, mixed> */
    private function renameIssue(string $departmentId, string $issueId, string $filename, string $name): array
    {
        $report = $this->entityManager->find(ActivityIssueReport::class, $issueId);
        if (!$report instanceof ActivityIssueReport) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $activity = $report->getActivity();
        if ($activity->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $photos = $this->setOriginalFilename($report->getPhotos() ?? [], $filename, $name);
        $report->setPhotos($photos);
        $this->entityManager->flush();
        $photo = $photos[$this->indexOfFilename($photos, $filename)];
        $links = [[
            'kind' => 'activity',
            'label' => $activity->getName(),
            'path' => '/activities/' . $activity->getId(),
        ]];
        $material = $report->getMaterialItem();
        if ($material instanceof MaterialItem) {
            $links[] = [
                'kind' => 'material',
                'label' => $material->getName(),
                'path' => '/materials/' . $material->getId(),
            ];
        }

        return $this->requireMapped(
            $this->browser->mapStoredFile(
                MediaStorageService::CONTEXT_ISSUE_REPORT,
                $departmentId,
                $issueId,
                $activity->getName(),
                '/activities/' . $activity->getId(),
                $photo,
                null,
                $links,
            ),
        );
    }

    /** @return array<string, mixed> */
    private function renameBooking(string $departmentId, string $bookingId, string $filename, string $name): array
    {
        $booking = $this->entityManager->find(AccountingBooking::class, $bookingId);
        if (!$booking instanceof AccountingBooking || $booking->getDepartment()->getId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $photos = $this->setOriginalFilename($booking->getReceipts() ?? [], $filename, $name);
        $booking->setReceipts($photos);
        $booking->touchUpdatedAt();
        $this->entityManager->flush();
        $photo = $photos[$this->indexOfFilename($photos, $filename)];
        $label = $booking->getReceiptLabel() ?: ('Buchung ' . $booking->getBookedAt()->format('Y-m-d'));

        return $this->requireMapped(
            $this->browser->mapStoredFile(
                MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
                $departmentId,
                $bookingId,
                $label,
                '/accounting/bookings',
                $photo,
            ),
        );
    }

    /** @return array<string, mixed> */
    private function renameFollowUp(string $departmentId, string $followUpId, string $filename, string $name): array
    {
        $followUp = $this->entityManager->find(AccountingAcquisitionFollowUp::class, $followUpId);
        if (!$followUp instanceof AccountingAcquisitionFollowUp || $followUp->getDepartment()->getId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $photos = $this->setOriginalFilename($followUp->getReceipts() ?? [], $filename, $name);
        $followUp->setReceipts($photos);
        $followUp->touchUpdatedAt();
        $this->entityManager->flush();
        $photo = $photos[$this->indexOfFilename($photos, $filename)];
        $label = $followUp->getReceiptLabel() ?: 'Anschaffung';

        return $this->requireMapped(
            $this->browser->mapStoredFile(
                MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
                $departmentId,
                $followUpId,
                $label,
                '/accounting/bookings',
                $photo,
            ),
        );
    }

    /**
     * @param list<array<string, mixed>>|null $photos
     *
     * @return list<array<string, mixed>>
     */
    private function setOriginalFilename(?array $photos, string $filename, string $name): array
    {
        $list = $this->photoNormalizer->normalizeIncoming($photos ?? []);
        $index = $this->indexOfFilename($list, $filename);
        $list[$index]['original_filename'] = $name;

        return array_values($list);
    }

    /**
     * @param array<string, mixed>|null $mapped
     *
     * @return array<string, mixed>
     */
    private function requireMapped(?array $mapped): array
    {
        if ($mapped === null) {
            throw new \RuntimeException('Datei nicht gefunden');
        }

        return $mapped;
    }

    /**
     * @param list<array<string, mixed>> $photos
     */
    private function indexOfFilename(array $photos, string $filename): int
    {
        foreach ($photos as $index => $photo) {
            if (($photo['filename'] ?? '') === $filename) {
                return $index;
            }
            $url = (string) ($photo['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $path = parse_url($url, PHP_URL_PATH);
            $base = basename(\is_string($path) && $path !== '' ? $path : $url);
            if ($base === $filename) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('Datei nicht gefunden');
    }
}
