<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassExternalChange;
use App\Entity\DepartmentGrossanlassExternalPayload;
use App\Entity\DepartmentGrossanlassExternalRecord;
use App\Entity\DepartmentGrossanlassExternalRun;
use App\Entity\DepartmentGrossanlassExternalSource;
use App\Entity\User;
use App\Service\Crypto\SecretBox;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Externe Datenquellen (API-Daten) des Helferpools: Verbindung verwalten, testen, Originalantworten abrufen oder als
 * JSON importieren, Feldzuordnung speichern, Historie lesen. Nur GA-HV/GA-SUBHV. Der Zugangsschlüssel wird verschlüsselt
 * gespeichert und nie ausgegeben ({@see serialize()} kennt nur `has_secret`). Die Übernahme in den Helferpool
 * übernimmt {@see GrossanlassExternalSyncService}.
 */
final class GrossanlassExternalSourceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassAccessService $access,
        private readonly SecretBox $secretBox,
        private readonly GrossanlassExternalHttpFetcher $fetcher,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(Department $department, User $user): array
    {
        $this->assertCanManage($department, $user);
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassExternalSource::class)
            ->findBy(['departmentId' => $department->getId()], ['name' => 'ASC']);

        return array_map(fn (DepartmentGrossanlassExternalSource $s) => $this->serialize($s), $rows);
    }

    /** @return array<string, mixed> */
    public function get(Department $department, User $user, string $sourceId): array
    {
        return $this->serialize($this->require($department, $user, $sourceId));
    }

    /**
     * @param array<string, mixed> $data name, base_url, endpoint_path, auth_type, auth_header, auth_user, secret, clear_secret, snapshot_complete
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->assertCanManage($department, $user);
        $source = new DepartmentGrossanlassExternalSource(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::EXT_SOURCE, DepartmentGrossanlassExternalSource::class),
            $department->getId(),
        );
        $this->apply($department, $source, $data, true);
        $this->entityManager->persist($source);
        $this->entityManager->flush();

        return $this->serialize($source);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $sourceId, array $data): array
    {
        $source = $this->require($department, $user, $sourceId);
        $this->apply($department, $source, $data, false);
        $this->entityManager->flush();

        return $this->serialize($source);
    }

    /**
     * Speichert die Feldzuordnung. Unvollständige Zuordnungen sind speicherbar (schrittweise Arbeit); die Vorschau meldet, was fehlt.
     *
     * @param array<string, mixed> $mapping
     * @return array<string, mixed>
     */
    public function saveMapping(Department $department, User $user, string $sourceId, array $mapping): array
    {
        $source = $this->require($department, $user, $sourceId);
        $source->setMapping(GrossanlassExternalMapping::sanitize($mapping));
        $this->entityManager->flush();

        return $this->serialize($source);
    }

    /** Verbindung prüfen (GET auf den Endpunkt, Antwort muss JSON sein). Speichert nur den Status, keine Daten. @return array<string, mixed> */
    public function test(Department $department, User $user, string $sourceId): array
    {
        $source = $this->require($department, $user, $sourceId);
        if (!$source->hasUrl()) {
            throw new \InvalidArgumentException('Für diese Quelle ist keine API-Adresse hinterlegt (nur JSON-Import)');
        }
        try {
            $result = $this->fetcher->fetch($source, $this->secretOf($source));
            json_decode($result['body'], true, 64, JSON_THROW_ON_ERROR);
            $source->recordConnection(true, null);
            $this->entityManager->flush();

            return ['ok' => true, 'http_status' => $result['status'], 'source' => $this->serialize($source)];
        } catch (GrossanlassExternalFetchException $e) {
            $source->recordConnection(false, $e->getMessage());
        } catch (\JsonException) {
            $source->recordConnection(false, 'Die Antwort ist kein gültiges JSON');
        }
        $this->entityManager->flush();

        return ['ok' => false, 'error' => $source->getLastError(), 'source' => $this->serialize($source)];
    }

    /**
     * Ruft die Antwort ab und legt das Original ab. Ändert keine Helferdaten.
     *
     * @throws GrossanlassExternalFetchException
     */
    public function fetchPayload(DepartmentGrossanlassExternalSource $source, ?User $user): DepartmentGrossanlassExternalPayload
    {
        if (!$source->hasUrl()) {
            throw new GrossanlassExternalFetchException('Für diese Quelle ist keine API-Adresse hinterlegt (nur JSON-Import)');
        }
        try {
            $result = $this->fetcher->fetch($source, $this->secretOf($source));
        } catch (GrossanlassExternalFetchException $e) {
            $source->recordConnection(false, $e->getMessage());
            $this->entityManager->flush();
            throw $e;
        }
        // Auch ungültiges JSON bleibt als Original erhalten (Fehlersuche), wird aber als Fehler gemeldet.
        $payload = $this->storePayload($source, $result['body'], DepartmentGrossanlassExternalPayload::ORIGIN_FETCH, $result['status'], $user);
        try {
            json_decode($result['body'], true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $source->recordConnection(false, 'Die Antwort ist kein gültiges JSON');
            $this->entityManager->flush();
            throw new GrossanlassExternalFetchException('Die Antwort ist kein gültiges JSON (Original wurde gespeichert)', $result['status']);
        }
        $source->recordConnection(true, null);
        $this->entityManager->flush();

        return $payload;
    }

    /** API-Abruf als eigener Aufruf («Daten abrufen»). @return array<string, mixed> */
    public function fetch(Department $department, User $user, string $sourceId): array
    {
        $source = $this->require($department, $user, $sourceId);
        $payload = $this->fetchPayload($source, $user);

        return $this->serializePayload($payload, true);
    }

    /** JSON-Testantwort einspielen (wenn kein API-Zugang vorhanden ist oder zum Nachstellen). @return array<string, mixed> */
    public function importJson(Department $department, User $user, string $sourceId, mixed $json): array
    {
        $source = $this->require($department, $user, $sourceId);
        $body = is_string($json) ? trim($json) : json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') {
            throw new \InvalidArgumentException('JSON fehlt');
        }
        if (strlen($body) > GrossanlassExternalHttpFetcher::MAX_BYTES) {
            throw new \InvalidArgumentException('JSON ist zu gross (über 5 MB)');
        }
        try {
            json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Kein gültiges JSON: ' . $e->getMessage());
        }
        $payload = $this->storePayload($source, $body, DepartmentGrossanlassExternalPayload::ORIGIN_IMPORT, null, $user);
        $this->entityManager->flush();

        return $this->serializePayload($payload, true);
    }

    /** Neueste (zuletzt gesehene) Antwort der Quelle oder die mit der angegebenen ID. */
    public function payloadFor(DepartmentGrossanlassExternalSource $source, ?string $payloadId): DepartmentGrossanlassExternalPayload
    {
        $repo = $this->entityManager->getRepository(DepartmentGrossanlassExternalPayload::class);
        $payload = $payloadId !== null && $payloadId !== ''
            ? $repo->findOneBy(['id' => $payloadId, 'sourceId' => $source->getId()])
            : $repo->findOneBy(['sourceId' => $source->getId()], ['lastSeenAt' => 'DESC', 'fetchedAt' => 'DESC']);
        if (!$payload instanceof DepartmentGrossanlassExternalPayload) {
            throw new \InvalidArgumentException($payloadId ? 'Antwort nicht gefunden' : 'Es liegt noch keine Antwort vor: zuerst Daten abrufen oder JSON importieren');
        }

        return $payload;
    }

    /** @return array<string, mixed> Antwort mit Rohtext */
    public function getPayload(Department $department, User $user, string $sourceId, ?string $payloadId): array
    {
        $source = $this->require($department, $user, $sourceId);

        return $this->serializePayload($this->payloadFor($source, $payloadId), true);
    }

    /** @return list<array<string, mixed>> Antworten ohne Rohtext, neueste zuerst */
    public function listPayloads(Department $department, User $user, string $sourceId): array
    {
        $source = $this->require($department, $user, $sourceId);
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassExternalPayload::class)
            ->findBy(['sourceId' => $source->getId()], ['fetchedAt' => 'DESC'], 50);

        return array_map(fn (DepartmentGrossanlassExternalPayload $p) => $this->serializePayload($p, false), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listRuns(Department $department, User $user, string $sourceId): array
    {
        $source = $this->require($department, $user, $sourceId);
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassExternalRun::class)
            ->findBy(['sourceId' => $source->getId()], ['startedAt' => 'DESC'], 50);

        return array_map(static fn (DepartmentGrossanlassExternalRun $r) => [
            'id' => $r->getId(),
            'payload_id' => $r->getPayloadId(),
            'started_at' => $r->getStartedAt()->format(\DateTimeInterface::ATOM),
            'finished_at' => $r->getFinishedAt()?->format(\DateTimeInterface::ATOM),
            'outcome' => $r->getOutcome(),
            'counts' => $r->counts(),
            'errors' => $r->getErrors(),
        ], $rows);
    }

    /** @return list<array<string, mixed>> Externe Datensätze mit Zustand und zugeordnetem Helfer */
    public function listRecords(Department $department, User $user, string $sourceId, string $state = ''): array
    {
        $source = $this->require($department, $user, $sourceId);
        $criteria = ['sourceId' => $source->getId()];
        if (in_array($state, [DepartmentGrossanlassExternalRecord::STATE_ACTIVE, DepartmentGrossanlassExternalRecord::STATE_CANCELLED, DepartmentGrossanlassExternalRecord::STATE_REMOVED], true)) {
            $criteria['state'] = $state;
        }
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassExternalRecord::class)->findBy($criteria, ['lastSyncedAt' => 'DESC'], 500);

        return array_map(static fn (DepartmentGrossanlassExternalRecord $r) => [
            'id' => $r->getId(),
            'external_key' => $r->getExternalKey(),
            'state' => $r->getState(),
            'data' => $r->getData(),
            'helper_id' => $r->getHelperId(),
            'payload_id' => $r->getPayloadId(),
            'first_imported_at' => $r->getFirstImportedAt()->format(\DateTimeInterface::ATOM),
            'last_synced_at' => $r->getLastSyncedAt()->format(\DateTimeInterface::ATOM),
        ], $rows);
    }

    /** @return list<array<string, mixed>> Änderungsverlauf eines Datensatzes */
    public function recordChanges(Department $department, User $user, string $sourceId, string $recordId): array
    {
        $source = $this->require($department, $user, $sourceId);
        $record = $this->entityManager->getRepository(DepartmentGrossanlassExternalRecord::class)->findOneBy(['id' => $recordId, 'sourceId' => $source->getId()]);
        if (!$record instanceof DepartmentGrossanlassExternalRecord) {
            throw new \InvalidArgumentException('Datensatz nicht gefunden');
        }
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassExternalChange::class)->findBy(['recordId' => $recordId], ['createdAt' => 'DESC'], 100);

        return array_map(static fn (DepartmentGrossanlassExternalChange $c) => [
            'id' => $c->getId(),
            'run_id' => $c->getRunId(),
            'type' => $c->getChangeType(),
            'old' => $c->getOldData(),
            'new' => $c->getNewData(),
            'created_at' => $c->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $rows);
    }

    /** Quelle des Grossanlasses laden (Berechtigung und Grossanlass-Trennung). */
    public function require(Department $department, User $user, string $sourceId): DepartmentGrossanlassExternalSource
    {
        $this->assertCanManage($department, $user);
        $source = $this->entityManager->find(DepartmentGrossanlassExternalSource::class, $sourceId);
        if (!$source instanceof DepartmentGrossanlassExternalSource || $source->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Datenquelle nicht gefunden');
        }

        return $source;
    }

    /** @return array<string, mixed> */
    public function serialize(DepartmentGrossanlassExternalSource $s): array
    {
        $repo = $this->entityManager->getRepository(DepartmentGrossanlassExternalPayload::class);
        $latest = $repo->findOneBy(['sourceId' => $s->getId()], ['lastSeenAt' => 'DESC', 'fetchedAt' => 'DESC']);
        $lastRun = $this->entityManager->getRepository(DepartmentGrossanlassExternalRun::class)->findOneBy(['sourceId' => $s->getId()], ['startedAt' => 'DESC']);
        $conn = $this->entityManager->getConnection();

        return [
            'id' => $s->getId(),
            'name' => $s->getName(),
            'base_url' => $s->getBaseUrl(),
            'endpoint_path' => $s->getEndpointPath(),
            'auth_type' => $s->getAuthType(),
            'auth_header' => $s->getAuthHeader(),
            'auth_user' => $s->getAuthUser(),
            'has_secret' => $s->hasSecret(),
            'has_url' => $s->hasUrl(),
            'mapping' => $s->getMapping() === [] ? new \stdClass() : $s->getMapping(),
            'mapping_problems' => GrossanlassExternalMapping::configProblems($s->getMapping()),
            'snapshot_complete' => $s->isSnapshotComplete(),
            'connection_status' => $s->getConnectionStatus(),
            'last_tested_at' => $s->getLastTestedAt()?->format(\DateTimeInterface::ATOM),
            'last_error' => $s->getLastError(),
            'last_success_sync_at' => $s->getLastSuccessSyncAt()?->format(\DateTimeInterface::ATOM),
            'latest_payload' => $latest instanceof DepartmentGrossanlassExternalPayload ? $this->serializePayload($latest, false) : null,
            'last_run' => $lastRun instanceof DepartmentGrossanlassExternalRun ? [
                'id' => $lastRun->getId(),
                'started_at' => $lastRun->getStartedAt()->format(\DateTimeInterface::ATOM),
                'outcome' => $lastRun->getOutcome(),
                'counts' => $lastRun->counts(),
            ] : null,
            'record_counts' => array_map('intval', $conn->fetchAllKeyValue(
                'SELECT state, COUNT(*) FROM department_grossanlass_ext_record WHERE source_id = ? GROUP BY state',
                [$s->getId()],
            )),
        ];
    }

    /** @return array<string, mixed> */
    private function serializePayload(DepartmentGrossanlassExternalPayload $p, bool $withBody): array
    {
        return [
            'id' => $p->getId(),
            'origin' => $p->getOrigin(),
            'http_status' => $p->getHttpStatus(),
            'byte_size' => $p->getByteSize(),
            'fetched_at' => $p->getFetchedAt()->format(\DateTimeInterface::ATOM),
            'last_seen_at' => $p->getLastSeenAt()->format(\DateTimeInterface::ATOM),
            'seen_count' => $p->getSeenCount(),
        ] + ($withBody ? ['body' => $p->getBody()] : []);
    }

    /** Legt die Originalantwort ab; eine identische (gleicher Hash) wird nicht doppelt gespeichert. */
    private function storePayload(DepartmentGrossanlassExternalSource $source, string $body, string $origin, ?int $status, ?User $user): DepartmentGrossanlassExternalPayload
    {
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassExternalPayload::class)
            ->findOneBy(['sourceId' => $source->getId(), 'bodyHash' => hash('sha256', $body)]);
        if ($existing instanceof DepartmentGrossanlassExternalPayload) {
            return $existing->seenAgain();
        }
        $payload = new DepartmentGrossanlassExternalPayload(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::EXT_PAYLOAD, DepartmentGrossanlassExternalPayload::class),
            $source->getId(),
            $source->getDepartmentId(),
            $origin,
            $body,
            $status,
            $user?->getId(),
        );
        $this->entityManager->persist($payload);
        $this->entityManager->flush();

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function apply(Department $department, DepartmentGrossanlassExternalSource $source, array $data, bool $creating): void
    {
        $has = static fn (string $k): bool => array_key_exists($k, $data);
        if ($creating || $has('name')) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 120) {
                throw new \InvalidArgumentException('Name ist erforderlich (höchstens 120 Zeichen)');
            }
            $dup = $this->entityManager->getConnection()->fetchOne(
                'SELECT id FROM department_grossanlass_ext_source WHERE department_id = ? AND LOWER(name) = LOWER(?) AND id <> ?',
                [$department->getId(), $name, $source->getId()],
            );
            if ($dup !== false) {
                throw new \InvalidArgumentException('Eine Datenquelle mit diesem Namen existiert bereits');
            }
            $source->setName($name);
        }
        if ($has('base_url') || $has('endpoint_path')) {
            $base = trim((string) ($data['base_url'] ?? $source->getBaseUrl()));
            $path = trim((string) ($data['endpoint_path'] ?? $source->getEndpointPath()));
            if (mb_strlen($base) > 500 || mb_strlen($path) > 300) {
                throw new \InvalidArgumentException('Adresse ist zu lang');
            }
            if ($base !== '') { // leer = reine JSON-Import-Quelle
                GrossanlassExternalHttpFetcher::buildUrl($base, $path);
            }
            $source->setBaseUrl($base)->setEndpointPath($path);
        }
        if ($has('auth_type')) {
            $type = (string) $data['auth_type'];
            if (!in_array($type, DepartmentGrossanlassExternalSource::AUTH_TYPES, true)) {
                throw new \InvalidArgumentException('Ungültige Authentifizierung');
            }
            $source->setAuthType($type);
        }
        if ($has('auth_header')) {
            $header = trim((string) $data['auth_header']);
            if ($header !== '' && !preg_match('/^[A-Za-z0-9-]{1,80}$/', $header)) {
                throw new \InvalidArgumentException('Ungültiger Headername');
            }
            $source->setAuthHeader($header === '' ? null : $header);
        }
        if ($has('auth_user')) {
            $user = trim((string) $data['auth_user']);
            if (mb_strlen($user) > 180) {
                throw new \InvalidArgumentException('Benutzername ist zu lang');
            }
            $source->setAuthUser($user === '' ? null : $user);
        }
        if ($source->getAuthType() === DepartmentGrossanlassExternalSource::AUTH_HEADER && ($source->getAuthHeader() ?? '') === '') {
            throw new \InvalidArgumentException('Für die Header-Authentifizierung ist ein Headername erforderlich');
        }
        $secret = $has('secret') ? (string) $data['secret'] : '';
        if ($secret !== '') {
            if (mb_strlen($secret) > 2000) {
                throw new \InvalidArgumentException('Schlüssel ist zu lang');
            }
            $source->setSecretEnc($this->secretBox->encrypt($secret));
        } elseif (($data['clear_secret'] ?? false) === true || $source->getAuthType() === DepartmentGrossanlassExternalSource::AUTH_NONE) {
            $source->setSecretEnc(null);
        }
        if ($has('snapshot_complete')) {
            $source->setSnapshotComplete((bool) $data['snapshot_complete']);
        }
    }

    private function secretOf(DepartmentGrossanlassExternalSource $source): ?string
    {
        if (!$source->hasSecret()) {
            return null;
        }
        try {
            return $this->secretBox->decrypt((string) $source->getSecretEnc());
        } catch (\InvalidArgumentException) {
            throw new GrossanlassExternalFetchException('Der gespeicherte Schlüssel ist nicht lesbar: bitte neu eingeben');
        }
    }

    private function assertCanManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManageStaffing($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für den Helferpool');
        }
    }
}
