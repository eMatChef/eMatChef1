<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Service\Accounting\AccountingExpectedCostsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * #7 Phase 5: Platzhalter «Kosten folgen» (offene Werkstatt mit Aktivität).
 */
#[Route('/api/departments/{departmentId}/accounting/expected-costs', name: 'api_accounting_expected_costs_')]
class AccountingExpectedCostsController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingExpectedCostsService $expectedCosts,
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

        return new JsonResponse($this->expectedCosts->listForDepartment($departmentId));
    }
}
