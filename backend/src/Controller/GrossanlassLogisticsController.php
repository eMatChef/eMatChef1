<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassPackService;
use App\Service\Grossanlass\GrossanlassPlaceService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass', name: 'api_grossanlass_logistics_')]
class GrossanlassLogisticsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassPlaceService $places,
        private GrossanlassPackService $packs,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('/places', name: 'places_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listPlaces(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->places->list($d, $u));
    }

    #[Route('/places', name: 'places_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createPlace(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->places->create($d, $u, is_array($data) ? $data : []),
            201,
        );
    }

    #[Route('/einsaetze/{einsatzId}/packs', name: 'packs_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listPacks(string $departmentId, string $einsatzId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->packs->listForEinsatz($d, $u, $einsatzId),
        );
    }

    #[Route('/einsaetze/{einsatzId}/packs', name: 'packs_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addPack(string $departmentId, string $einsatzId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->packs->addPack($d, $u, $einsatzId),
            201,
        );
    }

    #[Route('/pack-lines/{lineId}', name: 'pack_line_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateLine(string $departmentId, string $lineId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->packs->updateLine($d, $u, $lineId, is_array($data) ? $data : []),
        );
    }

    #[Route('/packs/{packId}/release', name: 'pack_release', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function releasePack(string $departmentId, string $packId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->packs->releaseTrip($d, $u, $packId),
        );
    }

    #[Route('/packs/{packId}/scan-start', name: 'pack_scan_start', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function scanStart(string $departmentId, string $packId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->packs->scanStart($d, $u, $packId),
        );
    }

    #[Route('/packs/{packId}/scan-arrive', name: 'pack_scan_arrive', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function scanArrive(string $departmentId, string $packId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $placeId = trim((string) ($data['place_id'] ?? ''));

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->packs->scanArrive($d, $u, $packId, $placeId),
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
