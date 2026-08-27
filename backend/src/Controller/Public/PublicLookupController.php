<?php

namespace App\Controller\Public;

use App\Service\Grossanlass\GrossanlassUserCardService;
use App\Service\Public\PublicCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/lookup', name: 'api_public_lookup_')]
class PublicLookupController extends AbstractController
{
    public function __construct(
        private PublicCodeService $publicCodeService,
        private GrossanlassUserCardService $grossanlassUserCardService,
    ) {}

    #[Route('/m/{materialCode}/b/{batchCode}', name: 'material_batch', methods: ['GET'])]
    public function materialBatch(string $materialCode, string $batchCode): JsonResponse
    {
        $result = $this->publicCodeService->resolveMaterialBatchByPublicCodes($materialCode, $batchCode);

        if ($result === null) {
            return new JsonResponse([
                'error' => 'Public-Code nicht gefunden oder nicht aktiv',
            ], 404);
        }

        return new JsonResponse($result);
    }

    #[Route('/m/{publicCode}', name: 'material', methods: ['GET'])]
    public function material(string $publicCode): JsonResponse
    {
        $result = $this->publicCodeService->resolveMaterialByPublicCode($publicCode);

        if ($result === null) {
            return new JsonResponse([
                'error' => 'Public-Code nicht gefunden oder nicht aktiv',
            ], 404);
        }

        return new JsonResponse($result);
    }

    #[Route('/a/{publicCode}', name: 'activity', methods: ['GET'])]
    public function activity(string $publicCode): JsonResponse
    {
        $result = $this->publicCodeService->resolveActivityByPublicCode($publicCode);

        if ($result === null) {
            return new JsonResponse([
                'error' => 'Public-Code nicht gefunden oder nicht aktiv',
            ], 404);
        }

        return new JsonResponse($result);
    }

    #[Route('/w/{publicCode}', name: 'workshop', methods: ['GET'])]
    public function workshop(string $publicCode): JsonResponse
    {
        $result = $this->publicCodeService->resolveWorkshopByPublicCode($publicCode);

        if ($result === null) {
            return new JsonResponse([
                'error' => 'Public-Code nicht gefunden oder nicht aktiv',
            ], 404);
        }

        return new JsonResponse($result);
    }

    #[Route('/c/{publicCode}', name: 'user_card', methods: ['GET'])]
    public function userCard(string $publicCode): JsonResponse
    {
        $result = $this->grossanlassUserCardService->resolvePublicByCode($publicCode);

        if ($result === null) {
            return new JsonResponse([
                'error' => 'Public-Code nicht gefunden oder nicht aktiv',
            ], 404);
        }

        return new JsonResponse($result);
    }
}
