<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassChargeMovement;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\DepartmentGrossanlassPlace;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Spiegelt Abteilungs-Lageradressen (address.type=storage) in GA-Lagerplätze — Schreibpfad, nicht GET.
 */
final class GrossanlassStorageLagerplatzService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function syncStorageAddress(Address $address): void
    {
        if ($address->getType() !== 'storage' || $address->isDeleted()) {
            return;
        }
        $departmentId = $address->getDepartmentId();
        if ($departmentId === null || $departmentId === '') {
            return;
        }
        $department = $this->entityManager->find(Department::class, $departmentId);
        if (!$department instanceof Department || !$department->isGrossanlass()) {
            return;
        }

        $place = $this->findByStorageAddressId($address->getId());
        if (!$place instanceof DepartmentGrossanlassPlace) {
            $place = $this->createLagerplatz($department, $address);
        } else {
            $this->applyAddressToLagerplatz($address, $place);
        }
        $this->entityManager->flush();
    }

    /**
     * @return list<string> leer = Löschen der Adresse erlaubt
     */
    public function deletionBlockers(Address $address): array
    {
        if ($address->getType() !== 'storage') {
            return [];
        }
        $place = $this->findByStorageAddressId($address->getId());
        if (!$place instanceof DepartmentGrossanlassPlace) {
            return [];
        }
        $department = $place->getDepartment();

        return $this->lagerplatzInUseBlockers($department, $place);
    }

    public function unlinkStorageAddress(Address $address): void
    {
        $place = $this->findByStorageAddressId($address->getId());
        if ($place instanceof DepartmentGrossanlassPlace) {
            $place->setStorageAddress(null);
            $this->entityManager->flush();
        }
    }

    public function findByStorageAddressId(string $addressId): ?DepartmentGrossanlassPlace
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)
            ->findOneBy(['storageAddressId' => $addressId]);

        return $row instanceof DepartmentGrossanlassPlace ? $row : null;
    }

    public function isLagerplatzActiveForInbound(DepartmentGrossanlassPlace $place): bool
    {
        if ($place->getKind() !== GrossanlassPlaceCodes::KIND_LAGERPLATZ) {
            return false;
        }
        $addressId = $place->getStorageAddressId();
        if ($addressId === null || $addressId === '') {
            return true;
        }
        $address = $this->entityManager->find(Address::class, $addressId);

        return $address instanceof Address
            && !$address->isDeleted()
            && $address->getType() === 'storage'
            && $address->getDepartmentId() === $place->getDepartmentId();
    }

    private function createLagerplatz(Department $department, Address $address): DepartmentGrossanlassPlace
    {
        $name = trim((string) ($address->getName() ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Lageradresse ohne Name kann keinen Lagerplatz erhalten');
        }
        $place = new DepartmentGrossanlassPlace();
        $place->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PLACE,
            DepartmentGrossanlassPlace::class,
        ));
        $place->setDepartment($department);
        $place->setName($name);
        $place->setKind(GrossanlassPlaceCodes::KIND_LAGERPLATZ);
        $place->setStorageAddress($address);
        $place->setPublicCode(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PLACE_PUBLIC,
            DepartmentGrossanlassPlace::class,
            'publicCode',
        ));
        $this->applyAddressToLagerplatz($address, $place);
        $this->entityManager->persist($place);

        return $place;
    }

    private function applyAddressToLagerplatz(Address $address, DepartmentGrossanlassPlace $place): void
    {
        if ($place->getDepartmentId() !== $address->getDepartmentId()) {
            throw new \InvalidArgumentException('Lageradresse gehört zu einem anderen Department');
        }
        $name = trim((string) ($address->getName() ?? ''));
        if ($name !== '') {
            $place->setName($name);
        }
        $place->setKind(GrossanlassPlaceCodes::KIND_LAGERPLATZ);
        $place->setStorageAddress($address);
        if ($address->getLatitude() !== null && $address->getLongitude() !== null) {
            $place->setLatitude($address->getLatitude());
            $place->setLongitude($address->getLongitude());
        }
    }

    /**
     * @return list<string>
     */
    private function lagerplatzInUseBlockers(Department $department, DepartmentGrossanlassPlace $place): array
    {
        $blockers = [];
        $placeId = $place->getId();
        if ($this->countChargeMovements($placeId) > 0) {
            $blockers[] = 'Materialbewegungen verweisen auf den Lagerstandort.';
        }
        if ($this->countEinsatzDestinations($department, $placeId) > 0) {
            $blockers[] = 'Noch als Einsatz-Ziel gesetzt.';
        }
        if ($this->countPackLocations($department, $placeId) > 0) {
            $blockers[] = 'Noch als Pack-Standort gesetzt.';
        }

        return $blockers;
    }

    private function countChargeMovements(string $placeId): int
    {
        return (int) $this->entityManager->getRepository(DepartmentGrossanlassChargeMovement::class)
            ->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.placeId = :placeId')
            ->setParameter('placeId', $placeId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countEinsatzDestinations(Department $department, string $placeId): int
    {
        return (int) $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.departmentId = :departmentId')
            ->andWhere('e.destinationPlaceId = :placeId')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('placeId', $placeId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPackLocations(Department $department, string $placeId): int
    {
        return (int) $this->entityManager->getRepository(DepartmentGrossanlassPack::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.departmentId = :departmentId')
            ->andWhere('p.currentPlaceId = :placeId')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('placeId', $placeId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
