<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Werkstatt-Fotos unter var/uploads/{departmentId}/photos/workshop/{ticketId}/.
 */
class WorkshopPhotoStorageService
{
    public function __construct(
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function storeForDepartmentMember(
        WorkshopTicket $ticket,
        User $user,
        UploadedFile $file,
    ): array {
        $ticketId = (string) $ticket->getId();
        $departmentId = $ticket->getDepartmentId();

        return $this->mediaStorage->store(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $ticketId,
            $departmentId,
            $user,
            $file,
            [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function store(
        WorkshopTicket $ticket,
        User $user,
        UploadedFile $file,
        string $supplierCompanyId,
    ): array {
        $ticketId = (string) $ticket->getId();
        $departmentId = $ticket->getDepartmentId();

        return $this->mediaStorage->store(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $ticketId,
            $departmentId,
            $user,
            $file,
            [
                'uploaded_by_supplier_company_id' => $supplierCompanyId,
            ],
        );
    }

    public function resolveWorkshopTicketFilePath(
        string $departmentId,
        string $ticketId,
        string $filename,
        ?string $legacySupplierCompanyId = null,
    ): string {
        return $this->mediaStorage->resolveWorkshopTicketFilePath(
            $departmentId,
            $ticketId,
            $filename,
            $legacySupplierCompanyId,
        );
    }

    public function buildSupplierPhotoUrl(string $departmentId, string $ticketId, string $filename): string
    {
        return $this->mediaStorage->buildPublicMediaUrl(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $departmentId,
            $ticketId,
            $filename,
        );
    }

    public function buildWorkshopPhotoUrl(string $departmentId, string $ticketId, string $filename): string
    {
        return $this->mediaStorage->buildPublicMediaUrl(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $departmentId,
            $ticketId,
            $filename,
        );
    }
}
