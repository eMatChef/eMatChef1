<?php

namespace App\Controller\Public;

use App\Repository\OrganisationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Öffentliche Liste für Registrierung / Onboarding (ohne JWT).
 * Liegt unter /api/public/* → Firewall api_public (security: false).
 */
#[Route('/api/public/organisations', name: 'api_public_organisations_')]
class PublicOrganisationsController extends AbstractController
{
    public function __construct(
        private OrganisationRepository $organisationRepository
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $organisations = $this->organisationRepository->findAll();

        $result = [];
        foreach ($organisations as $org) {
            $result[] = [
                'id' => $org->getId(),
                'name' => $org->getName(),
            ];
        }

        return new JsonResponse($result);
    }
}
