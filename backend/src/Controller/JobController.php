<?php

namespace App\Controller;

use App\Service\UnassignedUserCleanupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/jobs', name: 'api_jobs_')]
class JobController extends AbstractController
{
    public function __construct(private UnassignedUserCleanupService $cleanupService)
    {
    }

    #[Route('/unassigned-users-cleanup/preview', name: 'unassigned_cleanup_preview', methods: ['GET'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function preview(Request $request): JsonResponse
    {
        $days = max(1, (int) $request->query->get('days', 21));
        $result = $this->cleanupService->preview($days);

        return new JsonResponse($result);
    }

    #[Route('/unassigned-users-cleanup/run', name: 'unassigned_cleanup_run', methods: ['POST'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function run(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $days = max(1, (int) ($data['days'] ?? 21));
        $dryRun = (bool) ($data['dry_run'] ?? false);
        $userIds = isset($data['user_ids']) && is_array($data['user_ids']) ? $data['user_ids'] : [];

        if ($dryRun) {
            $result = $this->cleanupService->preview($days);
            return new JsonResponse(['dry_run' => true] + $result);
        }

        $result = $this->cleanupService->cleanup($days, $userIds);
        return new JsonResponse(['dry_run' => false] + $result);
    }
}
