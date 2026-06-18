<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Address;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Eventstandort bei geteilten Aktivitäten: Kopie ins Adressbuch der Partner-Abteilung, Sync bei Änderung.
 */
final class ActivitySharedVenueService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Für Formular-Binding: lokale Adress-ID des Betrachters (Gast-Kopie oder Host-Kanonisch).
     */
    public function resolveViewerVenueAddressId(Activity $activity, string $viewerDepartmentId): ?string
    {
        $viewerDepartmentId = trim($viewerDepartmentId);
        $hostDeptId = $activity->getDepartmentId();
        $canonicalId = trim((string) ($activity->getVenueAddressId() ?? ''));

        if ($viewerDepartmentId === '' || $canonicalId === '') {
            return $canonicalId !== '' ? $canonicalId : null;
        }

        if ($viewerDepartmentId === $hostDeptId) {
            return $canonicalId;
        }

        $mirrorId = $this->findLocalVenueMirrorId($activity, $viewerDepartmentId);
        if ($mirrorId !== null) {
            return $mirrorId;
        }

        return $this->ensureLocalVenueMirror($activity, $viewerDepartmentId);
    }

    /**
     * Spiegel anlegen/aktualisieren und Aktivität persistieren falls nötig.
     */
    public function ensureLocalVenueMirror(Activity $activity, string $guestDepartmentId): ?string
    {
        $guestDepartmentId = trim($guestDepartmentId);
        $hostDeptId = $activity->getDepartmentId();
        if ($guestDepartmentId === '' || $guestDepartmentId === $hostDeptId) {
            return $activity->getVenueAddressId();
        }

        $canonical = $activity->getVenueAddress();
        if ($canonical === null) {
            return null;
        }

        $existingId = $this->findLocalVenueMirrorId($activity, $guestDepartmentId);
        if ($existingId !== null) {
            $existing = $this->entityManager->getRepository(Address::class)->find($existingId);
            if ($existing !== null && !$existing->isDeleted()) {
                $this->copyAddressFields($canonical, $existing);
                $existing->updateTimestamps();
                $this->entityManager->flush();

                return $existingId;
            }
        }

        $mirror = $this->cloneAddressForDepartment($canonical, $guestDepartmentId);
        $this->entityManager->persist($mirror);
        $this->setLocalVenueMirrorId($activity, $guestDepartmentId, (string) $mirror->getId());
        $this->entityManager->flush();

        return (string) $mirror->getId();
    }

    /**
     * venue_address_id aus PATCH: Gast aktualisiert lokale Kopie → Kanonisch + andere Spiegel syncen.
     *
     * @return array{activity_venue_id: ?string, viewer_venue_id: ?string}
     */
    public function applyVenuePatch(Activity $activity, User $user, string $viewerDepartmentId, ?string $requestedAddressId): array
    {
        $viewerDepartmentId = trim($viewerDepartmentId);
        $requestedAddressId = $requestedAddressId !== null ? trim($requestedAddressId) : null;
        $hostDeptId = $activity->getDepartmentId();

        if ($requestedAddressId === null || $requestedAddressId === '') {
            $activity->setVenueAddress(null);
            $activity->setVenueAddressId(null);
            $this->clearLocalVenueMirrors($activity);
            $this->entityManager->flush();

            return ['activity_venue_id' => null, 'viewer_venue_id' => null];
        }

        $address = $this->entityManager->getRepository(Address::class)->find($requestedAddressId);
        if ($address === null || $address->isDeleted()) {
            throw new \InvalidArgumentException('Adresse nicht gefunden');
        }

        if ($viewerDepartmentId === $hostDeptId) {
            if ($address->getDepartmentId() !== $hostDeptId) {
                throw new \InvalidArgumentException('Eventstandort muss zum Ersteller-Department gehören');
            }
            $activity->setVenueAddress($address);
            $this->syncMirrorsFromCanonical($activity, $address);
            $this->entityManager->flush();

            return [
                'activity_venue_id' => $activity->getVenueAddressId(),
                'viewer_venue_id' => $activity->getVenueAddressId(),
            ];
        }

        if ($address->getDepartmentId() !== $viewerDepartmentId) {
            throw new \InvalidArgumentException('Eventstandort muss zu deinem Department gehören');
        }

        $canonical = $activity->getVenueAddress();
        if ($canonical === null) {
            throw new \InvalidArgumentException('Kein Eventstandort am Ersteller hinterlegt');
        }

        $this->copyAddressFields($address, $canonical);
        $canonical->updateTimestamps();
        $this->syncMirrorsFromCanonical($activity, $canonical, excludeAddressId: $address->getId());
        $this->setLocalVenueMirrorId($activity, $viewerDepartmentId, (string) $address->getId());
        $this->entityManager->flush();

        return [
            'activity_venue_id' => $activity->getVenueAddressId(),
            'viewer_venue_id' => (string) $address->getId(),
        ];
    }

    public function syncMirrorsFromCanonical(Activity $activity, Address $canonical, ?string $excludeAddressId = null): void
    {
        $canonicalId = (string) $canonical->getId();
        $hostDeptId = $activity->getDepartmentId();

        foreach ($activity->getInvitedDepartments() ?? [] as $inv) {
            if (!\is_array($inv)) {
                continue;
            }
            if (($inv['status'] ?? '') === 'rejected') {
                continue;
            }
            $deptId = trim((string) ($inv['id'] ?? ''));
            if ($deptId === '' || $deptId === $hostDeptId) {
                continue;
            }

            $localId = trim((string) ($inv['local_venue_address_id'] ?? ''));
            if ($localId === '') {
                $this->ensureLocalVenueMirror($activity, $deptId);
                continue;
            }

            if ($excludeAddressId !== null && $localId === $excludeAddressId) {
                continue;
            }

            if ($localId === $canonicalId) {
                continue;
            }

            $mirror = $this->entityManager->getRepository(Address::class)->find($localId);
            if ($mirror === null || $mirror->isDeleted()) {
                $this->ensureLocalVenueMirror($activity, $deptId);
                continue;
            }

            $this->copyAddressFields($canonical, $mirror);
            $mirror->updateTimestamps();
        }
    }

    /**
     * Adressbuch-Bearbeitung: Eventstandort-Spiegel oder Kanonisch → alle verknüpften Kopien syncen.
     */
    public function syncSharedVenueFromAddressUpdate(Address $updatedAddress): void
    {
        $addressId = (string) $updatedAddress->getId();
        $syncedAny = false;

        /** @var Activity[] $asCanonical */
        $asCanonical = $this->entityManager->getRepository(Activity::class)
            ->createQueryBuilder('a')
            ->where('a.deletedAt IS NULL')
            ->andWhere('a.venueAddressId = :aid')
            ->setParameter('aid', $addressId)
            ->getQuery()
            ->getResult();

        foreach ($asCanonical as $activity) {
            $this->syncMirrorsFromCanonical($activity, $updatedAddress);
            $syncedAny = true;
        }

        /** @var Activity[] $withInvites */
        $withInvites = $this->entityManager->getRepository(Activity::class)
            ->createQueryBuilder('a')
            ->where('a.deletedAt IS NULL')
            ->andWhere('a.invitedDepartments IS NOT NULL')
            ->getQuery()
            ->getResult();

        foreach ($withInvites as $activity) {
            if ((string) ($activity->getVenueAddressId() ?? '') === $addressId) {
                continue;
            }
            if (!$this->activityReferencesLocalVenueMirror($activity, $addressId)) {
                continue;
            }
            $canonical = $activity->getVenueAddress();
            if ($canonical === null) {
                continue;
            }
            $this->copyAddressFields($updatedAddress, $canonical);
            $canonical->updateTimestamps();
            $this->syncMirrorsFromCanonical($activity, $canonical, excludeAddressId: $addressId);
            $syncedAny = true;
        }

        if ($syncedAny) {
            $this->entityManager->flush();
        }
    }

    private function activityReferencesLocalVenueMirror(Activity $activity, string $addressId): bool
    {
        foreach ($activity->getInvitedDepartments() ?? [] as $inv) {
            if (!\is_array($inv)) {
                continue;
            }
            if (($inv['local_venue_address_id'] ?? '') === $addressId) {
                return true;
            }
        }

        return false;
    }

    private function cloneAddressForDepartment(Address $source, string $departmentId): Address
    {
        $mirror = new Address();
        $mirror->setId(IdGenerator::generateUnique($this->entityManager, Address::class));
        $mirror->setScope(Address::SCOPE_DEPARTMENT);
        $mirror->setDepartmentId($departmentId);
        $this->copyAddressFields($source, $mirror);

        return $mirror;
    }

    private function copyAddressFields(Address $from, Address $to): void
    {
        $to->setType($from->getType() ?: 'event');
        $to->setName($from->getName());
        $to->setCompany($from->getCompany());
        $to->setAddressLine2($from->getAddressLine2());
        $to->setStreet($from->getStreet());
        $to->setStreetNumber($from->getStreetNumber());
        $to->setPostalCode($from->getPostalCode());
        $to->setCity($from->getCity());
        $to->setCanton($from->getCanton());
        $to->setCountry($from->getCountry() ?: 'CH');
        $to->setLatitude($from->getLatitude());
        $to->setLongitude($from->getLongitude());
        $to->setContactFirstName($from->getContactFirstName());
        $to->setContactLastName($from->getContactLastName());
        $to->setEmail($from->getEmail());
        $to->setPhone($from->getPhone());
        $to->setMobile($from->getMobile());
        $to->setAdditionalInfo($from->getAdditionalInfo());
        $to->setIsPrimary(false);
    }

    private function findLocalVenueMirrorId(Activity $activity, string $guestDepartmentId): ?string
    {
        foreach ($activity->getInvitedDepartments() ?? [] as $inv) {
            if (!\is_array($inv) || ($inv['id'] ?? '') !== $guestDepartmentId) {
                continue;
            }
            $localId = trim((string) ($inv['local_venue_address_id'] ?? ''));

            return $localId !== '' ? $localId : null;
        }

        return null;
    }

    private function setLocalVenueMirrorId(Activity $activity, string $guestDepartmentId, string $localAddressId): void
    {
        $invites = $activity->getInvitedDepartments() ?? [];
        $changed = false;
        foreach ($invites as &$inv) {
            if (!\is_array($inv) || ($inv['id'] ?? '') !== $guestDepartmentId) {
                continue;
            }
            $inv['local_venue_address_id'] = $localAddressId;
            $changed = true;
            break;
        }
        unset($inv);
        if ($changed) {
            $activity->setInvitedDepartments($invites);
        }
    }

    private function clearLocalVenueMirrors(Activity $activity): void
    {
        $invites = $activity->getInvitedDepartments() ?? [];
        $changed = false;
        foreach ($invites as &$inv) {
            if (!\is_array($inv)) {
                continue;
            }
            if (isset($inv['local_venue_address_id'])) {
                unset($inv['local_venue_address_id']);
                $changed = true;
            }
        }
        unset($inv);
        if ($changed) {
            $activity->setInvitedDepartments($invites);
        }
    }
}
