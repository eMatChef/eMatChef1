<?php

namespace App\Controller;

use App\Entity\DepartmentDisplayDevice;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\User;
use App\Service\Display\DepartmentDisplayDeviceService;
use App\Service\Display\DepartmentDisplayScreenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Geräteverwaltung der Infoscreens eines Departments (auch Grossanlässe). Alle Aktionen prüfen serverseitig
 * die Verwaltungsrechte und dass das Gerät zum Department gehört.
 */
#[Route('/api/departments/{departmentId}/display-devices', name: 'api_department_display_devices_')]
#[IsGranted('ROLE_USER')]
class DepartmentDisplayDeviceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentDisplayScreenService $screenService,
        private DepartmentDisplayDeviceService $deviceService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(string $departmentId): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return new JsonResponse(array_map(
            fn (DepartmentDisplayDevice $d) => $this->deviceService->serialize($d),
            $this->deviceService->listForDepartment($departmentId),
        ));
    }

    /** Umbenennen und/oder einem anderen Infoscreen desselben Departments zuweisen. */
    #[Route('/{deviceId}', name: 'update', methods: ['PATCH'])]
    public function update(string $departmentId, string $deviceId, Request $request): JsonResponse
    {
        return $this->act($departmentId, $deviceId, function (DepartmentDisplayDevice $device) use ($departmentId, $request) {
            $data = json_decode($request->getContent(), true);
            $data = is_array($data) ? $data : [];
            if (\array_key_exists('screen_id', $data)) {
                $target = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find((string) $data['screen_id']);
                if (!$target instanceof DepartmentDisplayScreen || $target->getDepartmentId() !== $departmentId) {
                    return new JsonResponse(['error' => 'Screen nicht gefunden'], 404);
                }
                if ($target->getId() !== $device->getScreenId()) {
                    $this->deviceService->reassign($device, $target);
                }
            }
            if (\array_key_exists('name', $data)) {
                $this->deviceService->rename($device, (string) $data['name']);
            }

            return null;
        });
    }

    #[Route('/{deviceId}/extend', name: 'extend', methods: ['POST'])]
    public function extend(string $departmentId, string $deviceId): JsonResponse
    {
        return $this->act($departmentId, $deviceId, function (DepartmentDisplayDevice $device) {
            $this->deviceService->extend($device);

            return null;
        });
    }

    #[Route('/{deviceId}/revoke', name: 'revoke', methods: ['POST'])]
    public function revoke(string $departmentId, string $deviceId): JsonResponse
    {
        return $this->act($departmentId, $deviceId, function (DepartmentDisplayDevice $device) {
            $this->deviceService->revoke($device);

            return null;
        });
    }

    #[Route('/{deviceId}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $departmentId, string $deviceId): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $device = $this->deviceService->findInDepartment($departmentId, $deviceId);
        if ($device === null) {
            return new JsonResponse(['error' => 'Gerät nicht gefunden'], 404);
        }
        try {
            $this->deviceService->deletePermanently($device, $user);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        }

        return new JsonResponse(null, 204);
    }

    /**
     * @param callable(DepartmentDisplayDevice): ?JsonResponse $action
     */
    private function act(string $departmentId, string $deviceId, callable $action): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $device = $this->deviceService->findInDepartment($departmentId, $deviceId);
        if ($device === null) {
            return new JsonResponse(['error' => 'Gerät nicht gefunden'], 404);
        }
        try {
            $error = $action($device);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        }
        if ($error instanceof JsonResponse) {
            return $error;
        }

        return new JsonResponse($this->deviceService->serialize($device));
    }

    private function requireManager(string $departmentId): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        if (!$this->screenService->canManageDepartment($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return $user;
    }
}
