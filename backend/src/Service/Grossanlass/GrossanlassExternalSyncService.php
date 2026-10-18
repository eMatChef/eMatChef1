<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassExternalChange as Change;
use App\Entity\DepartmentGrossanlassExternalPayload;
use App\Entity\DepartmentGrossanlassExternalRecord as Rec;
use App\Entity\DepartmentGrossanlassExternalRun as Run;
use App\Entity\DepartmentGrossanlassExternalSource;
use App\Entity\DepartmentGrossanlassHelper;
use App\Entity\DepartmentGrossanlassHelperAvailability;
use App\Entity\DepartmentGrossanlassStaffingAssignment as Assignment;
use App\Entity\DepartmentGrossanlassStaffingLog;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Übernimmt eine abgelegte Originalantwort einer externen Quelle in den Helferpool (Vorschau oder echte Synchronisation).
 *
 * Fachregeln: Die externe Quelle bleibt für ihre Schichten und Buchungen zuständig. Je Buchung entsteht höchstens ein
 * Helfer (Schlüssel: Quelle + externer Helferschlüssel, nie der Name) und ein Verfügbarkeitsfenster. Wird eine Buchung
 * verschoben, storniert oder entfernt, werden eMatChef-Zuteilungen des Helfers, die nicht mehr in einem gültigen Fenster
 * liegen, als Konflikt gekennzeichnet (zählen nicht zur Deckung) und beim erneuten Passen wieder freigegeben.
 * Fehlende Datensätze gelten nur dann als entfernt, wenn die Quelle als vollständig bestätigt ist und die Antwort
 * keinen Hinweis auf Unvollständigkeit enthält. Die Vorschau führt dieselbe Logik in einer zurückgerollten Transaktion aus.
 */
