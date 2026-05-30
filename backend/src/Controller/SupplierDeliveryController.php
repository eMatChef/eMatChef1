<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\SupplierCatalogItem;
use App\Entity\SupplierDelivery;
use App\Entity\SupplierDeliveryLine;
use App\Entity\User;
use App\Repository\SupplierCatalogItemRepository;
use App\Repository\SupplierDeliveryRepository;
use App\Security\Voter\SupplierCompanyVoter;
use App\Service\Supplier\SupplierCompanyAccessService;
use App\Service\Supplier\SupplierDeliveryValidator;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/supplier-companies/{companyId}/deliveries', name: 'api_supplier_deliveries_')]
class SupplierDeliveryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierDeliveryRepository $deliveryRepository,
        private SupplierCatalogItemRepository $catalogItemRepository,
        private SupplierCompanyAccessService $accessService,
        private SupplierDeliveryValidator $deliveryValidator,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function list(string $companyId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireDeliveryAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $deliveries = $this->deliveryRepository->findByCompanyId($companyId);

        return new JsonResponse([
            'deliveries' => array_map(
                static fn (SupplierDelivery $delivery) => $delivery->toArray(),
                $deliveries
            ),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function create(string $companyId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $company = $this->accessService->requireDeliveryAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $departmentId = trim((string) ($data['department_id'] ?? ''));
            if ($departmentId === '') {
                return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
            }
            $department = $this->entityManager->find(Department::class, $departmentId);
            if (!$department) {
                return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
            }

            $delivery = new SupplierDelivery();
            $delivery->setId(IdGenerator::generateUnique($this->entityManager, SupplierDelivery::class));
            $delivery->setSupplierCompany($company);
            $delivery->setDepartment($department);
            $this->applyHeaderData($delivery, $data);
            $this->replaceLines($delivery, $companyId, $data['lines'] ?? []);

            $this->entityManager->persist($delivery);
            $this->entityManager->flush();

            return new JsonResponse([
                'delivery' => $delivery->toArray(),
                'message' => 'Übergabe erstellt',
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{deliveryId}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function show(string $companyId, string $deliveryId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireDeliveryAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $delivery = $this->requireDelivery($companyId, $deliveryId);

        return new JsonResponse(['delivery' => $delivery->toArray()]);
    }

    #[Route('/{deliveryId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function update(string $companyId, string $deliveryId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireDeliveryAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $delivery = $this->requireDelivery($companyId, $deliveryId);
        if (!$delivery->isEditable()) {
            return new JsonResponse(['error' => 'Nur Entwürfe können bearbeitet werden'], 409);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            if (array_key_exists('department_id', $data)) {
                $departmentId = trim((string) $data['department_id']);
                if ($departmentId === '') {
                    return new JsonResponse(['error' => 'department_id darf nicht leer sein'], 400);
                }
                $department = $this->entityManager->find(Department::class, $departmentId);
                if (!$department) {
                    return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
                }
                $delivery->setDepartment($department);
            }
            $this->applyHeaderData($delivery, $data);
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($delivery, $companyId, $data['lines']);
            }
            $delivery->touch();
            $this->entityManager->flush();

            return new JsonResponse([
                'delivery' => $delivery->toArray(),
                'message' => 'Übergabe aktualisiert',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{deliveryId}/submit', name: 'submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function submit(string $companyId, string $deliveryId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireDeliveryAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $delivery = $this->requireDelivery($companyId, $deliveryId);
        if ($delivery->getStatus() !== SupplierDelivery::STATUS_DRAFT) {
            return new JsonResponse(['error' => 'Nur Entwürfe können übermittelt werden'], 409);
        }

        $validation = $this->deliveryValidator->validateForSubmit($delivery);
        if (\count($validation['errors']) > 0) {
            return new JsonResponse([
                'error' => 'Validierung fehlgeschlagen',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ], 400);
        }

        $delivery->setStatus(SupplierDelivery::STATUS_SUBMITTED);
        $delivery->touch();
        $this->entityManager->flush();

        return new JsonResponse([
            'delivery' => $delivery->toArray(),
            'warnings' => $validation['warnings'],
            'message' => 'Übergabe übermittelt',
        ]);
    }

    #[Route('/{deliveryId}/cancel', name: 'cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function cancel(string $companyId, string $deliveryId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireDeliveryAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $delivery = $this->requireDelivery($companyId, $deliveryId);
        if (!\in_array($delivery->getStatus(), [SupplierDelivery::STATUS_DRAFT, SupplierDelivery::STATUS_SUBMITTED], true)) {
            return new JsonResponse(['error' => 'Diese Übergabe kann nicht storniert werden'], 409);
        }

        $delivery->setStatus(SupplierDelivery::STATUS_CANCELLED);
        $delivery->touch();
        $this->entityManager->flush();

        return new JsonResponse([
            'delivery' => $delivery->toArray(),
            'message' => 'Übergabe storniert',
        ]);
    }

    #[Route('/{deliveryId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function delete(string $companyId, string $deliveryId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireDeliveryAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $delivery = $this->requireDelivery($companyId, $deliveryId);
        if ($delivery->getStatus() !== SupplierDelivery::STATUS_DRAFT) {
            return new JsonResponse(['error' => 'Nur Entwürfe können gelöscht werden'], 409);
        }

        $this->entityManager->remove($delivery);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Übergabe gelöscht']);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function requireDelivery(string $companyId, string $deliveryId): SupplierDelivery
    {
        $delivery = $this->deliveryRepository->find($deliveryId);
        if (!$delivery instanceof SupplierDelivery || $delivery->getSupplierCompanyId() !== $companyId) {
            throw $this->createNotFoundException('Übergabe nicht gefunden');
        }

        return $delivery;
    }

    /** @param array<string, mixed> $data */
    private function applyHeaderData(SupplierDelivery $delivery, array $data): void
    {
        if (array_key_exists('delivery_ref', $data)) {
            $delivery->setDeliveryRef($this->nullableString($data['delivery_ref']));
        }
        if (array_key_exists('invoice_ref', $data)) {
            $delivery->setInvoiceRef($this->nullableString($data['invoice_ref']));
        }
        if (array_key_exists('delivered_at', $data)) {
            $delivery->setDeliveredAt($this->parseDateTime($data['delivered_at']));
        }
        if (array_key_exists('notes', $data)) {
            $delivery->setNotes($this->nullableString($data['notes']));
        }
    }

    /** @param mixed $lines */
    private function replaceLines(SupplierDelivery $delivery, string $companyId, mixed $lines): void
    {
        if (!\is_array($lines)) {
            throw new \InvalidArgumentException('lines muss ein Array sein');
        }

        $delivery->clearLines();

        foreach ($lines as $index => $lineData) {
            if (!\is_array($lineData)) {
                throw new \InvalidArgumentException('Ungültige Zeile');
            }
            $catalogItemId = trim((string) ($lineData['catalog_item_id'] ?? ''));
            if ($catalogItemId === '') {
                throw new \InvalidArgumentException('catalog_item_id ist pro Zeile erforderlich');
            }
            $catalogItem = $this->catalogItemRepository->find($catalogItemId);
            if (!$catalogItem instanceof SupplierCatalogItem || $catalogItem->getSupplierCompanyId() !== $companyId) {
                throw new \InvalidArgumentException('Katalog-Artikel nicht gefunden');
            }

            $line = new SupplierDeliveryLine();
            $line->setId(IdGenerator::generateUnique($this->entityManager, SupplierDeliveryLine::class));
            $line->setCatalogItem($catalogItem);
            $line->setQty(max(1, (int) ($lineData['qty'] ?? 1)));
            $line->setUnitPrice($this->nullableDecimal($lineData['unit_price'] ?? $catalogItem->getUnitPrice()));
            $line->setSerialNumbers(\is_array($lineData['serial_numbers'] ?? null) ? $lineData['serial_numbers'] : []);
            $componentSerials = $lineData['component_serials'] ?? null;
            $line->setComponentSerials(\is_array($componentSerials) ? $componentSerials : null);
            $line->setSortOrder((int) ($lineData['sort_order'] ?? $index));
            $delivery->addLine($line);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function parseDateTime(mixed $value): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new \DateTime((string) $value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültiges delivered_at');
        }
    }
}
