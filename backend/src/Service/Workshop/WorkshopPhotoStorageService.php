<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Service\Media\MediaStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Werkstatt-Fotos — delegiert an MediaStorageService (var/uploads/workshop/{departmentId}/{ticketId}/).
 *
 * Legacy-Pfade workshop/supplier/{companyId}/{ticketId}/ werden beim Lesen weiter unterstützt.
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
            [
                'url_builder' => fn (string $filename): string => $this->buildWorkshopPhotoUrl(
                    $ticketId,
                    $filename,
                ),
            ],
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
                'url_builder' => fn (string $filename): string => $this->buildSupplierPhotoUrl(
                    $supplierCompanyId,
                    $ticketId,
                    $filename,
                ),
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

    public function buildSupplierPhotoUrl(string $supplierCompanyId, string $ticketId, string $filename): string
    {
        return $this->mediaStorage->buildSupplierPhotoUrl($supplierCompanyId, $ticketId, $filename);
    }

    public function buildWorkshopPhotoUrl(string $ticketId, string $filename): string
    {
        return $this->mediaStorage->buildWorkshopPhotoUrl($ticketId, $filename);
    }
}
