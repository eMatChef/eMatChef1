<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\Onboarding\OnboardingSandboxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/onboarding-sandbox', name: 'api_department_onboarding_sandbox_')]
class OnboardingSandboxController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OnboardingSandboxService $sandboxService,
    ) {}

    #[Route('', name: 'ensure', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function ensure(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer dieses Department'], 403);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($data)) {
            $data = [];
        }
        $forTourId = isset($data['forTourId']) ? trim((string) $data['forTourId']) : null;
        if ($forTourId === '') {
            $forTourId = null;
        }

        try {
            $result = $this->sandboxService->ensure(
                $department,
                $user,
                $forTourId,
                !empty($data['reset']),
            );
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Onboarding-Sandbox konnte nicht bereitgestellt werden',
                'detail' => $e->getMessage(),
            ], 500);
        }

        return new JsonResponse($result);
    }
}
