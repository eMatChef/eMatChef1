<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassAvailabilityConflict;
use App\Service\Grossanlass\GrossanlassBauprojektService;
use App\Service\Grossanlass\GrossanlassDispatchService;
use App\Service\Grossanlass\GrossanlassInboundService;
use App\Service\Grossanlass\GrossanlassMapService;
use App\Service\Grossanlass\GrossanlassPackService;
use App\Service\Grossanlass\GrossanlassPlaceService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        private GrossanlassBauprojektService $bauprojekt,
        private GrossanlassMapService $maps,
        private GrossanlassPackService $packs,
        private GroupAccessService $groupAccess,
        private GrossanlassDispatchService $dispatch,
        private GrossanlassInboundService $inbound,
    ) {}

    #[Route('/logistics/dispatch', name: 'dispatch_overview', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function dispatchOverview(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->overview($d, $u));
    }

    #[Route('/inbound/workspace', name: 'inbound_workspace', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function inboundWorkspace(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->inbound->workspace($d, $u));
    }

    #[Route('/inbound/receive', name: 'inbound_receive', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function inboundReceive(string $departmentId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->inbound->receiveBatch($d, $u, $data),
            201,
        );
    }

    #[Route('/logistics/tours', name: 'tours_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createTour(string $departmentId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->createTour($d, $u, $data), 201);
    }

    #[Route('/logistics/tours/{tourId}', name: 'tours_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateTour(string $departmentId, string $tourId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->updateTour($d, $u, $tourId, $data));
    }

    #[Route('/logistics/tours/{tourId}', name: 'tours_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteTour(string $departmentId, string $tourId): JsonResponse
    {
        return $this->handle($departmentId, function (Department $d, User $u) use ($tourId) {
            $this->dispatch->deleteTour($d, $u, $tourId);

            return ['deleted' => true];
        });
    }

    #[Route('/logistics/tours/{tourId}/needs', name: 'tours_need_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addTourNeed(string $departmentId, string $tourId, Request $request): JsonResponse
    {
        $needId = (string) ($this->body($request)['need_id'] ?? '');

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->addTourNeed($d, $u, $tourId, $needId));
    }

    #[Route('/logistics/tours/{tourId}/needs/{needId}', name: 'tours_need_remove', methods: ['DELETE'], requirements: ['needId' => '.+'])]
    #[IsGranted('ROLE_USER')]
    public function removeTourNeed(string $departmentId, string $tourId, string $needId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->removeTourNeed($d, $u, $tourId, $needId));
    }

    #[Route('/logistics/tours/{tourId}/stops', name: 'tours_stops', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function replaceTourStops(string $departmentId, string $tourId, Request $request): JsonResponse
    {
        $stops = $this->body($request)['stops'] ?? [];

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->replaceTourStops($d, $u, $tourId, is_array($stops) ? array_values(array_filter($stops, 'is_array')) : []));
    }

    #[Route('/logistics/tours/{tourId}/confirm', name: 'tours_confirm', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function confirmTour(string $departmentId, string $tourId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->confirmTour($d, $u, $tourId));
    }

    #[Route('/logistics/tours/{tourId}/unconfirm', name: 'tours_unconfirm', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unconfirmTour(string $departmentId, string $tourId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->unconfirmTour($d, $u, $tourId));
    }

    #[Route('/logistics/needs/{needId}/release', name: 'need_release', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function releaseNeed(string $departmentId, string $needId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->releaseNeed($d, $u, $needId));
    }

    #[Route('/logistics/needs/{needId}/convert', name: 'need_convert', methods: ['POST'], requirements: ['needId' => '.+'])]
    #[IsGranted('ROLE_USER')]
    public function convertNeed(string $departmentId, string $needId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->convertVehicleNeed($d, $u, $needId), 201);
    }

    #[Route('/logistics/needs/{needId}/revoke-release', name: 'need_revoke_release', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function revokeNeedRelease(string $departmentId, string $needId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->revokeNeedRelease($d, $u, $needId));
    }

    #[Route('/logistics/needs/{needId}/change-requests', name: 'need_change_request', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function requestChange(string $departmentId, string $needId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->requestChange($d, $u, $needId, $data), 201);
    }

    #[Route('/logistics/change-requests', name: 'change_requests_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function changeRequests(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->changeRequests($d, $u));
    }

    #[Route('/logistics/change-requests/{requestId}/decision', name: 'change_requests_decide', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function decideChangeRequest(string $departmentId, string $requestId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->decideChangeRequest($d, $u, $requestId, $data));
    }

    #[Route('/logistics/tours/{tourId}/release', name: 'tours_release', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function releaseTour(string $departmentId, string $tourId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->releaseTour($d, $u, $tourId));
    }

    #[Route('/logistics/tours/{tourId}/close', name: 'tours_close', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function closeTour(string $departmentId, string $tourId, Request $request): JsonResponse
    {
        $note = (string) ($this->body($request)['note'] ?? '');

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->dispatch->closeTour($d, $u, $tourId, $note));
    }

    #[Route('/packs/{packId}/arrive-manual', name: 'pack_arrive_manual', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function arriveManually(string $departmentId, string $packId, Request $request): JsonResponse
    {
        $reason = (string) ($this->body($request)['reason'] ?? '');

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->packs->arriveManually($d, $u, $packId, $reason));
    }

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

    #[Route('/places/{placeId}', name: 'places_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updatePlace(string $departmentId, string $placeId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->places->update($d, $u, $placeId, is_array($data) ? $data : []),
        );
    }

    #[Route('/places/{placeId}', name: 'places_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deletePlace(string $departmentId, string $placeId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            function (Department $d, User $u) use ($placeId): array {
                $this->places->delete($d, $u, $placeId);

                return ['ok' => true];
            },
        );
    }

    #[Route('/places/{placeId}/briefing', name: 'places_briefing', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function placeBriefing(string $departmentId, string $placeId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->bauprojekt->briefingForPlace($d, $u, $placeId),
        );
    }

    #[Route('/maps', name: 'maps_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listMaps(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->maps->list($d, $u));
    }

    #[Route('/maps', name: 'maps_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createMap(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->maps->create($d, $u, is_array($data) ? $data : []),
            201,
        );
    }

    #[Route('/maps/{mapId}', name: 'maps_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateMap(string $departmentId, string $mapId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->maps->update($d, $u, $mapId, is_array($data) ? $data : []),
        );
    }

    #[Route('/maps/{mapId}/background', name: 'maps_background', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadMapBackground(string $departmentId, string $mapId, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Datei fehlt'], 400);
        }
        $bounds = [];
        foreach (['bounds_north', 'bounds_south', 'bounds_east', 'bounds_west'] as $key) {
            $value = $request->request->get($key);
            if ($value !== null && $value !== '') {
                $bounds[$key] = $value;
            }
        }

        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->maps->uploadBackground($d, $u, $mapId, $file, $bounds),
        );
    }

    #[Route('/maps/{mapId}/background', name: 'maps_background_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteMapBackground(string $departmentId, string $mapId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $d, User $u) => $this->maps->removeBackground($d, $u, $mapId),
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

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
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
        } catch (GrossanlassAvailabilityConflict $e) {
            return new JsonResponse($e->toPayload(), 409);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }
}
