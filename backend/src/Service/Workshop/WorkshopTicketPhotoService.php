<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Service\Media\MediaPhotoNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Foto-Upload für Werkstatt-Tickets (Materialwart / Depchef).
 */
class WorkshopTicketPhotoService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkshopPhotoStorageService $photoStorage,
        private WorkshopPhotoAccessService $photoAccess,
        private MediaPhotoNormalizer $photoNormalizer,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function addPhoto(string $ticketId, User $user, UploadedFile $file): array
    {
        $ticket = $this->photoAccess->requireTicketById($ticketId);
        $this->photoAccess->assertCanUploadTicketPhotos($user, $ticket);

        $photo = $this->photoStorage->storeForDepartmentMember($ticket, $user, $file);

        $photos = $ticket->getPhotos() ?? [];
        $photos[] = $photo;
        $ticket->setPhotos($photos);
        $ticket->updateTimestamps();
        $this->entityManager->flush();

        return $this->photoNormalizer->normalizeOutgoing($ticket->getPhotos());
    }
}
