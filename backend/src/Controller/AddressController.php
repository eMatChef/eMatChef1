<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Department;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/addresses', name: 'api_addresses_')]
class AddressController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Liste aller Adressen eines Departments (Multi-Tenant!)
     * WICHTIG: department_id ist erforderlich für Multi-Tenant-Isolation
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');
        $type = $request->query->get('type');
        
        // Multi-Tenant: department_id ist erforderlich!
        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        
        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('a.type', 'ASC')
            ->addOrderBy('a.name', 'ASC');
        
        if ($type) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $type);
        }
        
        $addresses = $qb->getQuery()->getResult();
        
        return new JsonResponse([
            'addresses' => array_map(fn($a) => $a->toArray(), $addresses),
            'types' => Address::getAvailableTypes(),
            'cantons' => Address::getSwissCantons(),
        ]);
    }

    /**
     * Einzelne Adresse abrufen
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
    {
        $address = $this->entityManager->getRepository(Address::class)->find($id);
        
        if (!$address) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }
        
        return new JsonResponse([
            'address' => $address->toArray(),
            'types' => Address::getAvailableTypes(),
            'cantons' => Address::getSwissCantons(),
        ]);
    }

    /**
     * Neue Adresse erstellen
     * WICHTIG: department_id ist erforderlich für Multi-Tenant-Isolation
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validierung - Multi-Tenant: department_id ist erforderlich!
        if (empty($data['department_id'])) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        
        // Mindestens Name, Firma oder Adresse muss vorhanden sein
        $hasName = !empty($data['name']) || !empty($data['company']);
        $hasAddress = !empty($data['street']) || !empty($data['city']);
        $hasCoordinates = !empty($data['latitude']) && !empty($data['longitude']);
        
        if (!$hasName && !$hasAddress && !$hasCoordinates) {
            return new JsonResponse(['error' => 'Mindestens Name, Adresse oder Standort ist erforderlich'], 400);
        }
        
        // Prüfe ob Department existiert
        $department = $this->entityManager->getRepository(Department::class)->find($data['department_id']);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        
        try {
            $address = new Address();
            $address->setId(IdGenerator::generateUnique($this->entityManager, Address::class));
            $address->setDepartmentId($data['department_id']);
            
            $this->updateAddressFromData($address, $data);
            
            $this->entityManager->persist($address);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'address' => $address->toArray(),
                'message' => 'Adresse erstellt'
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adresse aktualisieren
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $address = $this->entityManager->getRepository(Address::class)->find($id);
        
        if (!$address) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        
        try {
            $this->updateAddressFromData($address, $data);
            $address->updateTimestamps();
            
            $this->entityManager->flush();
            
            return new JsonResponse([
                'address' => $address->toArray(),
                'message' => 'Adresse aktualisiert'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adresse löschen
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $address = $this->entityManager->getRepository(Address::class)->find($id);
        
        if (!$address) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }
        
        try {
            $this->entityManager->remove($address);
            $this->entityManager->flush();
            
            return new JsonResponse(['message' => 'Adresse gelöscht']);
        } catch (\Exception $e) {
            $errorText = (string) $e->getMessage();
            if (str_contains($errorText, 'fk_storage_rack_address') || str_contains($errorText, 'storage_rack')) {
                return new JsonResponse([
                    'error' => 'Adresse kann nicht gelöscht werden, solange Regale diesem Lagerstandort zugewiesen sind.'
                ], 409);
            }
            return new JsonResponse([
                'error' => 'Fehler beim Löschen: ' . $errorText
            ], 500);
        }
    }

    /**
     * Verfügbare Typen und Kantone abrufen
     */
    #[Route('/meta/options', name: 'options', methods: ['GET'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function options(): JsonResponse
    {
        return new JsonResponse([
            'types' => Address::getAvailableTypes(),
            'cantons' => Address::getSwissCantons(),
        ]);
    }

    /**
     * Adresse als primär setzen (für diesen Typ im Department)
     */
    #[Route('/{id}/set-primary', name: 'set_primary', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function setPrimary(string $id): JsonResponse
    {
        $address = $this->entityManager->getRepository(Address::class)->find($id);
        
        if (!$address) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }
        
        try {
            // Alle anderen Adressen gleichen Typs im Department auf nicht-primär setzen
            $sameTypeAddresses = $this->entityManager->createQueryBuilder()
                ->select('a')
                ->from(Address::class, 'a')
                ->where('a.departmentId = :departmentId')
                ->andWhere('a.type = :type')
                ->setParameter('departmentId', $address->getDepartmentId())
                ->setParameter('type', $address->getType())
                ->getQuery()
                ->getResult();
            
            foreach ($sameTypeAddresses as $addr) {
                $addr->setIsPrimary($addr->getId() === $id);
            }
            
            $this->entityManager->flush();
            
            return new JsonResponse([
                'address' => $address->toArray(),
                'message' => 'Primäre Adresse gesetzt'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hilfsmethode: Adresse aus Request-Daten aktualisieren
     */
    private function updateAddressFromData(Address $address, array $data): void
    {
        if (isset($data['type'])) {
            $address->setType($data['type']);
        }
        if (array_key_exists('name', $data)) {
            $address->setName($data['name']);
        }
        if (array_key_exists('company', $data)) {
            $address->setCompany($data['company']);
        }
        if (array_key_exists('address_line2', $data)) {
            $address->setAddressLine2($data['address_line2']);
        }
        if (array_key_exists('street', $data)) {
            $address->setStreet($data['street']);
        }
        if (array_key_exists('street_number', $data)) {
            $address->setStreetNumber($data['street_number']);
        }
        if (array_key_exists('postal_code', $data)) {
            $address->setPostalCode($data['postal_code']);
        }
        if (array_key_exists('city', $data)) {
            $address->setCity($data['city']);
        }
        if (array_key_exists('canton', $data)) {
            $address->setCanton($data['canton']);
        }
        if (isset($data['country'])) {
            $address->setCountry($data['country']);
        }
        if (array_key_exists('latitude', $data)) {
            $address->setLatitude($data['latitude'] !== null ? (float) $data['latitude'] : null);
        }
        if (array_key_exists('longitude', $data)) {
            $address->setLongitude($data['longitude'] !== null ? (float) $data['longitude'] : null);
        }
        if (array_key_exists('email', $data)) {
            $address->setEmail($data['email']);
        }
        if (array_key_exists('phone', $data)) {
            $address->setPhone($data['phone']);
        }
        if (array_key_exists('mobile', $data)) {
            $address->setMobile($data['mobile']);
        }
        if (array_key_exists('additional_info', $data)) {
            $address->setAdditionalInfo($data['additional_info']);
        }
        if (isset($data['is_primary'])) {
            $address->setIsPrimary((bool) $data['is_primary']);
        }
    }
}
