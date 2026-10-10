<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassSetupIncompleteException;
use App\Service\Grossanlass\GrossanlassSetupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Ersteinrichtung eines Grossanlasses: Stand der drei Pflichtbereiche und Freigabe. */
#[Route('/api/departments/{departmentId}/grossanlass/setup', name: 'api_grossanlass_setup_')]
class GrossanlassSetupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassSetupService $setup,
    ) {
    }

    #[Route('', name: 'status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u): array => $this->setup->status($d, $u));
    }

    #[Route('/release', name: 'release', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function release(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u): array => $this->setup->release($d, $u));
    }

    /** @param callable(Department, User): array<string, mixed> $fn */
    private function handle(string $departmentId, callable $fn): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        try {
            return new JsonResponse($fn($department, $user));
        } catch (GrossanlassSetupIncompleteException $e) {
            return new JsonResponse($e->toPayload(), 422);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }
}
