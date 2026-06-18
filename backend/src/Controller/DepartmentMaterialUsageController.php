<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\DepartmentMaterialUsageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/material-usage-stats', name: 'api_department_material_usage_')]
class DepartmentMaterialUsageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private DepartmentMaterialUsageService $usageService,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertDepartmentMember($departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $items = $this->usageService->topMaterials($departmentId, $from, $to, $limit);

        return new JsonResponse([
            'department_id' => $departmentId,
            'from' => $from?->format(\DateTimeInterface::ATOM),
            'to' => $to?->format(\DateTimeInterface::ATOM),
            'items' => $items,
        ]);
    }

    private function assertDepartmentMember(string $departmentId): JsonResponse|null
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'departmentId' => $departmentId,
            'userId' => $user->getId(),
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Kein Zugriff'], 403);
        }

        return null;
    }

    private function parseDate(mixed $raw): ?\DateTimeImmutable
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
