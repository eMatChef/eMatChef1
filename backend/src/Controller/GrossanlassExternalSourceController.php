<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassExternalFetchException;
use App\Service\Grossanlass\GrossanlassExternalSourceService;
use App\Service\Grossanlass\GrossanlassExternalSyncService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Helferpool, Reiter «API-Daten»: externe Datenquellen (Verbindung, Originalantworten, Feldzuordnung, Synchronisation).
 * Dünne Schicht; die Berechtigung (GA-HV/GA-SUBHV) prüfen die Services, hier nur die Mitgliedschaft. Der Zugangsschlüssel
 * wird nie zurückgegeben. API-Fehler der Gegenseite: HTTP 502 mit anzeigbarer Meldung.
 */
#[Route('/api/departments/{departmentId}/grossanlass/external-sources', name: 'api_grossanlass_external_')]
class GrossanlassExternalSourceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GroupAccessService $groupAccess,
        private readonly GrossanlassExternalSourceService $sources,
        private readonly GrossanlassExternalSyncService $sync,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->list($d, $u));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->create($d, $u, $this->body($request)), 201);
    }

    #[Route('/{sourceId}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $departmentId, string $sourceId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->get($d, $u, $sourceId));
    }

    #[Route('/{sourceId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $sourceId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->update($d, $u, $sourceId, $this->body($request)));
    }

    #[Route('/{sourceId}/mapping', name: 'mapping', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function saveMapping(string $departmentId, string $sourceId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->saveMapping($d, $u, $sourceId, $this->body($request)));
    }

    #[Route('/{sourceId}/test', name: 'test', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function test(string $departmentId, string $sourceId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->test($d, $u, $sourceId));
    }

    /** Daten abrufen: Originalantwort holen und ablegen, ohne den Helferpool zu verändern. */
    #[Route('/{sourceId}/fetch', name: 'fetch', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function fetch(string $departmentId, string $sourceId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->fetch($d, $u, $sourceId));
    }

    #[Route('/{sourceId}/import-json', name: 'import_json', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function importJson(string $departmentId, string $sourceId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->importJson($d, $u, $sourceId, $this->body($request)['json'] ?? null), 201);
    }

    #[Route('/{sourceId}/payloads', name: 'payloads', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function payloads(string $departmentId, string $sourceId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->listPayloads($d, $u, $sourceId));
    }

    /** Eine Originalantwort mit Rohtext; `latest` = zuletzt gesehene. */
    #[Route('/{sourceId}/payloads/{payloadId}', name: 'payload', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function payload(string $departmentId, string $sourceId, string $payloadId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->getPayload($d, $u, $sourceId, $payloadId === 'latest' ? null : $payloadId));
    }

    /** Vorschau: Mapping auf die Antwort anwenden, ohne Daten zu verändern. */
    #[Route('/{sourceId}/preview', name: 'preview', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function preview(string $departmentId, string $sourceId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sync->preview($d, $u, $sourceId, $this->payloadId($request)));
    }

    /** Synchronisieren; `fetch: true` ruft vorher von der API ab, sonst wird die abgelegte Antwort übernommen. */
    #[Route('/{sourceId}/sync', name: 'sync', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sync(string $departmentId, string $sourceId, Request $request): JsonResponse
    {
        $fetch = ($this->body($request)['fetch'] ?? false) === true;

        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sync->sync($d, $u, $sourceId, $this->payloadId($request), $fetch));
    }

    #[Route('/{sourceId}/runs', name: 'runs', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function runs(string $departmentId, string $sourceId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->listRuns($d, $u, $sourceId));
    }

    #[Route('/{sourceId}/records', name: 'records', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function records(string $departmentId, string $sourceId, Request $request): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->listRecords($d, $u, $sourceId, (string) $request->query->get('state', '')));
    }

    #[Route('/{sourceId}/records/{recordId}/changes', name: 'record_changes', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function recordChanges(string $departmentId, string $sourceId, string $recordId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $d, User $u) => $this->sources->recordChanges($d, $u, $sourceId, $recordId));
    }

    /** @param callable(Department, User): mixed $fn */
    private function handle(string $departmentId, callable $fn, int $okStatus = 200): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        if (!$department->isGrossanlass()) {
            return new JsonResponse(['error' => 'Kein Grossanlass-Department'], 400);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($user->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }
        try {
            return new JsonResponse($fn($department, $user), $okStatus);
        } catch (GrossanlassExternalFetchException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 'external_fetch_failed', 'http_status' => $e->httpStatus], 502);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], str_contains($e->getMessage(), 'nicht gefunden') ? 404 : 400);
        } catch (\RuntimeException $e) {
            // Fehlende Berechtigung (Service) bzw. fehlgeschlagene Synchronisation (nichts wurde verändert)
            $forbidden = str_starts_with($e->getMessage(), 'Keine Berechtigung');

            return new JsonResponse(['error' => $e->getMessage()], $forbidden ? 403 : 500);
        }
    }

    private function payloadId(Request $request): ?string
    {
        $id = $this->body($request)['payload_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }
}
