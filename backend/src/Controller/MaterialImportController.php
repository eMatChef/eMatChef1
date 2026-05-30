<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MaterialImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/materials', name: 'api_materials_import_')]
class MaterialImportController extends AbstractController
{
    public function __construct(
        private MaterialImportService $importService,
    ) {
    }

    /**
     * Material-Stammdaten + Bestand aus CSV/XLSX-Vorschau importieren.
     *
     * Body: {
     *   department_id: string,
     *   dry_run?: bool,
     *   default_duplicate_action?: "add_batch"|"skip"|"create",
     *   rows: array
     * }
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function import(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }

        $departmentId = trim((string) ($data['department_id'] ?? ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $accessError = $this->importService->assertCanImport($departmentId, $user);
        if ($accessError !== null) {
            return new JsonResponse(['error' => $accessError], 403);
        }

        $rows = $data['rows'] ?? null;
        if (!is_array($rows) || count($rows) === 0) {
            return new JsonResponse(['error' => 'rows ist erforderlich und darf nicht leer sein'], 400);
        }

        $dryRun = (bool) ($data['dry_run'] ?? false);
        $defaultDuplicateAction = (string) ($data['default_duplicate_action'] ?? 'add_batch');
        if (!in_array($defaultDuplicateAction, ['add_batch', 'skip', 'create'], true)) {
            $defaultDuplicateAction = 'add_batch';
        }

        try {
            $result = $this->importService->process(
                $departmentId,
                $rows,
                $dryRun,
                $defaultDuplicateAction,
                $user,
            );

            if (!empty($result['error'])) {
                return new JsonResponse(['error' => $result['error']], 404);
            }

            $status = $dryRun ? 200 : ($result['success'] ? 201 : 207);

            return new JsonResponse($result, $status);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Import fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }
}
