<?php

namespace App\Controller;

use App\Entity\DepartmentDisplayScreen;
use App\Entity\User;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\Layout\DisplayBlockService;
use App\Service\Display\Layout\DisplayLayoutException;
use App\Service\Display\Layout\DisplayLayoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Layout-Entwurf, Veröffentlichung und Revisionen eines Infoscreens. Alle Endpunkte erfordern das Verwaltungsrecht
 * des Departments und prüfen, dass der Screen zu diesem Department gehört. Entwürfe verlassen nie die
 * authentifizierte API; öffentliche Geräte erhalten nur die veröffentlichte Revision.
 */
#[Route('/api/departments/{departmentId}', name: 'api_department_display_layout_')]
#[IsGranted('ROLE_USER')]
class DepartmentDisplayLayoutController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentDisplayScreenService $screenService,
        private DisplayLayoutService $layoutService,
        private DisplayBlockService $blockService,
    ) {
    }

    /** Widgets und Vorlagen, die im Kontext des Departments angeboten werden. */
    #[Route('/display-layout/catalog', name: 'catalog', methods: ['GET'])]
    public function catalog(string $departmentId): JsonResponse
    {
        return $this->run($departmentId, null, fn () => $this->layoutService->catalog($departmentId));
    }

    /** Eigene Bausteine des Departments (Widgets und Gruppen); offizielle Bausteine liegen im Katalog. */
    #[Route('/display-blocks', name: 'blocks_list', methods: ['GET'])]
    public function blocks(string $departmentId): JsonResponse
    {
        return $this->run($departmentId, null, fn () => ['blocks' => $this->blockService->list($departmentId)]);
    }

    #[Route('/display-blocks', name: 'blocks_create', methods: ['POST'])]
    public function createBlock(string $departmentId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->run($departmentId, null, fn ($s, User $u) => $this->blockService->create(
            $departmentId,
            $u,
            \is_string($data['name'] ?? null) ? $data['name'] : '',
            $data['content'] ?? null,
            $data['default_w'] ?? null,
            $data['default_h'] ?? null,
        ), 201);
    }

    #[Route('/display-blocks/{blockId}', name: 'blocks_rename', methods: ['PATCH'])]
    public function renameBlock(string $departmentId, string $blockId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->run($departmentId, null, fn () => $this->blockService->rename($departmentId, $blockId, \is_string($data['name'] ?? null) ? $data['name'] : ''));
    }

    #[Route('/display-blocks/{blockId}', name: 'blocks_delete', methods: ['DELETE'])]
    public function deleteBlock(string $departmentId, string $blockId): JsonResponse
    {
        return $this->run($departmentId, null, function () use ($departmentId, $blockId) {
            $this->blockService->delete($departmentId, $blockId);

            return ['deleted' => true];
        });
    }

    #[Route('/display-screens/{screenId}/layout', name: 'state', methods: ['GET'])]
    public function state(string $departmentId, string $screenId): JsonResponse
    {
        return $this->run($departmentId, $screenId, fn (DepartmentDisplayScreen $s) => $this->layoutService->state($s));
    }

    /** Entwurf aus Vorlage ("blank" = leer) oder aus einem anderen Infoscreen desselben Departments. */
    #[Route('/display-screens/{screenId}/layout/initialize', name: 'initialize', methods: ['POST'])]
    public function initialize(string $departmentId, string $screenId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->run($departmentId, $screenId, fn (DepartmentDisplayScreen $s) => $this->layoutService->initialize(
            $s,
            isset($data['template_id']) ? (string) $data['template_id'] : null,
            isset($data['source_screen_id']) ? (string) $data['source_screen_id'] : null,
            $this->version($data),
        ));
    }

    #[Route('/display-screens/{screenId}/layout/draft', name: 'save_draft', methods: ['PUT'])]
    public function saveDraft(string $departmentId, string $screenId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->run($departmentId, $screenId, fn (DepartmentDisplayScreen $s) => $this->layoutService->saveDraft($s, $data['layout'] ?? null, $this->version($data)));
    }

    /** Prüft den gespeicherten Entwurf mit den strengen Regeln der Veröffentlichung. */
    #[Route('/display-screens/{screenId}/layout/validate', name: 'validate', methods: ['POST'])]
    public function validateDraft(string $departmentId, string $screenId): JsonResponse
    {
        return $this->run($departmentId, $screenId, function (DepartmentDisplayScreen $s) {
            $errors = $this->layoutService->validateDraftForPublish($s);

            return ['valid' => $errors === [], 'errors' => $errors];
        });
    }

    #[Route('/display-screens/{screenId}/layout/publish', name: 'publish', methods: ['POST'])]
    public function publish(string $departmentId, string $screenId, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->run($departmentId, $screenId, fn (DepartmentDisplayScreen $s, User $u) => $this->layoutService->publish($s, $this->version($data), $u));
    }

    #[Route('/display-screens/{screenId}/layout/revisions', name: 'revisions', methods: ['GET'])]
    public function revisions(string $departmentId, string $screenId): JsonResponse
    {
        return $this->run($departmentId, $screenId, fn (DepartmentDisplayScreen $s) => $this->layoutService->listRevisions($s));
    }

    #[Route('/display-screens/{screenId}/layout/revisions/{number}', name: 'revision', methods: ['GET'], requirements: ['number' => '\d+'])]
    public function revision(string $departmentId, string $screenId, int $number): JsonResponse
    {
        return $this->run($departmentId, $screenId, function (DepartmentDisplayScreen $s) use ($number) {
            $detail = $this->layoutService->revisionDetail($s, $number);
            if ($detail === null) {
                throw new DisplayLayoutException(404, 'revision_not_found', 'Revision nicht gefunden.');
            }

            return $detail;
        });
    }

    /** Frühere Revision wiederherstellen (wird zum Entwurf, optional sofort als neue Revision veröffentlicht). */
    #[Route('/display-screens/{screenId}/layout/revisions/{number}/restore', name: 'restore', methods: ['POST'], requirements: ['number' => '\d+'])]
    public function restore(string $departmentId, string $screenId, int $number, Request $request): JsonResponse
    {
        $data = $this->body($request);

        return $this->run($departmentId, $screenId, fn (DepartmentDisplayScreen $s, User $u) => $this->layoutService->restore(
            $s,
            $number,
            $this->version($data),
            ($data['publish'] ?? false) === true,
            $u,
        ));
    }

    /**
     * @param callable $action fn (?DepartmentDisplayScreen, User)
     */
    private function run(string $departmentId, ?string $screenId, callable $action, int $status = 200): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->screenService->canManageDepartment($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $screen = null;
        if ($screenId !== null) {
            $screen = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find($screenId);
            if (!$screen instanceof DepartmentDisplayScreen || $screen->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'Screen nicht gefunden'], 404);
            }
        }

        try {
            return new JsonResponse($action($screen, $user), $status);
        } catch (DisplayLayoutException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode, 'errors' => $e->errors] + $e->extra, $e->httpStatus);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function version(array $data): int
    {
        $version = $data['expected_version'] ?? null;
        if (!\is_int($version) || $version < 0) {
            throw new DisplayLayoutException(400, 'expected_version_required', 'expected_version (Ganzzahl) ist erforderlich.');
        }

        return $version;
    }
}
