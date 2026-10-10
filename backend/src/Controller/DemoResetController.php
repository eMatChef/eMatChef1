<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Demo\Reset\DemoResetService;
use App\Service\Demo\Scenario\DemoScenarioException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Demo-Reset im gelben Testumgebungs-Balken (nur Demo-Szenarien in erlaubten Umgebungen). */
#[Route('/api/departments/{departmentId}/demo-reset', name: 'api_demo_reset_')]
class DemoResetController extends AbstractController
{
    public function __construct(private DemoResetService $reset)
    {
    }

    #[Route('', name: 'status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(string $departmentId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        return new JsonResponse($this->reset->status($user, $departmentId));
    }

    #[Route('/preview', name: 'preview', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function preview(string $departmentId): JsonResponse
    {
        return $this->run(fn (User $u): array => $this->reset->preview($u, $departmentId));
    }

    #[Route('', name: 'execute', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function execute(string $departmentId, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $body = \is_array($body) ? $body : [];

        return $this->run(fn (User $u): array => $this->reset->execute(
            $u,
            $departmentId,
            (string) ($body['plan_hash'] ?? ''),
            (string) ($body['confirm'] ?? ''),
        ));
    }

    /** @param callable(User): array<string, mixed> $fn */
    private function run(callable $fn): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        try {
            return new JsonResponse($fn($user));
        } catch (DemoScenarioException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        }
    }
}
