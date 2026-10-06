<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Auth\MiDataGroupImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/midata-group-import', name: 'api_midata_group_import_')]
#[IsGranted('ROLE_USER')]
final class MiDataGroupImportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MiDataGroupImportService $groupImport,
    ) {}

    /** `available` drives the button; `tree` is null until the OAuth round trip has loaded a snapshot. */
    #[Route('', name: 'get', methods: ['GET'])]
    public function get(string $departmentId): JsonResponse
    {
        [$user, $department] = $this->context($departmentId);
        if (!$user instanceof User || !$department instanceof Department) {
            return new JsonResponse(['available' => false, 'has_mappings' => false, 'tree' => null]);
        }
        if ($this->groupImport->resolveImportableDepartmentGroupId($user, $department) === null) {
            return new JsonResponse(['available' => false, 'has_mappings' => false, 'tree' => null]);
        }

        return new JsonResponse([
            'available' => true,
            'has_mappings' => $this->groupImport->hasImportedGroups($department),
            'tree' => $this->groupImport->getTree($user, $department),
        ]);
    }

    #[Route('', name: 'import', methods: ['POST'])]
    public function import(string $departmentId, Request $request): JsonResponse
    {
        [$user, $department] = $this->context($departmentId);
        if (!$user instanceof User || !$department instanceof Department
            || $this->groupImport->resolveImportableDepartmentGroupId($user, $department) === null) {
            return new JsonResponse(['error' => 'Keine Berechtigung für den MiData-Gruppenimport'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $ids = is_array($data) ? ($data['external_group_ids'] ?? null) : null;
        if (!is_array($ids) || $ids === [] || !array_is_list($ids) || count($ids) > 300) {
            return new JsonResponse(['error' => 'external_group_ids ist erforderlich'], 400);
        }

        $result = $this->groupImport->import($user, $department, $ids);
        if ($result['status'] === 'snapshot_missing') {
            return new JsonResponse(['error' => 'Die MiData-Gruppen sind abgelaufen. Bitte erneut laden.', 'reason' => 'snapshot_missing'], 409);
        }
        if ($result['status'] !== 'ok') {
            return new JsonResponse(['error' => 'Ungültige Auswahl', 'reason' => $result['status']], 400);
        }

        return new JsonResponse($result);
    }

    /** @return array{0: ?User, 1: ?Department} */
    private function context(string $departmentId): array
    {
        $user = $this->getUser();

        return [
            $user instanceof User ? $user : null,
            $this->entityManager->getRepository(Department::class)->find($departmentId),
        ];
    }
}
