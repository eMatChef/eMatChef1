<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\SupplierCompany;
use App\Entity\SupplierMembership;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Repository\SupplierCompanyRepository;
use App\Repository\UserRepository;
use App\Service\Supplier\SupplierCompanyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/supplier-companies', name: 'api_admin_supplier_companies_')]
class SupplierCompanyAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierCompanyFactory $supplierCompanyFactory,
        private SupplierCompanyRepository $supplierCompanyRepository,
        private UserRepository $userRepository,
        private ProfileRepository $profileRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        $accessCheck = $this->ensurePlatformAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $companies = $this->supplierCompanyRepository->findBy([], ['name' => 'ASC']);

        return new JsonResponse([
            'supplier_companies' => array_map(
                fn (SupplierCompany $company) => $this->serializeAdminCompany($company),
                $companies
            ),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $accessCheck = $this->ensurePlatformAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name ist erforderlich'], 400);
        }

        try {
            $status = SupplierCompanyFactory::normalizeStatus((string) ($data['status'] ?? SupplierCompany::STATUS_ACTIVE));
            $capabilities = SupplierCompanyFactory::normalizeCapabilities((array) ($data['capabilities'] ?? []));
            $linkedDepartmentId = $this->nullableString($data['linked_department_id'] ?? null);
            if ($linkedDepartmentId !== null && !$this->entityManager->find(Department::class, $linkedDepartmentId)) {
                return new JsonResponse(['error' => 'linked_department_id nicht gefunden'], 404);
            }

            $company = $this->supplierCompanyFactory->createWithAddress(
                name: $name,
                addressData: \is_array($data['address'] ?? null) ? $data['address'] : [],
                manufacturerKey: $this->nullableString($data['manufacturer_key'] ?? null),
                capabilities: $capabilities,
                status: $status,
                linkedDepartmentId: $linkedDepartmentId,
            );

            $membership = $this->assignAdminUser($company, $data, true);

            return new JsonResponse([
                'supplier_company' => $this->serializeAdminCompany($company),
                'membership' => $membership?->toArray(),
                'message' => 'Supplier-Firma erstellt',
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'manufacturer_key ist bereits vergeben'], 409);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/promote-global-address/{addressId}', name: 'promote_global_address', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function promoteGlobalAddress(string $addressId, Request $request): JsonResponse
    {
        $accessCheck = $this->ensurePlatformAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $address = $this->entityManager->find(Address::class, $addressId);
        if (!$address) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $status = SupplierCompanyFactory::normalizeStatus((string) ($data['status'] ?? SupplierCompany::STATUS_ACTIVE));
            $capabilities = SupplierCompanyFactory::normalizeCapabilities((array) ($data['capabilities'] ?? []));
            $linkedDepartmentId = $this->nullableString($data['linked_department_id'] ?? null);
            if ($linkedDepartmentId !== null && !$this->entityManager->find(Department::class, $linkedDepartmentId)) {
                return new JsonResponse(['error' => 'linked_department_id nicht gefunden'], 404);
            }

            $company = $this->supplierCompanyFactory->promoteGlobalAddress(
                globalAddress: $address,
                name: $this->nullableString($data['name'] ?? null),
                manufacturerKey: $this->nullableString($data['manufacturer_key'] ?? null),
                capabilities: $capabilities,
                status: $status,
                linkedDepartmentId: $linkedDepartmentId,
            );

            $membership = $this->assignAdminUser($company, $data, true);

            return new JsonResponse([
                'supplier_company' => $this->serializeAdminCompany($company),
                'address' => $address->toArray(),
                'membership' => $membership?->toArray(),
                'message' => 'Globale Adresse als Supplier-Firma aktiviert',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'manufacturer_key ist bereits vergeben'], 409);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Aktivieren: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $accessCheck = $this->ensurePlatformAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $company = $this->supplierCompanyRepository->find($id);
        if (!$company) {
            return new JsonResponse(['error' => 'Supplier-Firma nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);
                if ($name === '') {
                    return new JsonResponse(['error' => 'name darf nicht leer sein'], 400);
                }
                $company->setName($name);
            }
            if (array_key_exists('manufacturer_key', $data)) {
                $company->setManufacturerKey($this->nullableString($data['manufacturer_key']));
            }
            if (array_key_exists('capabilities', $data)) {
                $company->setCapabilities(SupplierCompanyFactory::normalizeCapabilities((array) $data['capabilities']));
            }
            if (array_key_exists('status', $data)) {
                $company->setStatus(SupplierCompanyFactory::normalizeStatus((string) $data['status']));
            }
            if (array_key_exists('linked_department_id', $data)) {
                $linkedDepartmentId = $this->nullableString($data['linked_department_id']);
                if ($linkedDepartmentId !== null && !$this->entityManager->find(Department::class, $linkedDepartmentId)) {
                    return new JsonResponse(['error' => 'linked_department_id nicht gefunden'], 404);
                }
                $company->setLinkedDepartmentId($linkedDepartmentId);
            }

            $company->updateTimestamps();
            $this->entityManager->flush();

            return new JsonResponse([
                'supplier_company' => $this->serializeAdminCompany($company),
                'message' => 'Supplier-Firma aktualisiert',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'manufacturer_key ist bereits vergeben'], 409);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{id}/memberships', name: 'add_membership', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addMembership(string $id, Request $request): JsonResponse
    {
        $accessCheck = $this->ensurePlatformAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $company = $this->supplierCompanyRepository->find($id);
        if (!$company) {
            return new JsonResponse(['error' => 'Supplier-Firma nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $membership = $this->assignAdminUser($company, $data, false);
            if (!$membership) {
                return new JsonResponse(['error' => 'admin_user_id oder admin_user_email ist erforderlich'], 400);
            }

            return new JsonResponse([
                'membership' => $membership->toArray(),
                'message' => 'Membership angelegt',
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    private function ensurePlatformAdmin(): JsonResponse|true
    {
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function serializeAdminCompany(SupplierCompany $company): array
    {
        $payload = $company->toArray();
        $address = $company->getSupplierAddress();
        if ($address === null && $company->getSupplierAddressId()) {
            $address = $this->entityManager->find(Address::class, $company->getSupplierAddressId());
        }
        $payload['address'] = $address?->toArray();
        $payload['membership_count'] = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(sm.userId)')
            ->from(SupplierMembership::class, 'sm')
            ->where('sm.supplierCompanyId = :companyId')
            ->setParameter('companyId', $company->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function assignAdminUser(SupplierCompany $company, array $data, bool $isPrimaryDefault): ?SupplierMembership
    {
        $user = $this->resolveUser(
            $this->nullableString($data['admin_user_id'] ?? null),
            $this->nullableString($data['admin_user_email'] ?? null),
        );
        if (!$user) {
            return null;
        }

        $existing = $this->entityManager->getRepository(SupplierMembership::class)->findOneBy([
            'supplierCompanyId' => $company->getId(),
            'userId' => $user->getId(),
        ]);
        if ($existing instanceof SupplierMembership) {
            if (($data['admin_role'] ?? SupplierMembership::ROLE_ADMIN) === SupplierMembership::ROLE_ADMIN) {
                $existing->setRole(SupplierMembership::ROLE_ADMIN);
                $this->entityManager->flush();
            }

            return $existing;
        }

        $role = (string) ($data['admin_role'] ?? SupplierMembership::ROLE_ADMIN);
        if (!\in_array($role, [SupplierMembership::ROLE_ADMIN, SupplierMembership::ROLE_MEMBER], true)) {
            throw new \InvalidArgumentException('Ungültige admin_role');
        }

        return $this->supplierCompanyFactory->addMembership(
            $company,
            $user,
            $role,
            (bool) ($data['is_primary'] ?? $isPrimaryDefault),
        );
    }

    private function resolveUser(?string $userId, ?string $email): ?User
    {
        if ($userId !== null && $userId !== '') {
            $user = $this->userRepository->find($userId);

            return $user instanceof User ? $user : null;
        }

        if ($email !== null && $email !== '') {
            $profile = $this->profileRepository->findOneBy(['email' => mb_strtolower(trim($email))]);
            if (!$profile) {
                throw new \InvalidArgumentException('User mit dieser E-Mail nicht gefunden');
            }
            $user = $this->userRepository->findOneByProfileId($profile->getId());

            return $user instanceof User ? $user : null;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
