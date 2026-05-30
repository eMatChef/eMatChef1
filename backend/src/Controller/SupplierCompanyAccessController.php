<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\Voter\SupplierCompanyVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Interner Zugriffs-Probe für SupplierCompanyVoter (Paket 2).
 */
#[Route('/api/supplier-companies', name: 'api_supplier_companies_')]
class SupplierCompanyAccessController extends AbstractController
{
    #[Route('/{companyId}/access-probe', name: 'access_probe', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function accessProbe(string $companyId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht angemeldet'], 401);
        }

        return new JsonResponse([
            'ok' => true,
            'company_id' => $companyId,
            'user_id' => $user->getId(),
        ]);
    }
}
