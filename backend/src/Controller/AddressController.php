<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/addresses', name: 'api_addresses_')]
class AddressController extends AbstractController
{
    /** Kontakte: User-Rolle – lesbare Typen. */
    private const USER_CONTACT_VIEW_TYPES = ['general', 'storage', 'event', 'meeting'];

    /** Kontakte: User-Rolle – anlegen/bearbeiten/löschen nur diese Typen. */
    private const USER_CONTACT_CREATE_TYPES = ['meeting', 'event'];

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
        
        $role = $this->resolveDepartmentRole((string) $departmentId);
        $includeDeleted = $request->query->get('include_deleted') === '1'
            && $this->canManageDeletedContacts($role);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('a.type', 'ASC')
            ->addOrderBy('a.name', 'ASC');

        if (!$includeDeleted) {
            $qb->andWhere('a.deletedAt IS NULL');
        }
        if ($this->isUserContactReader($role)) {
            $qb->andWhere('a.type IN (:userContactTypes)')
                ->setParameter('userContactTypes', self::USER_CONTACT_VIEW_TYPES);
            if ($type && !in_array($type, self::USER_CONTACT_VIEW_TYPES, true)) {
                $addresses = [];

                return new JsonResponse([
                    'addresses' => [],
                    'types' => $this->filterTypesForRole(Address::getAvailableTypes(), $role),
                    'cantons' => Address::getSwissCantons(),
                ]);
            }
        }

