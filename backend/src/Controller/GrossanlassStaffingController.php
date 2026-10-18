<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Grossanlass\GrossanlassBauprojektService;
use App\Service\Grossanlass\GrossanlassHelperPoolService;
use App\Service\Grossanlass\GrossanlassStaffingAssignmentService;
use App\Service\Grossanlass\GrossanlassStaffingConflict;
use App\Service\Grossanlass\GrossanlassStaffingCoverageService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Helferpool V1: Helfer, Verfügbarkeit, Personalbedarf, Zuteilung, Zeitplan, Konflikte. Dünne Schicht über den Services;
 * die Berechtigung prüfen die Services (Verwaltung und Zuteilung nur GA-HV/GA-SUBHV), hier nur Mitgliedschaft und die
 * Leseberechtigung der Übersicht. Konflikte: HTTP 409 mit `code: staffing_conflict`.
 */
#[Route('/api/departments/{departmentId}/grossanlass', name: 'api_grossanlass_staffing_')]
class GrossanlassStaffingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassAccessService $access,
        private readonly GroupAccessService $groupAccess,
        private readonly GrossanlassHelperPoolService $helpers,
        private readonly GrossanlassStaffingAssignmentService $assignments,
        private readonly GrossanlassStaffingCoverageService $coverage,
        private readonly GrossanlassBauprojektService $bauprojekt,
    ) {
    }

    // ── Helfer ────────────────────────────────────────────────────────────────

    #[Route('/helpers', name: 'helpers_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listHelpers(string $departmentId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->list($d, $u, [
            'status' => (string) $request->query->get('status', 'active'),
            'q' => (string) $request->query->get('q', ''),
            'origin_department_id' => (string) $request->query->get('origin_department_id', ''),
            'limit' => $request->query->getInt('limit', 200),
            'offset' => $request->query->getInt('offset', 0),
        ]));
    }

    #[Route('/helpers', name: 'helpers_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createHelper(string $departmentId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->create($d, $u, $this->body($request)), 201);
    }

    #[Route('/helper-skills', name: 'helper_skills_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listSkills(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->listSkills($d));
    }

    #[Route('/helper-skills', name: 'helper_skills_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addSkill(string $departmentId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->addSkill($d, $u, $this->body($request)['name'] ?? ''), 201);
    }

    #[Route('/helper-linkable-users', name: 'helper_linkable_users', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function linkableUsers(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->linkableUsers($d, $u));
    }

    #[Route('/helpers/{helperId}', name: 'helpers_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getHelper(string $departmentId, string $helperId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->get($d, $u, $helperId));
    }

    #[Route('/helpers/{helperId}', name: 'helpers_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateHelper(string $departmentId, string $helperId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->update($d, $u, $helperId, $this->body($request)));
    }

    #[Route('/helpers/{helperId}/archive', name: 'helpers_archive', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function archiveHelper(string $departmentId, string $helperId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->archive($d, $u, $helperId));
    }

    #[Route('/helpers/{helperId}/restore', name: 'helpers_restore', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function restoreHelper(string $departmentId, string $helperId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->restore($d, $u, $helperId));
    }

    // ── Verfügbarkeit ─────────────────────────────────────────────────────────

    #[Route('/helpers/{helperId}/availability', name: 'availability_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listAvailability(string $departmentId, string $helperId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->listAvailability($d, $u, $helperId));
    }

    #[Route('/helpers/{helperId}/availability', name: 'availability_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addAvailability(string $departmentId, string $helperId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->addAvailability($d, $u, $helperId, $data['starts_at'] ?? null, $data['ends_at'] ?? null), 201);
    }

    #[Route('/helper-availability/{availabilityId}', name: 'availability_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateAvailability(string $departmentId, string $availabilityId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->helpers->updateAvailability($d, $u, $availabilityId, $data['starts_at'] ?? null, $data['ends_at'] ?? null));
    }

    #[Route('/helper-availability/{availabilityId}', name: 'availability_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeAvailability(string $departmentId, string $availabilityId): JsonResponse
    {
        return $this->handle($departmentId, function (Department $d, User $u) use ($availabilityId): array {
            $this->helpers->removeAvailability($d, $u, $availabilityId);

            return ['deleted' => true];
        });
    }

    // ── Personalbedarf (am Bauprojekt) ────────────────────────────────────────

    #[Route('/groups/{groupId}/staffing-needs', name: 'needs_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listNeeds(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->bauprojekt->listStaffingNeeds($d, $u, $this->group($d, $groupId)));
    }

    #[Route('/groups/{groupId}/staffing-needs', name: 'needs_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createNeed(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->bauprojekt->createStaffingNeed($d, $u, $this->group($d, $groupId), $this->body($request)), 201);
    }

    #[Route('/groups/{groupId}/staffing-needs/{needId}', name: 'needs_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateNeed(string $departmentId, string $groupId, string $needId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->bauprojekt->updateStaffingNeed($d, $u, $this->group($d, $groupId), $needId, $this->body($request)));
    }

    #[Route('/groups/{groupId}/staffing-needs/{needId}', name: 'needs_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteNeed(string $departmentId, string $groupId, string $needId): JsonResponse
    {
        return $this->handle($departmentId, function (Department $d, User $u) use ($groupId, $needId): array {
            $this->bauprojekt->deleteStaffingNeed($d, $u, $this->group($d, $groupId), $needId);

            return ['deleted' => true];
        });
    }

    // ── Deckung, Zeitplan, Konflikte ──────────────────────────────────────────

    /** Übersicht ohne Helfernamen. Query: group_ids (kommagetrennt), from, to. */
    #[Route('/staffing/overview', name: 'overview', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function overview(string $departmentId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, function (Department $d, User $u) use ($request): array {
            if (!$this->access->canSeeStaffingOverview($u, $d)) {
                throw new \RuntimeException('Keine Berechtigung für den Personalbedarf');
            }
            $filter = [];
            $groupIds = array_values(array_filter(array_map('trim', explode(',', (string) $request->query->get('group_ids', '')))));
            if ($groupIds !== []) {
                $filter['group_ids'] = $groupIds;
            }
            if ($request->query->get('from') && $request->query->get('to')) {
                $filter['from'] = (string) $request->query->get('from');
                $filter['to'] = (string) $request->query->get('to');
            }

            return $this->coverage->overview($d, $filter) + ['can_manage' => $this->access->canManageStaffing($u, $d)];
        });
    }

    /** Ein Bedarf mit zugeteilten Helfern (Namen) — nur GA-HV/GA-SUBHV. */
    #[Route('/staffing/needs/{needId}', name: 'need_detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function needDetail(string $departmentId, string $needId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->coverage->needDetail($d, $u, $needId));
    }

    #[Route('/staffing/schedule', name: 'schedule', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function schedule(string $departmentId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->coverage->schedule($d, $u, $request->query->get('from'), $request->query->get('to')));
    }

    #[Route('/staffing/conflicts', name: 'conflicts', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function conflicts(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->coverage->conflicts($d, $u));
    }

    // ── Zuteilung ─────────────────────────────────────────────────────────────

    #[Route('/staffing/assignments', name: 'assign', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function assign(string $departmentId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->assignments->assign($d, $u, $this->body($request)), 201);
    }

    #[Route('/staffing/assignments/{assignmentId}', name: 'assignment_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateAssignment(string $departmentId, string $assignmentId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->assignments->update($d, $u, $assignmentId, $this->body($request)));
    }

    #[Route('/staffing/assignments/{assignmentId}', name: 'assignment_cancel', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function cancelAssignment(string $departmentId, string $assignmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->assignments->cancel($d, $u, $assignmentId));
    }

    // ── intern ────────────────────────────────────────────────────────────────

    /** @param callable(Department, User): mixed $fn */
    private function handle(string $departmentId, callable $fn, int $okStatus = 200): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
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
        } catch (GrossanlassStaffingConflict $e) {
            return new JsonResponse($e->toPayload(), 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], str_contains($e->getMessage(), 'nicht gefunden') ? 404 : 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    private function group(Department $department, string $groupId): Group
    {
        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if (!$group instanceof Group || $group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Gruppe nicht gefunden');
        }

        return $group;
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }
}
