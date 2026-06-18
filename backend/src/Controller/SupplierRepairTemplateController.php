<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RepairTemplate;
use App\Entity\SupplierRepairTemplate;
use App\Entity\User;
use App\Repository\RepairTemplateRepository;
use App\Repository\SupplierRepairTemplateRepository;
use App\Security\Voter\SupplierCompanyVoter;
use App\Service\Supplier\SupplierCompanyAccessService;
use App\Service\Supplier\SupplierRepairTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/supplier-companies/{companyId}/repair-templates', name: 'api_supplier_repair_templates_')]
class SupplierRepairTemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierRepairTemplateRepository $supplierRepairTemplateRepository,
        private RepairTemplateRepository $repairTemplateRepository,
        private SupplierCompanyAccessService $accessService,
        private SupplierRepairTemplateService $repairTemplateService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function list(string $companyId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireRepairsAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse([
            'repair_templates' => $this->repairTemplateService->listMergedForCompany($companyId, true),
        ]);
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function import(string $companyId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $company = $this->accessService->requireRepairsAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $templateKey = strtolower(trim((string) ($data['template_key'] ?? '')));
        if ($templateKey === '') {
            return new JsonResponse(['error' => 'template_key ist erforderlich'], 400);
        }

        $platform = $this->repairTemplateRepository->findOneByTemplateKey($templateKey);
        if (!$platform instanceof RepairTemplate || !$platform->isActive()) {
            return new JsonResponse(['error' => 'Plattform-Vorlage nicht gefunden'], 404);
        }

        $existing = $this->supplierRepairTemplateRepository->findOneByCompanyAndKey($companyId, $templateKey);
        if ($existing instanceof SupplierRepairTemplate) {
            return new JsonResponse(
                $this->repairTemplateService->serializeMerged($existing, $platform, true),
            );
        }

        try {
            $supplierTemplate = $this->repairTemplateService->importFromPlatform($company, $platform);
            $this->entityManager->flush();

            return new JsonResponse(
                $this->repairTemplateService->serializeMerged($supplierTemplate, $platform, true),
                201,
            );
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Import fehlgeschlagen: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{templateKey}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function update(string $companyId, string $templateKey, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireRepairsAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $templateKey = strtolower(trim($templateKey));
        $supplierTemplate = $this->supplierRepairTemplateRepository->findOneByCompanyAndKey($companyId, $templateKey);
        if (!$supplierTemplate instanceof SupplierRepairTemplate) {
            return new JsonResponse(['error' => 'Supplier-Vorlage nicht gefunden'], 404);
        }

        $platform = $this->repairTemplateRepository->findOneByTemplateKey($templateKey);
        if (!$platform instanceof RepairTemplate) {
            return new JsonResponse(['error' => 'Plattform-Vorlage nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('prices_json', $data)) {
            if (!\is_array($data['prices_json'])) {
                return new JsonResponse(['error' => 'prices_json muss ein Objekt sein'], 400);
            }
            $supplierTemplate->setPricesJson($data['prices_json']);
        }

        if (array_key_exists('services_json', $data)) {
            if (!\is_array($data['services_json'])) {
                return new JsonResponse(['error' => 'services_json muss ein Objekt sein'], 400);
            }
            $supplierTemplate->setServicesJson($data['services_json']);
        }

        if (array_key_exists('flat_rate_chf', $data)) {
            $flat = $data['flat_rate_chf'];
            $supplierTemplate->setFlatRateChf($flat !== null && $flat !== '' ? (string) $flat : null);
        }

        if (array_key_exists('is_active', $data)) {
            $supplierTemplate->setIsActive((bool) $data['is_active']);
        }

        $supplierTemplate->updateTimestamps();
        $this->entityManager->flush();

        return new JsonResponse($this->repairTemplateService->serializeMerged($supplierTemplate, $platform, true));
    }

    #[Route('/{templateKey}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function delete(string $companyId, string $templateKey): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireRepairsAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $templateKey = strtolower(trim($templateKey));
        $supplierTemplate = $this->supplierRepairTemplateRepository->findOneByCompanyAndKey($companyId, $templateKey);
        if (!$supplierTemplate instanceof SupplierRepairTemplate) {
            return new JsonResponse(['error' => 'Supplier-Vorlage nicht gefunden'], 404);
        }

        $this->entityManager->remove($supplierTemplate);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Vorlage gelöscht']);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('Nicht authentifiziert');
        }

        return $user;
    }
}
