<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Membership;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Entity\User;
use App\Service\Print\MaterialQrPdfService;
use App\Service\Public\PublicCodeService;
use App\Service\Storage\StorageQrService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/storage-qr', name: 'api_storage_qr_')]
class StorageQrController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicCodeService $publicCodeService,
        private StorageQrService $storageQrService,
        private MaterialQrPdfService $materialQrPdfService,
    ) {}

    #[Route('/addresses/{id}/ensure', name: 'ensure_address', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function ensureAddress(string $id): JsonResponse
    {
        /** @var Address|null $address */
        $address = $this->entityManager->getRepository(Address::class)->find($id);
        if (!$address || $address->getDeletedAt() !== null) {
            return new JsonResponse(['error' => 'Lagerstandort nicht gefunden'], 404);
        }
        if ($address->getType() !== 'storage') {
            return new JsonResponse(['error' => 'Adresse ist kein Lagerstandort'], 400);
        }

        $access = $this->assertDepartmentAccess($address->getDepartmentId());
        if ($access instanceof JsonResponse) {
            return $access;
        }

        try {
            $payload = $this->storageQrService->ensureAddressQr($address, $this->getActorUserId());
            $this->entityManager->flush();
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($payload);
    }

    #[Route('/racks/{id}/ensure', name: 'ensure_rack', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function ensureRack(string $id): JsonResponse
    {
        /** @var StorageRack|null $rack */
        $rack = $this->entityManager->getRepository(StorageRack::class)->find($id);
        if (!$rack) {
            return new JsonResponse(['error' => 'Regal nicht gefunden'], 404);
        }

        $access = $this->assertDepartmentAccess($rack->getDepartmentId());
        if ($access instanceof JsonResponse) {
            return $access;
        }

        try {
            $payload = $this->storageQrService->ensureRackQr($rack, $this->getActorUserId());
            $this->entityManager->flush();
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($payload);
    }

    #[Route('/slots/{id}/ensure', name: 'ensure_slot', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function ensureSlot(string $id): JsonResponse
    {
        /** @var StorageSlot|null $slot */
        $slot = $this->entityManager->getRepository(StorageSlot::class)->find($id);
        if (!$slot) {
            return new JsonResponse(['error' => 'Fach nicht gefunden'], 404);
        }

        $access = $this->assertDepartmentAccess($slot->getRack()->getDepartmentId());
        if ($access instanceof JsonResponse) {
            return $access;
        }

        try {
            $payload = $this->storageQrService->ensureSlotQr($slot, $this->getActorUserId());
            $this->entityManager->flush();
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($payload);
    }

    #[Route('/queue-print', name: 'queue_print', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function queuePrint(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $scope = trim((string) ($data['scope'] ?? ''));
        $addressId = isset($data['address_id']) ? trim((string) $data['address_id']) : null;
        $rackId = isset($data['rack_id']) ? trim((string) $data['rack_id']) : null;
        $slotId = isset($data['slot_id']) ? trim((string) $data['slot_id']) : null;

        if ($departmentId === '' || $scope === '') {
            return new JsonResponse(['error' => 'department_id und scope sind erforderlich'], 400);
        }

        $access = $this->assertDepartmentAccess($departmentId);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        try {
            $result = $this->storageQrService->queuePrint(
                $departmentId,
                $scope,
                $addressId,
                $rackId,
                $slotId,
                $user,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($result, 201);
    }

    /**
     * PDF-Export für einen Lagerstandort mit frei wählbaren QR-Etiketten (A4-Raster 3×4).
     */
    #[Route('/pdf', name: 'pdf', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function exportPdf(Request $request): Response
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $addressId = trim((string) ($data['address_id'] ?? ''));
        $selections = $data['selections'] ?? null;

        if ($departmentId === '' || $addressId === '') {
            return new JsonResponse(['error' => 'department_id und address_id sind erforderlich'], 400);
        }
        if (!is_array($selections)) {
            return new JsonResponse(['error' => 'selections[] ist erforderlich'], 400);
        }

        $access = $this->assertDepartmentAccess($departmentId);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        try {
            $rows = $this->storageQrService->collectPdfRows(
                $departmentId,
                $addressId,
                $selections,
                $this->getActorUserId(),
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'QR-Codes konnten nicht gesammelt werden: ' . $e->getMessage()], 500);
        }

        $locationLabel = 'Lagerstandort';
        foreach ($rows as $row) {
            if ($row->lineLabel === 'Lagerstandort') {
                $locationLabel = $row->materialName;
                break;
            }
        }

        $title = $locationLabel . ' – Lager-QR';
        try {
            $pdfBinary = $this->materialQrPdfService->renderPdf($rows, $title);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'PDF konnte nicht erzeugt werden: ' . $e->getMessage()], 500);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $locationLabel) ?: 'lagerstandort';
        $filename = 'lager-qr-' . $safeName . '.pdf';

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    #[Route('/lookup/l/{code}', name: 'lookup_address', methods: ['GET'])]
    #[Route('/lookup/r/{code}', name: 'lookup_rack', methods: ['GET'])]
    #[Route('/lookup/s/{code}', name: 'lookup_slot', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function lookup(Request $request, string $code): JsonResponse
    {
        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $access = $this->assertDepartmentAccess($departmentId);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $path = $request->getPathInfo();
        $entityType = match (true) {
            str_contains($path, '/lookup/l/') => PublicCodeService::ENTITY_STORAGE_ADDRESS,
            str_contains($path, '/lookup/r/') => PublicCodeService::ENTITY_STORAGE_RACK,
            str_contains($path, '/lookup/s/') => PublicCodeService::ENTITY_STORAGE_SLOT,
            default => null,
        };
        if ($entityType === null) {
            return new JsonResponse(['error' => 'Unbekannter Lookup-Typ'], 400);
        }

        $resolved = $this->publicCodeService->resolveInternalStorageByPublicCode($entityType, $code);
        if ($resolved === null || ($resolved['department_id'] ?? '') !== $departmentId) {
            return new JsonResponse(['error' => 'Code nicht gefunden'], 404);
        }

        return new JsonResponse($resolved);
    }

    private function getActorUserId(): ?string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getId() : null;
    }

    private function assertDepartmentAccess(string $departmentId): true|JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer dieses Department'], 403);
        }

        return true;
    }
}
