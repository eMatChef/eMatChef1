<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\PrintTaskItem;
use App\Entity\User;
use App\Service\Print\MaterialQrExportCollector;
use App\Service\Print\MaterialQrPdfService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tasks/print-cart', name: 'api_print_cart_')]
class PrintTaskController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MaterialQrExportCollector $materialQrExportCollector,
        private MaterialQrPdfService $materialQrPdfService,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = (string) $request->query->get('department_id', '');
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) return $accessCheck;

        $items = $this->entityManager->getRepository(PrintTaskItem::class)->findBy([
            'departmentId' => $departmentId,
            'status' => 'pending',
        ], ['createdAt' => 'DESC']);

        return new JsonResponse(array_map(fn (PrintTaskItem $i) => $this->serializeItem($i), $items));
    }

    #[Route('/items', name: 'add_item', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addItem(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $entityType = trim((string) ($data['entity_type'] ?? ''));
        $entityId = trim((string) ($data['entity_id'] ?? ''));
        $label = trim((string) ($data['label'] ?? ''));
        $publicCode = isset($data['public_code']) ? trim((string) $data['public_code']) : null;
        $publicUrl = trim((string) ($data['public_url'] ?? ''));

        if ($departmentId === '' || $entityType === '' || $entityId === '' || $publicUrl === '') {
            return new JsonResponse(['error' => 'department_id, entity_type, entity_id und public_url sind erforderlich'], 400);
        }
        $urlCheck = $this->assertValidPublicUrl($entityType, $publicUrl);
        if ($urlCheck instanceof JsonResponse) {
            return $urlCheck;
        }
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) return $accessCheck;

        /** @var PrintTaskItem|null $existing */
        $existing = $this->entityManager->getRepository(PrintTaskItem::class)->findOneBy([
            'departmentId' => $departmentId,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'status' => 'pending',
        ]);
        if ($existing) {
            return new JsonResponse([
                'created' => false,
                'item' => $this->serializeItem($existing),
            ]);
        }

        $currentUser = $this->getUser();
        $item = new PrintTaskItem();
        $item->setId(IdGenerator::generate13Unique($this->entityManager, PrintTaskItem::class, 'pt'));
        $item->setDepartmentId($departmentId);
        $item->setCreatedByUserId($currentUser instanceof User ? $currentUser->getId() : null);
        $item->setEntityType($entityType);
        $item->setEntityId($entityId);
        $item->setLabel($label !== '' ? $label : ($entityType . ' ' . $entityId));
        $item->setPublicCode($publicCode !== '' ? $publicCode : null);
        $item->setPublicUrl($publicUrl);
        $item->setStatus('pending');
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return new JsonResponse([
            'created' => true,
            'item' => $this->serializeItem($item),
        ], 201);
    }

    #[Route('/bulk', name: 'add_bulk', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addBulk(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $items = $data['items'] ?? null;
        if ($departmentId === '' || !is_array($items)) {
            return new JsonResponse(['error' => 'department_id und items[] sind erforderlich'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) return $accessCheck;

        $created = 0;
        $skipped = 0;
        $resultItems = [];
        $currentUser = $this->getUser();
        foreach ($items as $row) {
            $entityType = trim((string) ($row['entity_type'] ?? ''));
            $entityId = trim((string) ($row['entity_id'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $publicCode = isset($row['public_code']) ? trim((string) $row['public_code']) : null;
            $publicUrl = trim((string) ($row['public_url'] ?? ''));
            if ($entityType === '' || $entityId === '' || $publicUrl === '') {
                continue;
            }
            if ($this->assertValidPublicUrl($entityType, $publicUrl) instanceof JsonResponse) {
                continue;
            }

            /** @var PrintTaskItem|null $existing */
            $existing = $this->entityManager->getRepository(PrintTaskItem::class)->findOneBy([
                'departmentId' => $departmentId,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'status' => 'pending',
            ]);
            if ($existing) {
                $skipped++;
                $resultItems[] = $this->serializeItem($existing);
                continue;
            }

            $item = new PrintTaskItem();
            $item->setId(IdGenerator::generate13Unique($this->entityManager, PrintTaskItem::class, 'pt'));
            $item->setDepartmentId($departmentId);
            $item->setCreatedByUserId($currentUser instanceof User ? $currentUser->getId() : null);
            $item->setEntityType($entityType);
            $item->setEntityId($entityId);
            $item->setLabel($label !== '' ? $label : ($entityType . ' ' . $entityId));
            $item->setPublicCode($publicCode !== '' ? $publicCode : null);
            $item->setPublicUrl($publicUrl);
            $item->setStatus('pending');
            $this->entityManager->persist($item);
            $created++;
            $resultItems[] = $this->serializeItem($item);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'created_count' => $created,
            'skipped_count' => $skipped,
            'items' => $resultItems,
        ], $created > 0 ? 201 : 200);
    }

    #[Route('/items/{id}/printed', name: 'mark_printed', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function markPrinted(string $id): JsonResponse
    {
        /** @var PrintTaskItem|null $item */
        $item = $this->entityManager->getRepository(PrintTaskItem::class)->find($id);
        if (!$item) return new JsonResponse(['error' => 'Eintrag nicht gefunden'], 404);
        $accessCheck = $this->assertDepartmentAccess($item->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) return $accessCheck;

        $item->setStatus('printed');
        $item->setPrintedAt(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/items/{id}', name: 'delete_item', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteItem(string $id): JsonResponse
    {
        /** @var PrintTaskItem|null $item */
        $item = $this->entityManager->getRepository(PrintTaskItem::class)->find($id);
        if (!$item) return new JsonResponse(['error' => 'Eintrag nicht gefunden'], 404);
        $accessCheck = $this->assertDepartmentAccess($item->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) return $accessCheck;

        $this->entityManager->remove($item);
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * PDF-Export aller Material-QR-Codes (Bulk, Chargen, physische Kombis).
     * A4-Raster: 12 QR-Codes pro Seite (3×4).
     */
    #[Route('/material-qr-pdf', name: 'material_qr_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function materialQrPdf(Request $request): Response
    {
        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $currentUser = $this->getUser();
        $actorUserId = $currentUser instanceof User ? $currentUser->getId() : null;

        try {
            $rows = $this->materialQrExportCollector->collectForDepartment($departmentId, $actorUserId, true);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'QR-Codes konnten nicht gesammelt werden: ' . $e->getMessage()], 500);
        }

        if ($rows === []) {
            return new JsonResponse(['error' => 'Keine Material-QR-Codes zum Export vorhanden'], 404);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        $deptName = $department instanceof Department ? $department->getName() : $departmentId;
        $title = $deptName . ' – Material-QR-Codes';

        try {
            $pdfBinary = $this->materialQrPdfService->renderPdf($rows, $title);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'PDF konnte nicht erzeugt werden: ' . $e->getMessage()], 500);
        }

        $filename = 'material-qr-codes-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $deptName) . '.pdf';

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    #[Route('', name: 'clear', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function clear(Request $request): JsonResponse
    {
        $departmentId = (string) $request->query->get('department_id', '');
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) return $accessCheck;

        $conn = $this->entityManager->getConnection();
        $deleted = $conn->executeStatement(
            'DELETE FROM print_task_item WHERE department_id = :department_id AND status = :status',
            ['department_id' => $departmentId, 'status' => 'pending']
        );

        return new JsonResponse(['deleted' => $deleted]);
    }

    private function serializeItem(PrintTaskItem $item): array
    {
        return [
            'id' => $item->getId(),
            'department_id' => $item->getDepartmentId(),
            'created_by_user_id' => $item->getCreatedByUserId(),
            'entity_type' => $item->getEntityType(),
            'entity_id' => $item->getEntityId(),
            'label' => $item->getLabel(),
            'public_code' => $item->getPublicCode(),
            'public_url' => $item->getPublicUrl(),
            'status' => $item->getStatus(),
            'created_at' => $item->getCreatedAt()->format('c'),
            'printed_at' => $item->getPrintedAt()?->format('c'),
        ];
    }

    /**
     * Erlaubte entity_type: batch, activity, workshop, storage_address, storage_rack, storage_slot.
     * public_url muss zum QR-Schema passen (kein /i/b/-Only).
     */
    private function assertValidPublicUrl(string $entityType, string $publicUrl): true|JsonResponse
    {
        $path = (string) (parse_url($publicUrl, PHP_URL_PATH) ?? '');
        $ok = match (strtolower($entityType)) {
            'batch', 'material' => (bool) preg_match('#^/i/m/[^/]+/b/[^/]+/?$#', $path),
            'activity' => (bool) preg_match('#^/i/a/[^/]+/?$#', $path),
            'workshop' => (bool) preg_match('#^/i/w/[^/]+/?$#', $path),
            'storage_address' => (bool) preg_match('#^/i/l/[^/]+/?$#', $path),
            'storage_rack' => (bool) preg_match('#^/i/r/[^/]+/?$#', $path),
            'storage_slot' => (bool) preg_match('#^/i/s/[^/]+/?$#', $path),
            default => false,
        };
        if (!$ok) {
            return new JsonResponse(['error' => 'public_url entspricht nicht dem QR-Schema'], 400);
        }

        return true;
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

