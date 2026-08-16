<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SupplierCompany;
use App\Entity\SupplierMembership;
use App\Entity\User;
use App\Repository\SupplierCompanyRepository;
use App\Repository\SupplierMembershipRepository;
use App\Security\Voter\SupplierCompanyVoter;
use App\Service\Supplier\SupplierCompanyAccessService;
use App\Service\Supplier\SupplierCompanyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/supplier-companies', name: 'api_supplier_companies_memberships_')]
class SupplierMembershipController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierCompanyRepository $supplierCompanyRepository,
        private SupplierMembershipRepository $supplierMembershipRepository,
        private SupplierCompanyAccessService $accessService,
        private SupplierCompanyFactory $supplierCompanyFactory,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $frontendUrl,
    ) {
    }

    #[Route('/join', name: 'join', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function join(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $data = json_decode($request->getContent(), true) ?: [];
        $joinCode = trim((string) ($data['join_code'] ?? ''));

        if ($joinCode === '') {
            return new JsonResponse(['error' => 'join_code ist erforderlich'], 400);
        }

        try {
            $membership = $this->supplierCompanyFactory->joinCompanyByCode($user, $joinCode);
            $company = $membership->getSupplierCompany();
            $user->setLastUsedSupplierCompany($company);
            $this->entityManager->flush();

            return new JsonResponse([
                'supplier_company_id' => $company->getId(),
                'supplier_company_name' => $company->getName(),
                'role' => $membership->getRole(),
                'auto_joined' => true,
                'redirect_path' => '/supplier/' . $company->getId() . '/dashboard',
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }
    }

    #[Route('/{companyId}/memberships', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function listMemberships(string $companyId): JsonResponse
    {
        $user = $this->requireUser();
        if (!$this->accessService->isCompanyAdmin($user, $companyId)) {
            return new JsonResponse(['error' => 'Nur Firmen-Admin darf das Team einsehen'], 403);
        }

        $memberships = $this->supplierMembershipRepository->findByCompanyId($companyId);

        return new JsonResponse([
            'memberships' => array_map(
                fn (SupplierMembership $membership) => $this->serializeMembership($membership),
                $memberships
            ),
        ]);
    }

    #[Route('/{companyId}/memberships/{userId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function updateMembership(string $companyId, string $userId, Request $request): JsonResponse
    {
        $actor = $this->requireUser();
        if (!$this->accessService->isCompanyAdmin($actor, $companyId)) {
            return new JsonResponse(['error' => 'Nur Firmen-Admin darf Rollen ändern'], 403);
        }

        $membership = $this->supplierMembershipRepository->findOneBy([
            'supplierCompanyId' => $companyId,
            'userId' => $userId,
        ]);
        if (!$membership instanceof SupplierMembership) {
            return new JsonResponse(['error' => 'Membership nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $role = strtolower(trim((string) ($data['role'] ?? '')));
        if (!\in_array($role, [SupplierMembership::ROLE_ADMIN, SupplierMembership::ROLE_MEMBER], true)) {
            return new JsonResponse(['error' => 'Ungültige Rolle'], 400);
        }

        if (
            $membership->getRole() === SupplierMembership::ROLE_ADMIN
            && $role === SupplierMembership::ROLE_MEMBER
            && $this->supplierMembershipRepository->countAdminsForCompany($companyId) <= 1
        ) {
            return new JsonResponse(['error' => 'Der letzte Firmen-Admin kann nicht herabgestuft werden'], 409);
        }

        $membership->setRole($role);
        $this->entityManager->flush();

        return new JsonResponse([
            'membership' => $this->serializeMembership($membership),
            'message' => 'Rolle aktualisiert',
        ]);
    }

    #[Route('/{companyId}/memberships/{userId}', name: 'remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function removeMembership(string $companyId, string $userId): JsonResponse
    {
        $actor = $this->requireUser();
        if (!$this->accessService->isCompanyAdmin($actor, $companyId)) {
            return new JsonResponse(['error' => 'Nur Firmen-Admin darf Mitglieder entfernen'], 403);
        }

        $membership = $this->supplierMembershipRepository->findOneBy([
            'supplierCompanyId' => $companyId,
            'userId' => $userId,
        ]);
        if (!$membership instanceof SupplierMembership) {
            return new JsonResponse(['error' => 'Membership nicht gefunden'], 404);
        }

        if (
            $membership->getRole() === SupplierMembership::ROLE_ADMIN
            && $this->supplierMembershipRepository->countAdminsForCompany($companyId) <= 1
        ) {
            return new JsonResponse(['error' => 'Der letzte Firmen-Admin kann nicht entfernt werden'], 409);
        }

        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Mitglied entfernt']);
    }

    #[Route('/{companyId}/join-code', name: 'join_code_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function getJoinCode(string $companyId): JsonResponse
    {
        $user = $this->requireUser();
        if (!$this->accessService->isCompanyAdmin($user, $companyId)) {
            return new JsonResponse(['error' => 'Nur Firmen-Admin darf den Join-Code einsehen'], 403);
        }

        $company = $this->requireCompany($companyId);
        $code = $this->supplierCompanyFactory->ensureJoinCode($company);

        return new JsonResponse($this->buildJoinCodeResponse($company, $code));
    }

    #[Route('/{companyId}/join-code/regenerate', name: 'join_code_regenerate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function regenerateJoinCode(string $companyId): JsonResponse
    {
        $user = $this->requireUser();
        if (!$this->accessService->isCompanyAdmin($user, $companyId)) {
            return new JsonResponse(['error' => 'Nur Firmen-Admin darf den Join-Code erneuern'], 403);
        }

        $company = $this->requireCompany($companyId);
        $code = $this->supplierCompanyFactory->regenerateJoinCode($company);

        return new JsonResponse($this->buildJoinCodeResponse($company, $code));
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function requireCompany(string $companyId): SupplierCompany
    {
        $company = $this->supplierCompanyRepository->find($companyId);
        if (!$company instanceof SupplierCompany) {
            throw $this->createNotFoundException('Supplier-Firma nicht gefunden');
        }

        return $company;
    }

    /** @return array<string, mixed> */
    private function serializeMembership(SupplierMembership $membership): array
    {
        $profile = $membership->getUser()->getProfile();

        return [
            'supplier_company_id' => $membership->getSupplierCompanyId(),
            'user_id' => $membership->getUserId(),
            'role' => $membership->getRole(),
            'is_primary' => $membership->getIsPrimary(),
            'name' => $profile?->getDisplayName() ?? 'Unbekannt',
            'email' => $profile?->getEmail(),
        ];
    }

    /** @return array<string, mixed> */
    private function buildJoinCodeResponse(SupplierCompany $company, string $joinCode): array
    {
        $frontendBase = rtrim($this->frontendUrl, '/');
        $inviteUrl = $frontendBase . '/pending-assignment?join_code=' . urlencode($joinCode);

        return [
            'supplier_company_id' => $company->getId(),
            'supplier_company_name' => $company->getName(),
            'join_code' => $joinCode,
            'invite_url' => $inviteUrl,
            'updated_at' => $company->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
