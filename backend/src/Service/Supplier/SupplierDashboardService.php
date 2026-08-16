<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\SupplierCompany;
use App\Entity\WorkshopTicket;
use App\Repository\SupplierCatalogItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kennzahlen für das Lieferanten-Dashboard (Verkauf / Werkstatt).
 */
final class SupplierDashboardService
{
    /** @var list<string> */
    private const OPEN_REPAIR_STATUSES = [
        WorkshopTicket::STATUS_OPEN,
        WorkshopTicket::STATUS_IN_PROGRESS,
        WorkshopTicket::STATUS_WAITING_PARTS,
    ];

    public function __construct(
        private SupplierCatalogItemRepository $catalogItemRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, mixed> */
    public function getDashboard(SupplierCompany $company): array
    {
        $companyId = (string) $company->getId();
        $offersSales = $company->hasCapability(SupplierCompany::CAPABILITY_CATALOG);
        $offersWorkshop = $company->hasCapability(SupplierCompany::CAPABILITY_REPAIRS);

        return [
            'company_id' => $companyId,
            'company_name' => $company->getName(),
            'capabilities' => $company->getCapabilities(),
            'sales' => [
                'offered' => $offersSales,
                'item_count' => $offersSales ? $this->catalogItemRepository->countByCompanyId($companyId) : 0,
            ],
            'workshop' => [
                'offered' => $offersWorkshop,
                'open_count' => $offersWorkshop ? $this->countOpenRepairs($companyId) : 0,
            ],
        ];
    }

    private function countOpenRepairs(string $companyId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(WorkshopTicket::class, 't')
            ->where('t.assignedToSupplierCompanyId = :companyId')
            ->andWhere('t.status IN (:open)')
            ->setParameter('companyId', $companyId)
            ->setParameter('open', self::OPEN_REPAIR_STATUSES)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
