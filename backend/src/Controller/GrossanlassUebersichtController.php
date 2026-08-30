<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassUebersichtService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass/uebersicht', name: 'api_grossanlass_uebersicht_')]
class GrossanlassUebersichtController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassUebersichtService $uebersicht,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->uebersicht->overview($d, $u));
    }

    #[Route('/submit-board', name: 'submit_board', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function submitBoard(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->uebersicht->submitBoard($d, $u));
    }

    #[Route('/einsaetze', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->uebersicht->createEinsatz($d, $u, $data),
            201,
        );
    }

    #[Route('/einsaetze/{einsatzId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $einsatzId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->uebersicht->updateEinsatz($d, $u, $einsatzId, $data),
        );
    }

    #[Route('/einsaetze/{einsatzId}/issue', name: 'issue', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function issue(string $departmentId, string $einsatzId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->uebersicht->issueEinsatz($d, $u, $einsatzId, $data),
        );
    }

    #[Route('/commitments/{commitmentId}', name: 'commitment_ops', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function commitmentOps(string $departmentId, string $commitmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->uebersicht->updateCommitmentOps($d, $u, $commitmentId, $data),
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
