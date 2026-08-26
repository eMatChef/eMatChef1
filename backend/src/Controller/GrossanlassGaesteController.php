<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassGaesteService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass/gaeste', name: 'api_grossanlass_gaeste_')]
class GrossanlassGaesteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassGaesteService $gaeste,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->gaeste->overview($d, $u));
    }

    #[Route('/shares/{shareId}/accept', name: 'accept', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function accept(string $departmentId, string $shareId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->gaeste->accept($d, $u, $shareId),
        );
    }

    #[Route('/sales', name: 'sell', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sell(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->gaeste->sell($d, $u, $data),
            201,
        );
    }

    /**
     * @param callable(Department, User): mixed $fn
     */
    private function handle(string $departmentId, callable $fn, int $okStatus = 200): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        if (!$department->isGrossanlass()) {
            return new JsonResponse(['error' => 'Kein Grossanlass-Department'], 400);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($user->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }
        try {
            return new JsonResponse($fn($department, $user), $okStatus);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }
}
