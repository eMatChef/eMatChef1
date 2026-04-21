<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\Organisation;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\Bootstrap\GlobalSystemSeedDefaults;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/global-addresses', name: 'api_global_addresses_')]
class GlobalAddressController extends AbstractController
{
    private const GLOBAL_ADDRESS_TYPE = 'supplier';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        // Lesezugriff für alle eingeloggten User (z.B. Material-Wizard Hersteller/Lieferant)
        $this->ensureGlobalScope();

        $query = trim((string) $request->query->get('q', ''));

        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('a.type = :type')
            ->setParameter('departmentId', GlobalSystemSeedDefaults::DEPARTMENT_ID)
            ->setParameter('type', self::GLOBAL_ADDRESS_TYPE)
            ->orderBy('a.company', 'ASC')
            ->addOrderBy('a.name', 'ASC');

        if ($query !== '') {
            $qb->andWhere('LOWER(COALESCE(a.name, \'\')) LIKE :q OR LOWER(COALESCE(a.company, \'\')) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $addresses = $qb->getQuery()->getResult();

        return new JsonResponse([
            'addresses' => array_map(fn (Address $address) => $this->toApiAddress($address), $addresses),
            'meta' => [
                'department_id' => GlobalSystemSeedDefaults::DEPARTMENT_ID,
                'type' => self::GLOBAL_ADDRESS_TYPE,
            ],
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $accessCheck = $this->ensureGlobalAddressAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $this->ensureGlobalScope();
        $data = json_decode($request->getContent(), true) ?: [];

        $hasName = !empty($data['name']);
        $hasCompany = !empty($data['company']);
        if (!$hasName && !$hasCompany) {
            return new JsonResponse(['error' => 'Name oder Firma ist erforderlich'], 400);
        }

        try {
            $address = new Address();
            $address->setId(IdGenerator::generateUnique($this->entityManager, Address::class));
            $address->setDepartmentId(GlobalSystemSeedDefaults::DEPARTMENT_ID);
            $address->setType(self::GLOBAL_ADDRESS_TYPE);

            $this->updateAddressFromData($address, $data);

            $this->entityManager->persist($address);
            $this->entityManager->flush();

            return new JsonResponse([
                'address' => $this->toApiAddress($address),
                'message' => 'Globale Adresse erstellt',
            ], 201);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $accessCheck = $this->ensureGlobalAddressAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $address = $this->entityManager->getRepository(Address::class)->find($id);
        if (!$address || !$this->isGlobalSupplierAddress($address)) {
            return new JsonResponse(['error' => 'Globale Adresse nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $this->updateAddressFromData($address, $data);
            $address->updateTimestamps();

            $this->entityManager->flush();

            return new JsonResponse([
                'address' => $this->toApiAddress($address),
                'message' => 'Globale Adresse aktualisiert',
            ]);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $accessCheck = $this->ensureGlobalAddressAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $address = $this->entityManager->getRepository(Address::class)->find($id);
        if (!$address || !$this->isGlobalSupplierAddress($address)) {
            return new JsonResponse(['error' => 'Globale Adresse nicht gefunden'], 404);
        }

        try {
            $this->entityManager->remove($address);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Globale Adresse geloescht']);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Loeschen: ' . $exception->getMessage()], 500);
        }
    }

    private function ensureGlobalAddressAdmin(): JsonResponse|true
    {
        if (
            !$this->isGranted('ROLE_SUPERADMIN') &&
            !$this->isGranted('ROLE_ORGANISATIONSCHEF') &&
            !$this->isGranted('ROLE_SUBORGCHEF')
        ) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        return true;
    }

    private function isGlobalSupplierAddress(Address $address): bool
    {
        return $address->getDepartmentId() === GlobalSystemSeedDefaults::DEPARTMENT_ID
            && $address->getType() === self::GLOBAL_ADDRESS_TYPE;
    }

    private function toApiAddress(Address $address): array
    {
        $payload = $address->toArray();
        $payload['is_default'] = false;
        return $payload;
    }

    private function updateAddressFromData(Address $address, array $data): void
    {
        if (array_key_exists('name', $data)) {
            $address->setName($data['name']);
        }
        if (array_key_exists('company', $data)) {
            $address->setCompany($data['company']);
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
        if (array_key_exists('country', $data) && $data['country'] !== null && $data['country'] !== '') {
            $address->setCountry((string) $data['country']);
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
    }

    private function ensureGlobalScope(): void
    {
        $organisation = $this->entityManager->getRepository(Organisation::class)->find(GlobalSystemSeedDefaults::ORGANISATION_ID);
        if (!$organisation) {
            $organisation = new Organisation();
            $organisation->setId(GlobalSystemSeedDefaults::ORGANISATION_ID);
            $organisation->setName('Global System');
            $this->entityManager->persist($organisation);
        }

        $department = $this->entityManager->getRepository(Department::class)->find(GlobalSystemSeedDefaults::DEPARTMENT_ID);
        $createdGlobalDepartment = false;
        if (!$department) {
            $department = new Department();
            $department->setId(GlobalSystemSeedDefaults::DEPARTMENT_ID);
            $department->setName('Global Suppliers');
            $department->setOrganisation($organisation);
            $this->entityManager->persist($department);
            $createdGlobalDepartment = true;
        }

        $this->entityManager->flush();

        if ($createdGlobalDepartment && $department !== null) {
            $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($this->entityManager, $department);
        }
    }
}
