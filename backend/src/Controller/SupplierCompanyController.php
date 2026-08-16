<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\SupplierCompany;
use App\Entity\SupplierMembership;
use App\Entity\User;
use App\Repository\SupplierCompanyRepository;
use App\Security\Voter\SupplierCompanyVoter;
use App\Service\Supplier\SupplierCompanyAccessService;
use App\Service\Supplier\SupplierCompanyFactory;
use App\Service\Supplier\SupplierDashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/supplier-companies', name: 'api_supplier_companies_')]
class SupplierCompanyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierCompanyRepository $supplierCompanyRepository,
        private SupplierCompanyAccessService $accessService,
        private SupplierCompanyFactory $supplierCompanyFactory,
        private SupplierDashboardService $dashboardService,
    ) {
    }

    /**
     * Öffentliche Liste aktiver Firmen für MW (Picker, Import).
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $status = strtolower(trim((string) $request->query->get('status', SupplierCompany::STATUS_ACTIVE)));
        if ($status !== SupplierCompany::STATUS_ACTIVE) {
            return new JsonResponse(['error' => 'Nur status=active wird unterstützt'], 400);
        }

        $companies = $this->supplierCompanyRepository->findByStatus(SupplierCompany::STATUS_ACTIVE);

        return new JsonResponse([
            'supplier_companies' => array_map(
                fn (SupplierCompany $company) => $this->serializePublicCompany($company),
                $companies
            ),
        ]);
    }

    #[Route('/{id}/dashboard', name: 'dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'id')]
    public function dashboard(string $id): JsonResponse
    {
        $company = $this->requireCompany($id);

        return new JsonResponse([
            'dashboard' => $this->dashboardService->getDashboard($company),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'id')]
    public function show(string $id): JsonResponse
    {
        $user = $this->requireUser();
        $company = $this->requireCompany($id);

        return new JsonResponse([
            'supplier_company' => $this->serializeProfileCompany($company, $user),
        ]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'id')]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if (!$this->accessService->isCompanyAdmin($user, $id)) {
            return new JsonResponse(['error' => 'Nur Firmen-Admin darf das Profil bearbeiten'], 403);
        }

        $company = $this->requireCompany($id);
        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $syncCompanyName = null;
            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);
                if ($name === '') {
                    return new JsonResponse(['error' => 'name darf nicht leer sein'], 400);
                }
                $company->setName($name);
                $syncCompanyName = $name;
            }
            if (array_key_exists('manufacturer_key', $data)) {
                $company->setManufacturerKey($this->nullableString($data['manufacturer_key']));
            }

            if (array_key_exists('operator_enabled', $data)) {
                $operatorError = $this->applyOperatorSettings($company, $user, $data);
                if ($operatorError instanceof JsonResponse) {
                    return $operatorError;
                }
            } elseif (
                $company->hasCapability(SupplierCompany::CAPABILITY_OPERATOR)
                && array_key_exists('linked_department_id', $data)
            ) {
                $operatorError = $this->applyOperatorSettings($company, $user, [
                    'operator_enabled' => true,
                    'linked_department_id' => $data['linked_department_id'],
                ]);
                if ($operatorError instanceof JsonResponse) {
                    return $operatorError;
                }
            }

            $address = $this->resolveSupplierAddress($company);
            if (\is_array($data['address'] ?? null)) {
                $this->supplierCompanyFactory->applyAddressPatch($address, $data['address'], $syncCompanyName);
                $address->updateTimestamps();
            } elseif ($syncCompanyName !== null) {
                $this->supplierCompanyFactory->applyAddressPatch($address, [], $syncCompanyName);
                $address->updateTimestamps();
            }

            $company->updateTimestamps();
            $this->entityManager->flush();

            return new JsonResponse([
                'supplier_company' => $this->serializeProfileCompany($company, $user),
                'message' => 'Profil gespeichert',
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'manufacturer_key ist bereits vergeben'], 409);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Speichern: ' . $exception->getMessage()], 500);
        }
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function requireCompany(string $id): SupplierCompany
    {
        $company = $this->supplierCompanyRepository->find($id);
        if (!$company instanceof SupplierCompany) {
            throw $this->createNotFoundException('Supplier-Firma nicht gefunden');
        }

        return $company;
    }

    private function resolveSupplierAddress(SupplierCompany $company): Address
    {
        $address = $company->getSupplierAddress();
        if ($address === null && $company->getSupplierAddressId()) {
            $address = $this->entityManager->find(Address::class, $company->getSupplierAddressId());
        }
        if (!$address instanceof Address) {
            throw new \RuntimeException('Supplier-Adresse fehlt');
        }
        if ($address->getScope() !== Address::SCOPE_SUPPLIER) {
            throw new \RuntimeException('Ungültige Supplier-Adresse');
        }

        return $address;
    }

    /** @return array<string, mixed> */
    private function serializePublicCompany(SupplierCompany $company): array
    {
        $address = $company->getSupplierAddress();
        if ($address === null && $company->getSupplierAddressId()) {
            $address = $this->entityManager->find(Address::class, $company->getSupplierAddressId());
        }

        return [
            'id' => $company->getId(),
            'name' => $company->getName(),
            'manufacturer_key' => $company->getManufacturerKey(),
            'address' => $address?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeProfileCompany(SupplierCompany $company, User $user): array
    {
        $address = $company->getSupplierAddress();
        if ($address === null && $company->getSupplierAddressId()) {
            $address = $this->entityManager->find(Address::class, $company->getSupplierAddressId());
        }

        $membership = $this->accessService->getMembership($user, (string) $company->getId());
        $role = $membership instanceof SupplierMembership ? $membership->getRole() : SupplierMembership::ROLE_MEMBER;

        return [
            'id' => $company->getId(),
            'name' => $company->getName(),
            'manufacturer_key' => $company->getManufacturerKey(),
            'supplier_address_id' => $company->getSupplierAddressId(),
            'status' => $company->getStatus(),
            'capabilities' => $company->getCapabilities(),
            'operator_enabled' => $company->hasCapability(SupplierCompany::CAPABILITY_OPERATOR),
            'linked_department_id' => $company->getLinkedDepartmentId(),
            'linked_department' => $this->serializeLinkedDepartment($company),
            'has_linked_department_membership' => $this->userHasLinkedDepartmentMembership($user, $company),
            'eligible_operator_departments' => $this->serializeEligibleOperatorDepartments($user),
            'address' => $address?->toArray(),
            'role' => $role,
            'can_edit' => $role === SupplierMembership::ROLE_ADMIN,
            'created_at' => $company->getCreatedAt()->format('c'),
            'updated_at' => $company->getUpdatedAt()->format('c'),
        ];
    }

    /** @param array<string, mixed> $data */
    private function applyOperatorSettings(SupplierCompany $company, User $user, array $data): ?JsonResponse
    {
        $enabled = filter_var($data['operator_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $capabilities = $company->getCapabilities();

        if (!$enabled) {
            $company->setCapabilities(array_values(array_filter(
                $capabilities,
                static fn (string $capability): bool => $capability !== SupplierCompany::CAPABILITY_OPERATOR
            )));
            $company->setLinkedDepartmentId(null);

            return null;
        }

        $linkedDepartmentId = $this->nullableString($data['linked_department_id'] ?? null);
        if ($linkedDepartmentId === null) {
            return new JsonResponse(['error' => 'linked_department_id ist für Operator erforderlich'], 400);
        }

        $department = $this->entityManager->find(Department::class, $linkedDepartmentId);
        if (!$department instanceof Department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        if (!$this->userHasDepartmentMembership($user, $linkedDepartmentId)) {
            return new JsonResponse([
                'error' => 'Du brauchst eine eigene Department-Membership im gewählten Department',
            ], 403);
        }

        if (!\in_array(SupplierCompany::CAPABILITY_OPERATOR, $capabilities, true)) {
            $capabilities[] = SupplierCompany::CAPABILITY_OPERATOR;
        }
        $company->setCapabilities(array_values(array_unique($capabilities)));
        $company->setLinkedDepartmentId($linkedDepartmentId);

        return null;
    }

    private function userHasDepartmentMembership(User $user, string $departmentId): bool
    {
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);

        return $membership instanceof Membership;
    }

    private function userHasLinkedDepartmentMembership(User $user, SupplierCompany $company): bool
    {
        $linkedDepartmentId = $company->getLinkedDepartmentId();
        if ($linkedDepartmentId === null || !$company->hasCapability(SupplierCompany::CAPABILITY_OPERATOR)) {
            return false;
        }

        return $this->userHasDepartmentMembership($user, $linkedDepartmentId);
    }

    /** @return array<string, mixed>|null */
    private function serializeLinkedDepartment(SupplierCompany $company): ?array
    {
        $linkedDepartmentId = $company->getLinkedDepartmentId();
        if ($linkedDepartmentId === null) {
            return null;
        }

        $department = $company->getLinkedDepartment();
        if ($department === null) {
            $department = $this->entityManager->find(Department::class, $linkedDepartmentId);
        }
        if (!$department instanceof Department) {
            return [
                'id' => $linkedDepartmentId,
                'name' => null,
                'organisation_id' => null,
                'organisation_name' => null,
            ];
        }

        return [
            'id' => $department->getId(),
            'name' => $department->getName(),
            'organisation_id' => $department->getOrganisationId(),
            'organisation_name' => $department->getOrganisation()->getName(),
        ];
    }

    /** @return list<array{department_id: string, name: string, organisation_name: string, role: string}> */
    private function serializeEligibleOperatorDepartments(User $user): array
    {
        $memberships = $this->entityManager->getRepository(Membership::class)->findBy(['userId' => $user->getId()]);
        $items = [];
        foreach ($memberships as $membership) {
            if (!$membership instanceof Membership) {
                continue;
            }
            $department = $membership->getDepartment();
            $items[] = [
                'department_id' => $department->getId(),
                'name' => $department->getName(),
                'organisation_name' => $department->getOrganisation()->getName(),
                'role' => $membership->getRole(),
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $items;
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