        if ($type) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $type);
        }
        
        $addresses = $qb->getQuery()->getResult();
        
        return new JsonResponse([
            'addresses' => array_map(fn($a) => $a->toArray(), $addresses),
            'types' => $this->filterTypesForRole(Address::getAvailableTypes(), $role),
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

        $this->assertCanViewContact($address);
        if ($address->isDeleted() && !$this->canManageDeletedContacts($this->resolveDepartmentRole($address->getDepartmentId()))) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }
        $role = $this->resolveDepartmentRole($address->getDepartmentId());
        
        return new JsonResponse([
            'address' => $address->toArray(),
            'types' => $this->filterTypesForRole(Address::getAvailableTypes(), $role),
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

        $requestedType = (string) ($data['type'] ?? 'general');
        try {
            $this->assertCanCreateContact((string) $data['department_id'], $requestedType);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
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
        
        if (!$address || $address->isDeleted()) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }

        try {
            $this->assertCanViewContact($address);
            $this->assertCanModifyContact($address);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        
        try {
            if (isset($data['type'])) {
                $this->assertCanCreateContact($address->getDepartmentId(), (string) $data['type']);
            }
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
            $this->assertCanViewContact($address);
            $this->assertCanModifyContact($address);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        if ($address->isDeleted()) {
            return new JsonResponse(['error' => 'Adresse wurde bereits gelöscht'], 410);
        }

        try {
            $user = $this->getUser();
            $address->setDeletedAt(new \DateTime());
            $address->setDeletedByUserId($user instanceof User ? $user->getId() : null);
            $address->setIsPrimary(false);
            $address->updateTimestamps();
            $this->entityManager->flush();

            return new JsonResponse([
                'message' => 'Adresse in den Papierkorb verschoben',
                'address' => $address->toArray(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Löschen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Gelöschte Adresse wiederherstellen (nur MW/DC).
     */
    #[Route('/{id}/restore', name: 'restore', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function restore(string $id): JsonResponse
    {
        $address = $this->entityManager->getRepository(Address::class)->find($id);

        if (!$address) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }

        try {
            $this->assertCanManageDeletedContacts($this->resolveDepartmentRole($address->getDepartmentId()));
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        if (!$address->isDeleted()) {
            return new JsonResponse(['error' => 'Adresse ist nicht gelöscht'], 400);
        }

        $address->setDeletedAt(null);
        $address->setDeletedByUserId(null);
        $address->updateTimestamps();
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Adresse wiederhergestellt',
            'address' => $address->toArray(),
        ]);
    }

    /**
     * Adresse endgültig löschen (nur MW/DC, nur aus dem Papierkorb).
     */
    #[Route('/{id}/permanent', name: 'permanent_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function permanentDelete(string $id): JsonResponse
    {
        $address = $this->entityManager->getRepository(Address::class)->find($id);

        if (!$address) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }

        try {
            $this->assertCanManageDeletedContacts($this->resolveDepartmentRole($address->getDepartmentId()));
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        if (!$address->isDeleted()) {
            return new JsonResponse(['error' => 'Nur gelöschte Adressen können endgültig entfernt werden'], 400);
        }

        try {
            $this->entityManager->remove($address);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Adresse endgültig gelöscht']);
        } catch (\Exception $e) {
            $errorText = (string) $e->getMessage();
            if (str_contains($errorText, 'fk_storage_rack_address') || str_contains($errorText, 'storage_rack')) {
                return new JsonResponse([
                    'error' => 'Adresse kann nicht gelöscht werden, solange Regale diesem Lagerstandort zugewiesen sind.',
                ], 409);
            }

            return new JsonResponse([
                'error' => 'Fehler beim endgültigen Löschen: ' . $errorText,
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

        if ($address->isDeleted()) {
            return new JsonResponse(['error' => 'Gelöschte Adresse kann nicht als primär gesetzt werden'], 400);
        }

        try {
            $this->assertCanViewContact($address);
            $this->assertCanModifyContact($address);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
        
        try {
            // Alle anderen Adressen gleichen Typs im Department auf nicht-primär setzen
            $sameTypeAddresses = $this->entityManager->createQueryBuilder()
                ->select('a')
                ->from(Address::class, 'a')
                ->where('a.departmentId = :departmentId')
                ->andWhere('a.type = :type')
                ->andWhere('a.deletedAt IS NULL')
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

    private function resolveDepartmentRole(string $departmentId): ?string
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);

        return $membership ? strtolower(trim((string) $membership->getRole())) : null;
    }

    private function isUserContactReader(?string $role): bool
    {
        return $role !== null && in_array($role, ['u', 'user'], true);
    }

    private function assertCanCreateContact(string $departmentId, string $type): void
    {
        $role = $this->resolveDepartmentRole($departmentId);
        if ($this->isUserContactReader($role) && !in_array($type, self::USER_CONTACT_CREATE_TYPES, true)) {
            throw new AccessDeniedHttpException('Nur Treffpunkt und Eventstandort dürfen angelegt werden');
        }
    }

    private function assertCanModifyContact(Address $address): void
    {
        $role = $this->resolveDepartmentRole($address->getDepartmentId());
        if ($this->isUserContactReader($role) && !in_array($address->getType(), self::USER_CONTACT_CREATE_TYPES, true)) {
            throw new AccessDeniedHttpException('Keine Berechtigung zum Bearbeiten dieses Kontakts');
        }
    }

    private function assertCanViewContact(Address $address): void
    {
        $role = $this->resolveDepartmentRole($address->getDepartmentId());
        if ($this->isUserContactReader($role) && !in_array($address->getType(), self::USER_CONTACT_VIEW_TYPES, true)) {
            throw new AccessDeniedHttpException('Keine Berechtigung für diesen Kontakt');
        }
    }

    /** MW/DC: Papierkorb einsehen, wiederherstellen, endgültig löschen. */
    private function canManageDeletedContacts(?string $role): bool
    {
        return $role !== null && in_array($role, ['mw', 'dc', 'matwart', 'depchef'], true);
    }

    private function assertCanManageDeletedContacts(?string $role): void
    {
        if (!$this->canManageDeletedContacts($role)) {
            throw new AccessDeniedHttpException('Nur Materialwart und Depchef dürfen gelöschte Adressen verwalten');
        }
    }

    /**
     * @param array<string, string> $allTypes
     * @return array<string, string>
     */
    private function filterTypesForRole(array $allTypes, ?string $role): array
    {
        if (!$this->isUserContactReader($role)) {
            return $allTypes;
        }

        return array_intersect_key($allTypes, array_flip(self::USER_CONTACT_VIEW_TYPES));
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
        if (array_key_exists('contact_first_name', $data)) {
            $address->setContactFirstName($data['contact_first_name']);
        }
        if (array_key_exists('contact_last_name', $data)) {
            $address->setContactLastName($data['contact_last_name']);
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
