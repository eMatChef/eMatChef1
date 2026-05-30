<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Membership;
use App\Entity\SupplierCompany;
use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Repository\SupplierMembershipRepository;
use App\Service\Media\MediaPhotoNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Zugriff auf Werkstatt-Fotos: Department-Mitglied oder zugewiesener Lieferant.
 */
class WorkshopPhotoAccessService
{
    /** @var list<string> */
    private const UPLOAD_ROLES = ['mw', 'dc', 'matwart', 'depchef'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierMembershipRepository $supplierMembershipRepository,
        private MediaPhotoNormalizer $photoNormalizer,
    ) {
    }

    public function assertCanViewTicketPhotos(User $user, WorkshopTicket $ticket): void
    {
        if ($this->canViewTicketPhotos($user, $ticket)) {
            return;
        }

        throw new AccessDeniedHttpException('Kein Zugriff auf diese Fotos');
    }

    public function assertCanUploadTicketPhotos(User $user, WorkshopTicket $ticket): void
    {
        if ($this->canUploadTicketPhotos($user, $ticket)) {
            return;
        }

        throw new AccessDeniedHttpException('Kein Zugriff zum Hochladen von Fotos');
    }

    public function canUploadTicketPhotos(User $user, WorkshopTicket $ticket): bool
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $ticket->getDepartmentId()]);
        if (!$membership instanceof Membership) {
            return false;
        }

        $role = strtolower(trim((string) ($membership->getRole() ?? '')));

        return \in_array($role, self::UPLOAD_ROLES, true);
    }

    public function assertSupplierCanViewTicket(User $user, string $companyId, WorkshopTicket $ticket): void
    {
        if ((string) $ticket->getAssignedToSupplierCompanyId() !== $companyId) {
            throw new AccessDeniedHttpException('Ticket nicht dieser Lieferanten-Firma zugewiesen');
        }

        $supplierMembership = $this->supplierMembershipRepository->findOneBy([
            'userId' => $user->getId(),
            'supplierCompanyId' => $companyId,
        ]);
        if (!$supplierMembership) {
            throw new AccessDeniedHttpException('Kein Zugriff auf diese Lieferanten-Firma');
        }

        $company = $supplierMembership->getSupplierCompany();
        if ($company->getStatus() !== SupplierCompany::STATUS_ACTIVE) {
            throw new AccessDeniedHttpException('Lieferanten-Firma ist nicht aktiv');
        }
        if (!\in_array(SupplierCompany::CAPABILITY_REPAIRS, $company->getCapabilities(), true)) {
            throw new AccessDeniedHttpException('Repairs-Capability ist für diese Firma nicht aktiviert');
        }
    }

    public function canViewTicketPhotos(User $user, WorkshopTicket $ticket): bool
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $ticket->getDepartmentId()]);
        if ($membership instanceof Membership) {
            return true;
        }

        $assignedCompanyId = $ticket->getAssignedToSupplierCompanyId();
        if ($assignedCompanyId === null || $assignedCompanyId === '') {
            return false;
        }

        $supplierMembership = $this->supplierMembershipRepository->findOneBy([
            'userId' => $user->getId(),
            'supplierCompanyId' => $assignedCompanyId,
        ]);
        if (!$supplierMembership) {
            return false;
        }

        $company = $supplierMembership->getSupplierCompany();

        return $company->getStatus() === SupplierCompany::STATUS_ACTIVE
            && \in_array(SupplierCompany::CAPABILITY_REPAIRS, $company->getCapabilities(), true);
    }

    public function requireTicketById(string $ticketId): WorkshopTicket
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)->find($ticketId);
        if (!$ticket instanceof WorkshopTicket) {
            throw new \InvalidArgumentException('Ticket nicht gefunden');
        }

        return $ticket;
    }

    /**
     * Lieferanten-Firmen-ID für Legacy-Pfad-Fallback (workshop/supplier/{companyId}/…).
     */
    public function resolveLegacySupplierCompanyIdForPhoto(WorkshopTicket $ticket, string $filename): ?string
    {
        $photos = $this->photoNormalizer->normalizeOutgoing($ticket->getPhotos());
        foreach ($photos as $photo) {
            if (($photo['filename'] ?? null) === $filename) {
                $companyId = $this->photoNormalizer->resolveSupplierCompanyId($photo);
                if ($companyId !== null) {
                    return $companyId;
                }
            }
        }

        $assignedCompanyId = $ticket->getAssignedToSupplierCompanyId();
        if ($assignedCompanyId === null || $assignedCompanyId === '') {
            return null;
        }

        return $assignedCompanyId;
    }
}
