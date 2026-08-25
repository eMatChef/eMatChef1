<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassPlanungService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass', name: 'api_grossanlass_planung_')]
class GrossanlassPlanungController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassPlanungService $planung,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('/planung', name: 'overview', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function overview(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $department, User $user) => $this->planung->overview($department, $user));
    }

    #[Route('/planung', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle($departmentId, fn (Department $department, User $user) => $this->planung->update($department, $user, $data));
    }

    #[Route('/planung/activities', name: 'create_activity', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createActivity(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->planung->createPhaseActivity($department, $user, $data),
            201,
        );
    }

    #[Route('/publish', name: 'publish', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function publish(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $department, User $user) => $this->planung->publish($department, $user));
    }

    #[Route('/planung/participants/search', name: 'participants_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function searchParticipants(string $departmentId, Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));

        return $this->handle($departmentId, fn (Department $department, User $user) => $this->planung->searchGuests($department, $user, $q));
    }

    #[Route('/planung/participants', name: 'participants_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addParticipant(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $guestId = trim((string) ($data['guest_department_id'] ?? ''));

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->planung->addParticipant($department, $user, $guestId),
            201,
        );
    }

    #[Route('/planung/participants/{participantId}', name: 'participants_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeParticipant(string $departmentId, string $participantId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->planung->removeParticipant($department, $user, $participantId),
        );
    }

    #[Route('/invites/{participantId}/respond', name: 'invite_respond', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function respondInvite(string $departmentId, string $participantId, Request $request): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($user->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        $decision = trim((string) ($data['decision'] ?? ''));
        $groupId = isset($data['group_id']) ? trim((string) $data['group_id']) : null;

        try {
            return new JsonResponse($this->planung->respondInvite($department, $user, $participantId, $decision, $groupId));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
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
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
