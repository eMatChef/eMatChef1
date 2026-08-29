<?php

namespace App\Controller;

use App\Entity\Address;
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
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $accessCheck = $this->ensureGlobalAddressAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $query = trim((string) $request->query->get('q', ''));

        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.scope = :scope')
            ->andWhere('a.type = :type')
            ->setParameter('scope', Address::SCOPE_GLOBAL)
            ->setParameter('type', self::GLOBAL_ADDRESS_TYPE)
            ->andWhere('a.deletedAt IS NULL')
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
                'scope' => Address::SCOPE_GLOBAL,
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

        $data = json_decode($request->getContent(), true) ?: [];

        $hasName = !empty($data['name']);
        $hasCompany = !empty($data['company']);
        if (!$hasName && !$hasCompany) {
            return new JsonResponse(['error' => 'Name oder Firma ist erforderlich'], 400);
        }

        try {
            $address = new Address();
            $address->setId(IdGenerator::generateUnique($this->entityManager, Address::class));
            $address->setScope(Address::SCOPE_GLOBAL);
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
        if (!$address || !$this->isGlobalSupplierAddress($address) || $address->isDeleted()) {
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

        if ($address->isDeleted()) {
            return new JsonResponse(['error' => 'Globale Adresse wurde bereits geloescht'], 410);
        }

        try {
            $user = $this->getUser();
            $address->setDeletedAt(new \DateTime());
            $address->setDeletedByUserId($user instanceof \App\Entity\User ? $user->getId() : null);
            $address->updateTimestamps();
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Globale Adresse in den Papierkorb verschoben']);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Loeschen: ' . $exception->getMessage()], 500);
        }
    }

    private function ensureGlobalAddressAdmin(): JsonResponse|true
    {
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        return true;
    }

    private function isGlobalSupplierAddress(Address $address): bool
    {
        return $address->getScope() === Address::SCOPE_GLOBAL
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
        if (array_key_exists('contact_first_name', $data)) {
            $address->setContactFirstName($data['contact_first_name']);
        }
        if (array_key_exists('contact_last_name', $data)) {
            $address->setContactLastName($data['contact_last_name']);
        }
        if (array_key_exists('contact_salutation', $data)) {
            $address->setContactSalutation($data['contact_salutation'] !== null ? (string) $data['contact_salutation'] : null);
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
}
