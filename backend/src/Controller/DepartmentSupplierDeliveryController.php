<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\SupplierDelivery;
use App\Entity\User;
use App\Repository\SupplierDeliveryRepository;
use App\Service\Supplier\SupplierImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/supplier-deliveries', name: 'api_department_supplier_deliveries_')]
class DepartmentSupplierDeliveryController extends AbstractController
{
    private const READ_ROLES = ['mw', 'dc', 'matwart', 'depchef'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierDeliveryRepository $deliveryRepository,
        private SupplierImportService $importService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->canReadDepartmentDeliveries($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $statusParam = strtolower(trim((string) $request->query->get('status', SupplierDelivery::STATUS_SUBMITTED)));
        $statuses = match ($statusParam) {
            'submitted' => [SupplierDelivery::STATUS_SUBMITTED],
            'open' => [SupplierDelivery::STATUS_SUBMITTED],
            'imported' => [SupplierDelivery::STATUS_IMPORTED],
            'all' => [SupplierDelivery::STATUS_SUBMITTED, SupplierDelivery::STATUS_IMPORTED],
            default => [SupplierDelivery::STATUS_SUBMITTED],
        };

        $deliveries = $this->deliveryRepository->findByDepartmentAndStatuses($departmentId, $statuses);

        return new JsonResponse([
            'deliveries' => array_map(
                static fn (SupplierDelivery $delivery) => $delivery->toArray(),
                $deliveries
            ),
        ]);
    }

    #[Route('/{deliveryId}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(string $departmentId, string $deliveryId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->canReadDepartmentDeliveries($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $delivery = $this->deliveryRepository->find($deliveryId);
        if (
            !$delivery instanceof SupplierDelivery
            || $delivery->getDepartmentId() !== $departmentId
            || !\in_array($delivery->getStatus(), [SupplierDelivery::STATUS_SUBMITTED, SupplierDelivery::STATUS_IMPORTED], true)
        ) {
            return new JsonResponse(['error' => 'Übergabe nicht gefunden'], 404);
        }

        return new JsonResponse(['delivery' => $delivery->toArray()]);
    }

    #[Route('/{deliveryId}/import', name: 'import', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function import(string $departmentId, string $deliveryId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->canReadDepartmentDeliveries($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $result = $this->importService->importDelivery($departmentId, $deliveryId, $data);

            return new JsonResponse([
                'delivery' => $result['delivery'],
                'materials' => $result['materials'],
                'message' => 'Übergabe importiert',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Import fehlgeschlagen: ' . $e->getMessage()], 500);
        }
    }

    private function canReadDepartmentDeliveries(User $user, string $departmentId): bool
    {
        if ($this->isGranted('ROLE_SUPERADMIN')) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership instanceof Membership) {
            return false;
        }

        return \in_array(strtolower(trim($membership->getRole())), self::READ_ROLES, true);
    }
}