final class GrossanlassExternalSyncService
{
    private const MAX_ITEMS = 300;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassExternalSourceService $sources,
        private readonly GrossanlassStaffingLocks $locks,
    ) {
    }

    /** @return array<string, mixed> */
    public function preview(Department $department, User $user, string $sourceId, ?string $payloadId): array
    {
        $source = $this->sources->require($department, $user, $sourceId);
        $payload = $this->sources->payloadFor($source, $payloadId);

        return $this->apply($source->getId(), $payload->getId(), $user->getId(), true);
    }

    /**
     * @param bool $fetch zuerst von der API abrufen (sonst die neueste bzw. angegebene abgelegte Antwort verwenden)
     * @return array<string, mixed>
     */
    public function sync(Department $department, User $user, string $sourceId, ?string $payloadId, bool $fetch): array
    {
        $source = $this->sources->require($department, $user, $sourceId);
        if ($fetch) {
            try {
                $payload = $this->sources->fetchPayload($source, $user);
            } catch (GrossanlassExternalFetchException $e) {
                $run = new Run($this->id(GrossanlassIdGenerator::EXT_RUN, Run::class), $source->getId(), $source->getDepartmentId(), $user->getId());
                $run->finish(Run::OUTCOME_FAILED, self::zero(), [['level' => 'error', 'code' => 'fetch_failed', 'message' => $e->getMessage()]]);
                $this->entityManager->persist($run);
                $this->entityManager->flush();
                throw $e;
            }
        } else {
            $payload = $this->sources->payloadFor($source, $payloadId);
        }

        return $this->apply($source->getId(), $payload->getId(), $user->getId(), false);
    }

    /** @return array<string, mixed> */
    private function apply(string $sourceId, string $payloadId, ?string $userId, bool $dry): array
    {
        $source = $this->entityManager->find(DepartmentGrossanlassExternalSource::class, $sourceId);
        $payload = $this->entityManager->find(DepartmentGrossanlassExternalPayload::class, $payloadId);
        \assert($source instanceof DepartmentGrossanlassExternalSource && $payload instanceof DepartmentGrossanlassExternalPayload);

        $prepared = $this->prepare($source, $payload);
        if (isset($prepared['failed'])) {
            return $this->fail($source, $payload, $userId, $dry, $prepared['failed']);
        }

        $conn = $this->entityManager->getConnection();
        $conn->beginTransaction();
        try {
            $conn->executeStatement('SELECT pg_advisory_xact_lock(hashtext(?))', [$source->getId()]);
            $result = $this->applyRows($source, $payload, $userId, $dry, $prepared['rows'], $prepared['mapErrors'], $prepared['completeHint']);
            if ($dry) {
                $conn->rollBack();
                $this->entityManager->clear();
            } else {
                $this->entityManager->flush();
                $conn->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            if (!$this->entityManager->isOpen()) {
                throw $e;
            }
            $this->entityManager->clear();
            $source = $this->entityManager->find(DepartmentGrossanlassExternalSource::class, $sourceId);
            $payload = $this->entityManager->find(DepartmentGrossanlassExternalPayload::class, $payloadId);
            if ($source instanceof DepartmentGrossanlassExternalSource && $payload instanceof DepartmentGrossanlassExternalPayload) {
                // Bestehende Daten bleiben unverändert (Rollback); festgehalten wird nur der Fehlschlag, ohne technische Details.
                $this->fail($source, $payload, $userId, $dry, [['level' => 'error', 'code' => 'internal_error']]);
            }
            throw new \RuntimeException('Synchronisation fehlgeschlagen; bestehende Daten wurden nicht verändert', 0, $e);
        }
    }

    /**
     * @return array{failed: list<array<string, mixed>>}|array{rows: array<string, array<string, mixed>>, mapErrors: list<array<string, mixed>>, completeHint: ?bool}
     */
    private function prepare(DepartmentGrossanlassExternalSource $source, DepartmentGrossanlassExternalPayload $payload): array
    {
        $problems = GrossanlassExternalMapping::configProblems($source->getMapping());
        if ($problems !== []) {
            return ['failed' => array_map(static fn (array $p) => ['level' => 'error', 'code' => 'mapping_' . $p['code'], 'field' => $p['field']], $problems)];
        }
        try {
            $json = json_decode($payload->getBody(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['failed' => [['level' => 'error', 'code' => 'invalid_json']]];
        }
        $extract = GrossanlassExternalMapping::extractRecords($json, $source->getMapping());
        if ($extract['problem'] !== null) {
            return ['failed' => [['level' => 'error', 'code' => $extract['problem']]]];
        }
        if (count($extract['records']) > 20000) {
            return ['failed' => [['level' => 'error', 'code' => 'too_many_records']]];
        }
        $mapped = GrossanlassExternalMapping::mapRecords($extract['records'], $source->getMapping());

        return [
            'rows' => $mapped['rows'],
            'mapErrors' => array_map(static fn (array $e) => ['level' => 'error'] + $e, $mapped['errors']),
            'completeHint' => $extract['complete_hint'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $errors
     * @return array<string, mixed>
     */
    private function fail(DepartmentGrossanlassExternalSource $source, DepartmentGrossanlassExternalPayload $payload, ?string $userId, bool $dry, array $errors): array
    {
        $runId = null;
        if (!$dry) {
            $run = new Run($this->id(GrossanlassIdGenerator::EXT_RUN, Run::class), $source->getId(), $source->getDepartmentId(), $userId);
            $run->setPayloadId($payload->getId())->finish(Run::OUTCOME_FAILED, self::zero(), $errors);
            $this->entityManager->persist($run);
            $this->entityManager->flush();
            $runId = $run->getId();
        }

        return ['dry_run' => $dry, 'outcome' => Run::OUTCOME_FAILED, 'run_id' => $runId, 'payload_id' => $payload->getId(), 'counts' => self::zero(), 'resolved' => 0, 'items' => [], 'conflicts' => [], 'errors' => $errors];
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     * @param list<array<string, mixed>> $mapErrors
     * @return array<string, mixed>
     */
    private function applyRows(DepartmentGrossanlassExternalSource $source, DepartmentGrossanlassExternalPayload $payload, ?string $userId, bool $dry, array $rows, array $mapErrors, ?bool $completeHint): array
    {
        $em = $this->entityManager;
        $deptId = $source->getDepartmentId();
        $run = new Run($this->id(GrossanlassIdGenerator::EXT_RUN, Run::class), $source->getId(), $deptId, $userId);
        $run->setPayloadId($payload->getId());
        if (!$dry) {
            $em->persist($run);
        }

        /** @var array<string, Rec> $existing */
        $existing = [];
        foreach ($em->getRepository(Rec::class)->findBy(['sourceId' => $source->getId()]) as $rec) {
            $existing[$rec->getExternalKey()] = $rec;
        }
        /** @var array<string, DepartmentGrossanlassHelper> $helpers */
        $helpers = [];
        foreach ($em->getRepository(DepartmentGrossanlassHelper::class)->findBy(['extSourceId' => $source->getId()]) as $h) {
            $helpers[(string) $h->getExtKey()] = $h;
        }
        $emails = null; // lazy: E-Mail-Adressen des Grossanlasses, damit keine Person per E-Mail still zusammengeführt wird
        $counts = self::zero();
        $errors = $mapErrors;
        $items = [];
        /** @var array<string, array{cancelled: bool}> $touched */
        $touched = [];
        $addItem = function (string $action, array $row, array $changes) use (&$items): void {
            if (count($items) < self::MAX_ITEMS) {
                $items[] = [
                    'key' => $row['booking_id'],
                    'action' => $action,
                    'helper' => trim($row['helper_first_name'] . ' ' . $row['helper_last_name']),
                    'mission' => $row['mission'],
                    'shift_start' => $row['shift_start'],
                    'shift_end' => $row['shift_end'],
                    'changes' => $changes,
                ];
            }
        };

        foreach ($rows as $row) {
            $key = (string) $row['booking_id']; // numerische Schlüssel werden in PHP-Arrays zu int
            $helper = $this->ensureHelper($source, $row, $helpers, $emails, $errors);
            $data = $row;
            ksort($data);
            $hash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $state = $row['cancelled'] ? Rec::STATE_CANCELLED : Rec::STATE_ACTIVE;
            $rec = $existing[$key] ?? null;
            if ($rec === null) {
                $rec = new Rec($this->id(GrossanlassIdGenerator::EXT_RECORD, Rec::class), $source->getId(), $deptId, $key);
                $rec->setState($state)->setData($data, $hash)->setPayloadId($payload->getId())->setHelperId($helper->getId());
                $em->persist($rec);
                $em->flush();
                $this->change($rec, $run, $dry, $state === Rec::STATE_CANCELLED ? Change::TYPE_CANCELLED : Change::TYPE_CREATED, null, $data);
                ++$counts['created'];
                $addItem($state === Rec::STATE_CANCELLED ? 'cancelled' : 'created', $row, []);
                $touched[$helper->getId()] = ['cancelled' => false];
            } else {
                $existing[$key] = $rec;
                $changed = $rec->getContentHash() !== $hash || $rec->getState() !== $state || $rec->getHelperId() !== $helper->getId();
                if ($changed) {
                    $old = $rec->getData();
                    $type = match (true) {
                        $rec->getState() === Rec::STATE_REMOVED => Change::TYPE_RESTORED,
                        $state === Rec::STATE_CANCELLED && $rec->getState() !== Rec::STATE_CANCELLED => Change::TYPE_CANCELLED,
                        default => Change::TYPE_UPDATED,
                    };
                    if ($rec->getHelperId() !== null && $rec->getHelperId() !== $helper->getId()) {
                        $touched[$rec->getHelperId()] = ['cancelled' => true]; // Buchung ging an eine andere Person
                    }
                    $rec->setState($state)->setData($data, $hash)->setHelperId($helper->getId());
                    $this->change($rec, $run, $dry, $type, $old, $data);
                    ++$counts['updated'];
                    $addItem($type, $row, $this->diff($old, $data));
                    $touched[$helper->getId()] = ['cancelled' => $state === Rec::STATE_CANCELLED] + ($touched[$helper->getId()] ?? []);
                } else {
                    ++$counts['unchanged'];
                }
                $rec->setPayloadId($payload->getId())->touchSynced();
            }
            $this->syncAvailability($rec, $helper, $row, $state);
        }

        // Entfernte Datensätze nur bei nachweislich vollständiger Antwort.
        $missing = array_filter($existing, static fn (Rec $r, $k) => !isset($rows[(string) $k]) && $r->getState() !== Rec::STATE_REMOVED, ARRAY_FILTER_USE_BOTH);
        if ($missing !== []) {
            $skip = match (true) {
                !$source->isSnapshotComplete() => 'removal_skipped_not_complete',
                $completeHint === false => 'removal_skipped_incomplete_response',
                $mapErrors !== [] => 'removal_skipped_invalid_records',
                $rows === [] => 'removal_skipped_empty_response',
                default => null,
            };
            if ($skip !== null) {
                $errors[] = ['level' => 'warning', 'code' => $skip, 'count' => count($missing)];
            } else {
                foreach ($missing as $rec) {
                    $old = $rec->getData();
                    $rec->setState(Rec::STATE_REMOVED)->touchSynced();
                    $this->change($rec, $run, $dry, Change::TYPE_REMOVED, $old, null);
                    $this->removeAvailability($rec);
                    ++$counts['removed'];
                    if ($rec->getHelperId() !== null) {
                        $touched[$rec->getHelperId()] = ['cancelled' => true];
                    }
                    $addItem('removed', $old + ['booking_id' => $rec->getExternalKey(), 'helper_first_name' => '', 'helper_last_name' => '', 'mission' => null, 'shift_start' => null, 'shift_end' => null], []);
                }
            }
        }
        $em->flush();

        [$conflicts, $resolved] = $this->reconcile($source, $run, $userId, $touched, $dry);
        $counts['conflicts'] = count($conflicts);

        $hasError = array_filter($errors, static fn (array $e) => $e['level'] === 'error') !== [];
        $outcome = $hasError ? Run::OUTCOME_PARTIAL : Run::OUTCOME_OK;
        $run->finish($outcome, $counts, $errors);
        if (!$dry) {
            $source->markSyncSuccess();
        }

        return [
            'dry_run' => $dry,
            'outcome' => $outcome,
            'run_id' => $dry ? null : $run->getId(),
            'payload_id' => $payload->getId(),
            'counts' => $counts,
            'resolved' => $resolved,
            'items' => $items,
            'conflicts' => $conflicts,
            'errors' => array_slice($errors, 0, 200),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, DepartmentGrossanlassHelper> $helpers
     * @param ?array<string, string> $emails
     * @param list<array<string, mixed>> $errors
     */
    private function ensureHelper(DepartmentGrossanlassExternalSource $source, array $row, array &$helpers, ?array &$emails, array &$errors): DepartmentGrossanlassHelper
    {
        $key = $row['helper_key'];
        $helper = $helpers[$key] ?? null;
        $isNew = $helper === null;
        if ($helper === null) {
            $helper = new DepartmentGrossanlassHelper(
                GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::HELPER, DepartmentGrossanlassHelper::class),
                $source->getDepartmentId(),
            );
            $helper->setSource(DepartmentGrossanlassHelper::SOURCE_API)->setExternal($source->getId(), $key);
        }
        $email = $row['helper_email'];
        if ($email !== null && $email !== $helper->getEmail()) {
            $emails ??= $this->departmentEmails($source->getDepartmentId());
            if (isset($emails[$email]) && $emails[$email] !== $helper->getId()) {
                $errors[] = ['level' => 'warning', 'code' => 'email_belongs_to_other_helper', 'key' => $row['booking_id']];
                $email = $helper->getEmail(); // nicht zusammenführen, E-Mail nicht übernehmen
            } else {
                $emails[$email] = $helper->getId();
            }
        }
        if ($helper->getFirstName() !== $row['helper_first_name']) {
            $helper->setFirstName($row['helper_first_name']);
        }
        if ($helper->getLastName() !== $row['helper_last_name']) {
            $helper->setLastName($row['helper_last_name']);
        }
        if ($email !== $helper->getEmail()) {
            $helper->setEmail($email);
        }
        if ($row['helper_phone'] !== null && $row['helper_phone'] !== $helper->getPhone()) {
            $helper->setPhone($row['helper_phone']);
        }
        if ($isNew) {
            $this->entityManager->persist($helper);
            $this->entityManager->flush();
            $helpers[$key] = $helper;
        }

        return $helper;
    }

    /** @return array<string, string> E-Mail => Helfer-ID (eigene Spalte und Profil verknüpfter Konten) */
    private function departmentEmails(string $departmentId): array
    {
        $out = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassHelper::class)->findBy(['departmentId' => $departmentId]) as $h) {
            $email = $h->getEmail();
            if ($h->getUserId() !== null) {
                $email = $this->entityManager->find(User::class, $h->getUserId())?->getProfile()?->getEmail();
            }
            if ($email !== null && $email !== '') {
                $out[strtolower($email)] = $h->getId();
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private function syncAvailability(Rec $rec, DepartmentGrossanlassHelper $helper, array $row, string $state): void
    {
        $repo = $this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class);
        $av = $repo->findOneBy(['extRecordId' => $rec->getId()]);
        if ($state !== Rec::STATE_ACTIVE) {
            $this->removeAvailability($rec);

            return;
        }
        $from = new \DateTime($row['shift_start']);
        $to = new \DateTime($row['shift_end']);
        if ($av instanceof DepartmentGrossanlassHelperAvailability && $av->getHelperId() !== $helper->getId()) {
            $this->entityManager->remove($av);
            $this->entityManager->flush();
            $av = null;
        }
        if ($av instanceof DepartmentGrossanlassHelperAvailability) {
            if ($av->getStartsAt() != $from || $av->getEndsAt() != $to) {
                $av->setWindow($from, $to);
            }

            return;
        }
        $av = new DepartmentGrossanlassHelperAvailability(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::HELPER_AVAILABILITY, DepartmentGrossanlassHelperAvailability::class),
            $rec->getDepartmentId(),
            $helper->getId(),
            $from,
            $to,
        );
        $av->setExtRecordId($rec->getId());
        $this->entityManager->persist($av);
    }

    private function removeAvailability(Rec $rec): void
    {
        $av = $this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)->findOneBy(['extRecordId' => $rec->getId()]);
        if ($av instanceof DepartmentGrossanlassHelperAvailability) {
            $this->entityManager->remove($av);
        }
    }

    /**
     * Prüft die Zuteilungen der betroffenen externen Helfer gegen ihre (neue) Verfügbarkeit: ungültige werden zum Konflikt,
     * wieder passende freigegeben.
     *
     * @param array<string, array{cancelled?: bool}> $touched
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function reconcile(DepartmentGrossanlassExternalSource $source, Run $run, ?string $userId, array $touched, bool $dry): array
    {
        $conflicts = [];
        $resolved = 0;
        foreach ($touched as $helperId => $info) {
            $helper = $this->entityManager->find(DepartmentGrossanlassHelper::class, $helperId);
            if (!$helper instanceof DepartmentGrossanlassHelper || $helper->getExtSourceId() !== $source->getId()) {
                continue; // nur Helfer dieser Quelle
            }
            $this->locks->lockHelper($source->getDepartmentId(), $helperId);
            $windows = array_map(
                static fn (DepartmentGrossanlassHelperAvailability $a) => [$a->getStartsAt(), $a->getEndsAt()],
                $this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)->findBy(['helperId' => $helperId]),
            );
            $assignments = $this->entityManager->getRepository(Assignment::class)->findBy(['helperId' => $helperId, 'departmentId' => $source->getDepartmentId()]);
            foreach ($assignments as $a) {
                if (!$a->isActive()) {
                    continue;
                }
                $covered = GrossanlassStaffingTime::isCovered($windows, $a->getStartsAt(), $a->getEndsAt());
                if (!$covered && !$a->isConflict()) {
                    $detail = ($info['cancelled'] ?? false) ? 'booking_cancelled' : 'shift_changed';
                    $a->markConflict(['reason' => 'external_booking_changed', 'detail' => $detail, 'source_id' => $source->getId(), 'source_name' => $source->getName(), 'run_id' => $dry ? null : $run->getId()]);
                    $this->log($a, DepartmentGrossanlassStaffingLog::ACTION_CONFLICT, $userId, ['source_id' => $source->getId(), 'detail' => $detail]);
                    $conflicts[] = [
                        'assignment_id' => $a->getId(),
                        'need_id' => $a->getNeedId(),
                        'helper_id' => $helperId,
                        'helper' => trim($helper->getFirstName() . ' ' . $helper->getLastName()),
                        'starts_at' => $a->getStartsAt()->format('Y-m-d\TH:i:s'),
                        'ends_at' => $a->getEndsAt()->format('Y-m-d\TH:i:s'),
                        'detail' => $detail,
                    ];
                } elseif ($covered && $a->isConflict() && ($a->getConflictInfo()['source_id'] ?? null) === $source->getId()) {
                    $a->resolveConflict();
                    $this->log($a, DepartmentGrossanlassStaffingLog::ACTION_CONFLICT_RESOLVED, $userId, ['source_id' => $source->getId()]);
                    ++$resolved;
                }
            }
        }
        $this->entityManager->flush();

        return [$conflicts, $resolved];
    }

    /** @param array<string, mixed> $details */
    private function log(Assignment $a, string $action, ?string $userId, array $details): void
    {
        $this->entityManager->persist(new DepartmentGrossanlassStaffingLog(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::STAFFING_LOG, DepartmentGrossanlassStaffingLog::class),
            $a->getDepartmentId(),
            $a->getId(),
            $a->getHelperId(),
            $action,
            $userId,
            null,
            $details,
        ));
    }

    /**
     * @param ?array<string, mixed> $old
     * @param ?array<string, mixed> $new
     */
    private function change(Rec $rec, Run $run, bool $dry, string $type, ?array $old, ?array $new): void
    {
        if ($dry) {
            return;
        }
        $this->entityManager->persist(new Change(
            $this->id(GrossanlassIdGenerator::EXT_CHANGE, Change::class),
            $rec->getId(),
            $run->getId(),
            $type,
            $old,
            $new,
        ));
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    private function diff(array $old, array $new): array
    {
        $out = [];
        foreach (array_keys($old + $new) as $field) {
            if (($old[$field] ?? null) !== ($new[$field] ?? null)) {
                $out[] = ['field' => (string) $field, 'old' => $old[$field] ?? null, 'new' => $new[$field] ?? null];
            }
        }

        return $out;
    }

    /** @return array{created: int, updated: int, unchanged: int, removed: int, conflicts: int} */
    private static function zero(): array
    {
        return ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0, 'conflicts' => 0];
    }

    private function id(string $kind, string $class): string
    {
        return GrossanlassIdGenerator::unique($this->entityManager, $kind, $class);
    }
}
