<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\RepairTemplate;
use App\Entity\SupplierCompany;
use App\Entity\SupplierRepairTemplate;
use App\Repository\SupplierRepairTemplateRepository;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class SupplierRepairTemplateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierRepairTemplateRepository $supplierRepairTemplateRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMergedForCompany(string $companyId, bool $detailed = true, bool $activeOnly = false): array
    {
        $templates = $this->supplierRepairTemplateRepository->findByCompanyId($companyId, $activeOnly);
        $result = [];

        foreach ($templates as $supplierTemplate) {
            $platform = $this->entityManager->getRepository(RepairTemplate::class)
                ->findOneBy(['templateKey' => $supplierTemplate->getTemplateKey()]);
            if (!$platform instanceof RepairTemplate || !$platform->isActive()) {
                continue;
            }
            $result[] = $this->serializeMerged($supplierTemplate, $platform, $detailed);
        }

        return $result;
    }

    public function importFromPlatform(SupplierCompany $company, RepairTemplate $platform): SupplierRepairTemplate
    {
        $templateKey = strtolower(trim($platform->getTemplateKey()));
        $existing = $this->supplierRepairTemplateRepository->findOneByCompanyAndKey($company->getId(), $templateKey);
        if ($existing instanceof SupplierRepairTemplate) {
            return $existing;
        }

        $supplierTemplate = new SupplierRepairTemplate();
        $supplierTemplate->setId(IdGenerator::generateUnique($this->entityManager, SupplierRepairTemplate::class));
        $supplierTemplate->setSupplierCompany($company);
        $supplierTemplate->setTemplateKey($templateKey);
        $supplierTemplate->setPricesJson($this->buildDefaultPricesJson($platform));
        $supplierTemplate->setServicesJson(['services' => []]);

        $this->entityManager->persist($supplierTemplate);

        return $supplierTemplate;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMerged(
        SupplierRepairTemplate $supplierTemplate,
        RepairTemplate $platform,
        bool $detailed,
    ): array {
        $result = [
            'id' => $supplierTemplate->getId(),
            'supplier_company_id' => $supplierTemplate->getSupplierCompanyId(),
            'template_key' => $supplierTemplate->getTemplateKey(),
            'name' => $platform->getName(),
            'material_class' => $platform->getMaterialClass(),
            'flat_rate_chf' => $supplierTemplate->getFlatRateChf(),
            'is_active' => $supplierTemplate->isActive(),
            'prices_json' => $supplierTemplate->getPricesJson(),
            'services_json' => $supplierTemplate->getServicesJson(),
            'created_at' => $supplierTemplate->getCreatedAt()->format('c'),
            'updated_at' => $supplierTemplate->getUpdatedAt()->format('c'),
        ];

        if ($detailed) {
            $result['structure_json'] = $platform->getStructureJson();
            $result['diagram_json'] = $platform->getDiagramJson();
        }

        return $result;
    }

    /**
     * @return array<string, array{unit_price_chf: null, is_active: bool}>
     */
    public function buildDefaultPricesJson(RepairTemplate $platform): array
    {
        $prices = [];
        $structure = $platform->getStructureJson();
        $sections = $structure['sections'] ?? [];

        foreach ($sections as $section) {
            if (!\is_array($section)) {
                continue;
            }
            foreach ($section['items'] ?? [] as $item) {
                if (!\is_array($item) || empty($item['key'])) {
                    continue;
                }
                $key = (string) $item['key'];
                $prices[$key] = [
                    'unit_price_chf' => null,
                    'is_active' => true,
                ];
            }
        }

        return $prices;
    }
}
