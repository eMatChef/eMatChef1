<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Service\Accounting\AccountingActivityInvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Aktivitäts-Rechnungen (berechnet) für die Buchhaltung → Erfassen.
 */
#[Route('/api/departments/{departmentId}/accounting/activity-invoices', name: 'api_accounting_activity_invoices_')]
class AccountingActivityInvoiceController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingActivityInvoiceService $activityInvoice,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        return new JsonResponse([
            'items' => $this->activityInvoice->listSummariesForDepartment($departmentId),
        ]);
    }
}
