<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassChangeRequest;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassDispatchEvent;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassUserCard;
use App\Entity\DepartmentGrossanlassTour;
use App\Entity\DepartmentGrossanlassVehicleNeed;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use App\Service\Clock\BusinessClock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lesemodell der Logistik-Disposition (`/ga/logistics/dispatch`). Führt bestehende Datensätze zusammen, ohne sie zu
 * kopieren: Fahraufträge (Einsatz mit `delivery = trip`), deren Packs, Standorte, Fahrzeugwünsche der Bauaufträge sowie
 * Fahrzeuge und Chauffeure des Anlasses. Touren gibt es noch nicht, `tours` bleibt leer.
 */
final class GrossanlassDispatchService
{
    public const SOURCE_PACKEN = 'packen';
    public const SOURCE_BESCHAFFUNG = 'beschaffung';
    public const SOURCE_RESSORT = 'ressort';
    public const SOURCE_PLANUNG = 'planung';

    public const STATUS_OPEN = 'open';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_UNDERWAY = 'underway';
    public const STATUS_DONE = 'done';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassPackService $packs,
        private GrossanlassTourService $tours,
        private GrossanlassPlaceService $places,
        private GrossanlassUebersichtService $uebersicht,
        private GrossanlassDispatchEventLog $eventLog,
        private BusinessClock $clock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Department $department, User $user): array
    {
        $this->assertCanSee($department, $user);
        $needs = $this->needMap($department);

        return [
            'now' => $this->clock->now($department)->format('Y-m-d\TH:i:s'),
            'can_manage' => $this->access->canReleaseTrip($user, $department),
            'needs' => array_values($needs),
            'tours' => array_map(fn (DepartmentGrossanlassTour $tour) => $this->tours->serialize($tour, $needs), $this->tours->tours($department)),
            'suggestions' => $this->suggestions($needs),
            'change_requests' => $this->changeRequests($department, $user, $needs),
            'drivers' => $this->drivers($department),
            'vehicles' => $this->vehicles($department),
        ];
    }

    // ── Touren (Schreiben nur für Logistikverantwortliche) ─────

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createTour(Department $department, User $user, array $data): array
    {
        $needs = $this->needMapForCommand($department, $user);
        $tour = $this->tours->create($department, $user, $data, $needs);

        return $this->tourPayload($department, $tour->getId());
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateTour(Department $department, User $user, string $tourId, array $data): array
    {
        $this->tours->update($department, $user, $tourId, $data, $this->needMapForCommand($department, $user));

        return $this->tourPayload($department, $tourId);
    }

    /** @return array<string, mixed> */
    public function addTourNeed(Department $department, User $user, string $tourId, string $needId): array
    {
        $this->tours->addNeed($department, $user, $tourId, $needId, $this->needMapForCommand($department, $user));

        return $this->tourPayload($department, $tourId);
    }

    /** @return array<string, mixed> */
    public function removeTourNeed(Department $department, User $user, string $tourId, string $needId): array
    {
        $tour = $this->tours->removeNeed($department, $user, $tourId, $needId, $this->needMapForCommand($department, $user));

        return $tour === null ? ['deleted' => true] : $this->tourPayload($department, $tourId);
    }

    /**
     * @param list<array<string, mixed>> $stops
     *
     * @return array<string, mixed>
     */
    public function replaceTourStops(Department $department, User $user, string $tourId, array $stops): array
    {
        $this->tours->replaceStops($department, $user, $tourId, $stops, $this->needMapForCommand($department, $user));

        return $this->tourPayload($department, $tourId);
    }

    /** @return array<string, mixed> */
    public function confirmTour(Department $department, User $user, string $tourId): array
    {
        $this->tours->confirm($department, $user, $tourId, $this->needMapForCommand($department, $user));

        return $this->tourPayload($department, $tourId);
    }

    /** @return array<string, mixed> */
    public function unconfirmTour(Department $department, User $user, string $tourId): array
    {
        $this->tours->unconfirm($department, $user, $tourId, $this->needMapForCommand($department, $user));

        return $this->tourPayload($department, $tourId);
    }

    public function deleteTour(Department $department, User $user, string $tourId): void
    {
        $this->tours->delete($department, $tourId, $this->needMapForCommand($department, $user));
    }


    // ── Freigabe und Ausführung (Logistikverantwortliche) ──────

    /**
     * Überführt einen Fahrzeugwunsch (`vn:<id>`) in einen echten Fahrauftrag (Einsatz `delivery = trip`). Der Fahrauftrag
     * verweist direkt auf den Wunsch, ist nicht freigegeben und hat keinen Chauffeur. Eine bestehende Tourzuordnung des
     * Wunsches wird auf den Fahrauftrag umgehängt. Nur `purpose = transport` ist überführbar und nur mit genau einem Fahrzeug an der Beschaffungslinie; sonst wird nichts erfunden.
     *
     * @return array<string, mixed> der neue Bedarf
     */
    public function convertVehicleNeed(Department $department, User $user, string $needId): array
    {
        $this->assertCanManage($department, $user);
        if (!str_starts_with($needId, 'vn:')) {
            throw new \InvalidArgumentException('Nur Fahrzeugwünsche lassen sich in einen Fahrauftrag überführen');
        }
        $vehicleNeedId = substr($needId, 3);
        $need = $this->entityManager->getRepository(DepartmentGrossanlassVehicleNeed::class)->find($vehicleNeedId);
        if (!$need instanceof DepartmentGrossanlassVehicleNeed || $need->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Fahrzeugwunsch nicht gefunden');
        }
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)->findOneBy(['vehicleNeedId' => $vehicleNeedId]);
        if ($existing instanceof DepartmentGrossanlassEinsatz) {
            throw new \InvalidArgumentException('Der Fahrzeugwunsch wurde bereits in einen Fahrauftrag überführt');
        }
        if ($need->getPurpose() !== DepartmentGrossanlassVehicleNeed::PURPOSE_TRANSPORT) {
            throw new \InvalidArgumentException('Der Fahrzeugwunsch ist ein Einsatz auf der Baustelle, kein Transportbedarf; er wird nicht zum Fahrauftrag');
        }
        if ($need->getStartsAt() === null) {
            throw new \InvalidArgumentException('Fahrzeugwunsch hat keinen Termin');
        }
        $lineId = $need->getProcurementLineId();
        $vehicles = [];
        if ($lineId !== null) {
            foreach ($this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->findBy(['procurementLineId' => $lineId]) as $commitment) {
                if ($commitment instanceof DepartmentGrossanlassCommitment && $commitment->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE) {
                    $vehicles[] = $commitment;
                }
            }
        }
        if (count($vehicles) !== 1) {
            throw new \InvalidArgumentException($vehicles === []
                ? 'Der Fahrzeugwunsch beschreibt nur einen Fahrzeugbedarf, kein konkretes Fahrzeug mit Transportauftrag; es wird kein Fahrauftrag erfunden'
                : 'Mehrere Fahrzeuge an der Beschaffungslinie; der Fahrauftrag ist nicht eindeutig');
        }
        $assignment = $this->tours->assignments($department)[$needId] ?? null;
        if ($assignment !== null && !in_array($assignment['tour']->getStatus(), [DepartmentGrossanlassTour::STATUS_DRAFT, DepartmentGrossanlassTour::STATUS_CONFIRMED], true)) {
            throw new \InvalidArgumentException('Die Tour ist unterwegs oder abgeschlossen; der Wunsch lässt sich nicht mehr überführen');
        }

        $einsatz = $this->uebersicht->createTripForVehicleNeed($department, $need, $vehicles[0]);
        if ($assignment !== null) {
            $assignment['item']->setVehicleNeedId(null);
            $assignment['item']->setEinsatzId($einsatz->getId());
            $this->entityManager->flush();
        }
        $this->eventLog->record($department, $user, DepartmentGrossanlassDispatchEvent::SUBJECT_TRIP, $einsatz->getId(), 'converted_from_vehicle_need', $needId);

        return $this->needMap($department)[$einsatz->getId()];
    }

    /**
     * Gibt einen Fahrauftrag zur Ausführung frei (`trip_released`, Pack «Fahrt frei») mit den bestehenden Einsatzregeln
     * (Pack vorhanden, Einsatz frei). Liegt der Fahrauftrag in einer Tour, muss sie bestätigt sein; deren Chauffeur und
     * Abladeort werden in den Fahrauftrag übernommen, damit Aufgaben und QR-Ablauf dieselben Daten sehen.
     *
     * @return array<string, mixed> der aktualisierte Bedarf
     */
    public function releaseNeed(Department $department, User $user, string $needId): array
    {
        $this->assertCanManage($department, $user);
        $needs = $this->needMap($department);
        $this->releaseOne($department, $user, $needs, $needId);

        return $this->needMap($department)[$needId];
    }

    /**
     * Gibt alle Fahraufträge einer bestätigten Tour frei. Zuerst werden alle geprüft, dann geschrieben.
     *
     * @return array<string, mixed>
     */
    public function releaseTour(Department $department, User $user, string $tourId): array
    {
        $this->assertCanManage($department, $user);
        $tour = $this->tours->find($department, $tourId);
        if (!$tour->isConfirmed()) {
            throw new \InvalidArgumentException('Tour zuerst bestätigen, dann Fahrten freigeben');
        }
        $needs = $this->needMap($department);
        $ids = [];
        $problems = [];
        foreach ($this->tours->tourItems($tour) as $item) {
            $need = $needs[$item->needKey()] ?? null;
            if ($need === null || empty($need['einsatz_id']) || !empty($need['released']) || $need['status'] === self::STATUS_DONE) {
                continue;
            }
            $einsatz = $this->entityManager->find(DepartmentGrossanlassEinsatz::class, $need['einsatz_id']);
            if (!$einsatz instanceof DepartmentGrossanlassEinsatz || !$einsatz->isPacked()) {
                $problems[] = sprintf('«%s»: erst nach Pack freigebbar', $need['title']);
            } elseif ($einsatz->getStatus() === DepartmentGrossanlassEinsatz::STATUS_PENDING) {
                $problems[] = sprintf('«%s»: Einsatz ist noch nicht frei', $need['title']);
            } else {
                $ids[] = $item->needKey();
            }
        }
        if ($problems !== []) {
            throw new \InvalidArgumentException('Tour nicht freigebbar: ' . implode('; ', $problems));
        }
        foreach ($ids as $id) {
            $this->releaseOne($department, $user, $needs, $id);
        }

        return $this->tourPayload($department, $tourId);
    }

    /**
     * Nimmt die Ausführungsfreigabe zurück, solange das Material nicht unterwegs ist.
     *
     * @return array<string, mixed>
     */
    public function revokeNeedRelease(Department $department, User $user, string $needId): array
    {
        $this->assertCanManage($department, $user);
        $need = $this->needMap($department)[$needId] ?? throw new \InvalidArgumentException('Transportbedarf nicht gefunden');
        $einsatz = !empty($need['einsatz_id']) ? $this->entityManager->find(DepartmentGrossanlassEinsatz::class, $need['einsatz_id']) : null;
        if (!$einsatz instanceof DepartmentGrossanlassEinsatz) {
            throw new \InvalidArgumentException('Kein Fahrauftrag');
        }
        if (!in_array($need['status'], [self::STATUS_OPEN, self::STATUS_PLANNED], true) || $einsatz->getStatus() === DepartmentGrossanlassEinsatz::STATUS_ISSUED) {
            throw new \InvalidArgumentException('Das Material ist unterwegs oder abgeliefert; die Freigabe lässt sich nicht mehr zurücknehmen');
        }
        $this->withdrawRelease($einsatz);
        $this->entityManager->flush();
        $this->eventLog->record($department, $user, DepartmentGrossanlassDispatchEvent::SUBJECT_TRIP, $needId, 'release_revoked');

        return $this->needMap($department)[$needId];
    }

    /**
     * Schliesst eine bestätigte Tour ab. Ausgelieferte Fahrten bleiben dokumentiert; nicht ausgeführte (nicht gestartete)
     * Fahrten verlieren die Freigabe und sind wieder offener Transportbedarf. Fahrten, deren Material unterwegs ist, müssen
     * zuerst abgeliefert werden (QR oder begründete Ausnahme).
     *
     * @return array<string, mixed>
     */
    public function closeTour(Department $department, User $user, string $tourId, string $note = ''): array
    {
        $this->assertCanManage($department, $user);
        $tour = $this->tours->find($department, $tourId);
        if ($tour->isClosed()) {
            throw new \InvalidArgumentException('Die Tour ist bereits abgeschlossen');
        }
        if (!$tour->isConfirmed()) {
            throw new \InvalidArgumentException('Nur bestätigte Touren lassen sich abschliessen; einen Entwurf löschen Sie');
        }
        $needs = $this->needMap($department);
        $outcomes = [];
        $underway = [];
        foreach ($this->tours->tourItems($tour) as $item) {
            $need = $needs[$item->needKey()] ?? null;
            $status = $need['status'] ?? self::STATUS_OPEN;
            if ($status === self::STATUS_UNDERWAY) {
                $underway[] = (string) ($need['title'] ?? $item->needKey());
            }
            $outcomes[$item->needKey()] = $status === self::STATUS_DONE ? 'delivered' : 'not_executed';
        }
        if ($underway !== []) {
            throw new \InvalidArgumentException('Erst abliefern (QR oder begründete Ausnahme): ' . implode(', ', $underway));
        }
        foreach ($outcomes as $needKey => $outcome) {
            if ($outcome !== 'not_executed' || empty($needs[$needKey]['einsatz_id'])) {
                continue;
            }
            $einsatz = $this->entityManager->find(DepartmentGrossanlassEinsatz::class, $needs[$needKey]['einsatz_id']);
            if ($einsatz instanceof DepartmentGrossanlassEinsatz) {
                $this->withdrawRelease($einsatz);
                // Der Chauffeur stammt aus der Tour (bei der Freigabe übernommen): der offene Bedarf wird neu disponiert.
                if ($tour->getChauffeurUserId() !== null && $einsatz->getChauffeurUserId() === $tour->getChauffeurUserId()) {
                    $einsatz->setChauffeurUserId(null);
                }
            }
        }
        $this->entityManager->flush();
        $this->tours->close($tour, $user, $outcomes);
        foreach ($outcomes as $needKey => $outcome) {
            if ($outcome === 'not_executed') {
                $this->eventLog->record($department, $user, DepartmentGrossanlassDispatchEvent::SUBJECT_TRIP, $needKey, 'not_executed', trim($tour->getName() . ' ' . $note));
            }
        }

        return $this->tourPayload($department, $tourId);
    }

    // ── Änderungswünsche der Ressorts ──────────────────────────

    /**
     * Ein Ressort schlägt eine Änderung an seinem Fahrauftrag vor. Die bestehende Planung bleibt unverändert gültig, bis
     * die Logistik entscheidet.
     *
     * @param array<string, mixed> $data note, starts_at, ends_at, destination_place_id
     *
     * @return array<string, mixed>
     */
    public function requestChange(Department $department, User $user, string $needId, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $einsatz = $this->entityManager->find(DepartmentGrossanlassEinsatz::class, $needId);
        if (!$einsatz instanceof DepartmentGrossanlassEinsatz || $einsatz->getDepartmentId() !== $department->getId() || !$einsatz->isTrip()) {
            throw new \InvalidArgumentException('Fahrauftrag nicht gefunden');
        }
        $group = $einsatz->getGroup();
        $allowed = $this->access->canReleaseTrip($user, $department)
            || $this->access->canSeeAnlassOverview($user, $department)
            || ($group !== null && $this->access->canSubmitEinsatz($user, $department, $group));
        if (!$allowed) {
            throw new \RuntimeException('Keine Berechtigung für Änderungswünsche an diesem Fahrauftrag');
        }
        if ($einsatz->getStatus() === DepartmentGrossanlassEinsatz::STATUS_RETURNED) {
            throw new \InvalidArgumentException('Der Fahrauftrag ist abgeschlossen');
        }
        $from = $this->wallDate($data['starts_at'] ?? null);
        $to = $this->wallDate($data['ends_at'] ?? null);
        if (($from === null) !== ($to === null)) {
            throw new \InvalidArgumentException('Neuer Zeitraum: Beginn und Ende angeben');
        }
        if ($from !== null && $to <= $from) {
            throw new \InvalidArgumentException('Das Ende muss nach dem Beginn liegen');
        }
        $destination = isset($data['destination_place_id']) && $data['destination_place_id'] !== '' ? (string) $data['destination_place_id'] : null;
        if ($destination !== null) {
            $place = $this->entityManager->find(DepartmentGrossanlassPlace::class, $destination);
            if (!$place instanceof DepartmentGrossanlassPlace || $place->getDepartmentId() !== $department->getId()) {
                throw new \InvalidArgumentException('Ziel-Ort nicht gefunden');
            }
        }
        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '' && $from === null && $destination === null) {
            throw new \InvalidArgumentException('Beschreiben Sie die gewünschte Änderung');
        }

        $request = new DepartmentGrossanlassChangeRequest();
        $request->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::CHANGE_REQUEST, DepartmentGrossanlassChangeRequest::class));
        $request->setDepartment($department)->setEinsatzId($einsatz->getId())->setRequestedByUserId($user->getId())->setNote(mb_substr($note, 0, 2000));
        $request->setProposedWindow($from, $to)->setProposedDestinationPlaceId($destination);
        $this->entityManager->persist($request);
        $this->entityManager->flush();
        $this->eventLog->record($department, $user, DepartmentGrossanlassDispatchEvent::SUBJECT_REQUEST, $request->getId(), 'requested', $note);

        return $this->serializeRequest($request, $this->needMap($department));
    }

    /**
     * Logistik: alle offenen und die letzten entschiedenen Wünsche; sonst nur die eigenen.
     *
     * @param array<string, array<string, mixed>>|null $needs
     *
     * @return list<array<string, mixed>>
     */
    public function changeRequests(Department $department, User $user, ?array $needs = null): array
    {
        $needs ??= $this->needMap($department);
        $manager = $this->access->canReleaseTrip($user, $department);
        $criteria = ['departmentId' => $department->getId()];
        if (!$manager) {
            $criteria['requestedByUserId'] = $user->getId();
        }
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassChangeRequest::class)->findBy($criteria, ['createdAt' => 'DESC'], 100);

        return array_map(fn (DepartmentGrossanlassChangeRequest $row) => $this->serializeRequest($row, $needs), $rows);
    }

    /**
     * Logistik entscheidet: `accept` übernimmt den Vorschlag, `adjust` übernimmt angepasste Werte (starts_at, ends_at,
     * destination_place_id), `reject` lehnt mit Begründung ab. Eine bestätigte Tour wird durch eine Änderung wieder zum Entwurf.
     *
     * @param array<string, mixed> $data decision, note, starts_at, ends_at, destination_place_id
     *
     * @return array<string, mixed>
     */
    public function decideChangeRequest(Department $department, User $user, string $requestId, array $data): array
    {
        $this->assertCanManage($department, $user);
        $request = $this->entityManager->getRepository(DepartmentGrossanlassChangeRequest::class)->findOneBy(['id' => $requestId, 'departmentId' => $department->getId()]);
        if (!$request instanceof DepartmentGrossanlassChangeRequest) {
            throw new \InvalidArgumentException('Änderungswunsch nicht gefunden');
        }
        if (!$request->isOpen()) {
            throw new \InvalidArgumentException('Der Änderungswunsch ist bereits entschieden');
        }
        $decision = (string) ($data['decision'] ?? '');
        $note = trim((string) ($data['note'] ?? ''));
        $now = $this->clock->now($department);
        if ($decision === 'reject') {
            if (mb_strlen($note) < 5) {
                throw new \InvalidArgumentException('Eine Ablehnung braucht eine Begründung');
            }
            $request->decide(DepartmentGrossanlassChangeRequest::STATUS_REJECTED, $note, $user->getId(), $now);
        } elseif (in_array($decision, ['accept', 'adjust'], true)) {
            $payload = [];
            if ($decision === 'accept') {
                $from = $request->getProposedStartsAt();
                $to = $request->getProposedEndsAt();
                $destination = $request->getProposedDestinationPlaceId();
            } else {
                $from = $this->wallDate($data['starts_at'] ?? null);
                $to = $this->wallDate($data['ends_at'] ?? null);
                $destination = isset($data['destination_place_id']) && $data['destination_place_id'] !== '' ? (string) $data['destination_place_id'] : null;
                if ($from === null && $to === null && $destination === null) {
                    throw new \InvalidArgumentException('Angepasste Werte fehlen');
                }
            }
            if ($from !== null && $to !== null) {
                $payload['from'] = $from->format('Y-m-d\TH:i:s');
                $payload['to'] = $to->format('Y-m-d\TH:i:s');
            }
            if ($destination !== null) {
                $payload['destination_place_id'] = $destination;
            }
            if ($payload !== []) {
                $this->uebersicht->updateEinsatz($department, $user, $request->getEinsatzId(), $payload);
                $this->tours->noteNeedChanged($department, $user, $request->getEinsatzId(), 'Änderungswunsch umgesetzt');
            }
            $request->decide($decision === 'accept' ? DepartmentGrossanlassChangeRequest::STATUS_ACCEPTED : DepartmentGrossanlassChangeRequest::STATUS_ADJUSTED, $note, $user->getId(), $now);
        } else {
            throw new \InvalidArgumentException('Entscheid: accept, adjust oder reject');
        }
        $this->entityManager->flush();
        $this->eventLog->record($department, $user, DepartmentGrossanlassDispatchEvent::SUBJECT_REQUEST, $request->getId(), 'decided_' . $request->getStatus(), $note);

        return $this->serializeRequest($request, $this->needMap($department));
    }

    /**
     * @param array<string, array<string, mixed>> $needs
     *
     * @return array<string, mixed>
     */
    private function serializeRequest(DepartmentGrossanlassChangeRequest $row, array $needs): array
    {
        $user = $row->getRequestedByUserId() !== null ? $this->entityManager->getRepository(User::class)->find($row->getRequestedByUserId()) : null;

        return [
            'id' => $row->getId(),
            'need_id' => $row->getEinsatzId(),
            'title' => (string) ($needs[$row->getEinsatzId()]['title'] ?? ''),
            'requested_by' => $user instanceof User ? ($user->getProfile()?->getDisplayName() ?? '') : '',
            'note' => $row->getNote(),
            'proposed_starts_at' => $row->getProposedStartsAt()?->format('Y-m-d\TH:i:s'),
            'proposed_ends_at' => $row->getProposedEndsAt()?->format('Y-m-d\TH:i:s'),
            'proposed_destination_place_id' => $row->getProposedDestinationPlaceId(),
            'status' => $row->getStatus(),
            'decision_note' => $row->getDecisionNote(),
            'decided_at' => $row->getDecidedAt()?->format('Y-m-d\TH:i:s'),
            'created_at' => $row->getCreatedAt()->format('Y-m-d\TH:i:s'),
        ];
    }

    /** @param array<string, array<string, mixed>> $needs */
    private function releaseOne(Department $department, User $user, array $needs, string $needId): void
    {
        $need = $needs[$needId] ?? throw new \InvalidArgumentException('Transportbedarf nicht gefunden');
        if (empty($need['einsatz_id'])) {
            throw new \InvalidArgumentException('Ein Fahrzeugwunsch ist noch kein Fahrauftrag und lässt sich nicht freigeben');
        }
        if (!in_array($need['status'], [self::STATUS_OPEN, self::STATUS_PLANNED], true)) {
            throw new \InvalidArgumentException('Der Fahrauftrag ist bereits unterwegs oder erledigt');
        }
        if (!empty($need['released'])) {
            return;
        }
        $payload = ['trip_released' => true];
        $note = '';
        if (empty($need['tour_id']) && empty($need['chauffeur_user_id'])) {
            throw new \InvalidArgumentException('Chauffeur zuteilen: den Fahrauftrag zuerst in eine Tour mit Chauffeur legen');
        }
        if (!empty($need['tour_id'])) {
            $tour = $this->tours->find($department, (string) $need['tour_id']);
            if (!$tour->isConfirmed()) {
                throw new \InvalidArgumentException('Tour zuerst bestätigen, dann den Fahrauftrag freigeben');
            }
            $payload['chauffeur_user_id'] = $tour->getChauffeurUserId();
            $note = $tour->getName();
            if ($tour->getChauffeurUserId() === null) {
                throw new \InvalidArgumentException('Der Tour fehlt ein Chauffeur');
            }
            if (empty($need['to_place_id'])) {
                foreach ($this->tours->tourItems($tour) as $item) {
                    if ($item->needKey() !== $needId) {
                        continue;
                    }
                    foreach ($this->tours->tourStops($tour) as $stop) {
                        if ($stop->getItemId() === $item->getId() && $stop->getKind() === 'unload' && $stop->getPlaceId() !== null) {
                            $payload['destination_place_id'] = $stop->getPlaceId();
                        }
                    }
                }
            }
        }
        $this->uebersicht->updateEinsatz($department, $user, $needId, $payload);
        $this->eventLog->record($department, $user, DepartmentGrossanlassDispatchEvent::SUBJECT_TRIP, $needId, 'released', $note);
    }

    private function withdrawRelease(DepartmentGrossanlassEinsatz $einsatz): void
    {
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassPack::class)->findBy(['einsatzId' => $einsatz->getId()]) as $pack) {
            if ($pack instanceof DepartmentGrossanlassPack) {
                $this->packs->revokeTripRelease($pack);
            }
        }
        $einsatz->setTripReleasedAt(null);
    }

    /** @param list<DepartmentGrossanlassPack> $packs */
    private function packStatus(array $packs): string
    {
        if ($packs === []) {
            return 'none';
        }
        $statuses = array_unique(array_map(static fn (DepartmentGrossanlassPack $p) => $p->getStatus(), $packs));
        foreach ([DepartmentGrossanlassPack::STATUS_IN_TRANSIT, DepartmentGrossanlassPack::STATUS_TRIP_RELEASED, DepartmentGrossanlassPack::STATUS_STAGING] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return DepartmentGrossanlassPack::STATUS_AT_PLACE;
    }

    private function assertCanManage(Department $department, User $user): void
    {
        $this->assertCanSee($department, $user);
        if (!$this->access->canReleaseTrip($user, $department)) {
            throw new \RuntimeException('Nur Logistikverantwortliche dürfen Fahrten freigeben und Touren abschliessen');
        }
    }

    private function wallDate(mixed $value): ?\DateTime
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return new \DateTime(substr(str_replace('T', ' ', trim($value)), 0, 19));
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültige Zeitangabe');
        }
    }

    /** @return array<string, mixed> */
    private function tourPayload(Department $department, string $tourId): array
    {
        $needs = $this->needMap($department);
        foreach ($this->tours->tours($department) as $tour) {
            if ($tour->getId() === $tourId) {
                return $this->tours->serialize($tour, $needs);
            }
        }
        throw new \InvalidArgumentException('Tour nicht gefunden');
    }

    private function assertCanSee(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeAnlassOverview($user, $department) && !$this->access->canOperateAusgabe($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für die Logistik-Disposition');
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function needMapForCommand(Department $department, User $user): array
    {
        $this->assertCanSee($department, $user);
        if (!$this->access->canReleaseTrip($user, $department)) {
            throw new \RuntimeException('Nur Logistikverantwortliche dürfen Touren disponieren');
        }

        return $this->needMap($department);
    }

    /**
     * Alle Transportbedarfe des Departments (Fahraufträge und noch nicht zugeordnete Fahrzeugwünsche), je mit Tour-Zuordnung.
     * Ein Bedarf in einer Tour gilt als disponiert; unterwegs/erledigt bleibt aus Einsatz und Packs.
     *
     * @return array<string, array<string, mixed>>
     */
    public function needMap(Department $department): array
    {
        $placeNames = $this->placeNames($department);
        $assignments = $this->tours->assignments($department);
        $needs = [];
        $coveredLineIds = [];
        $convertedIds = [];
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)->findBy(
            ['departmentId' => $department->getId(), 'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_TRIP, 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ],
            ['startsAt' => 'ASC'],
        );
        foreach ($rows as $row) {
            if (!$row instanceof DepartmentGrossanlassEinsatz) {
                continue;
            }
            $lineId = $row->getCommitment()?->getProcurementLine()?->getId();
            if ($lineId !== null) {
                $coveredLineIds[$lineId] = true;
            }
            if ($row->getVehicleNeedId() !== null) {
                $convertedIds[$row->getVehicleNeedId()] = true;
            }
            $needs[$row->getId()] = $this->tripNeed($row, $placeNames);
        }
        foreach ($this->vehicleNeeds($department, $coveredLineIds, $assignments, $convertedIds) as $need) {
            $needs[$need['id']] = $need;
        }
        foreach ($needs as $id => &$need) {
            $tour = $assignments[$id]['tour'] ?? null;
            $need['tour_id'] = $tour?->getId();
            $need['tour_status'] = $tour?->getStatus();
            if ($tour !== null && $need['status'] === self::STATUS_OPEN) {
                $need['status'] = self::STATUS_PLANNED;
            }
        }
        unset($need);

        return $needs;
    }

    /**
     * Einfache, nachvollziehbare Vorschläge: offene Bedarfe ohne Tour am selben Tag mit demselben Ziel, sonst mit
     * überlappendem oder höchstens zwei Stunden entferntem Zeitfenster. Nur Hinweis, nie verbindlich.
     *
     * @param array<string, array<string, mixed>> $needs
     *
     * @return list<array{id: string, reason: string, need_ids: list<string>, day: string, starts_at: string, ends_at: string}>
     */
    private function suggestions(array $needs): array
    {
        $open = array_values(array_filter($needs, static fn (array $n) => $n['status'] === self::STATUS_OPEN && empty($n['tour_id'])));
        $byDay = [];
        foreach ($open as $need) {
            $byDay[substr($need['starts_at'], 0, 10)][] = $need;
        }
        ksort($byDay);
        $out = [];
        foreach ($byDay as $day => $list) {
            $used = [];
            $groups = [];
            foreach ($list as $need) {
                $groups[$need['to'] !== '' ? $need['to'] : ('#' . $need['id'])][] = $need;
            }
            foreach ($groups as $group) {
                if (count($group) > 1) {
                    $out[] = $this->suggestion('sameTarget', $day, $group);
                    foreach ($group as $need) {
                        $used[$need['id']] = true;
                    }
                }
            }
            $rest = array_values(array_filter($list, static fn (array $n) => !isset($used[$n['id']])));
            usort($rest, static fn (array $a, array $b) => strcmp($a['starts_at'], $b['starts_at']));
            $chain = [];
            $chainEnd = null;
            foreach ([...$rest, null] as $need) {
                $startsNew = $need === null || $chainEnd === null || strtotime($need['starts_at']) > strtotime($chainEnd) + 7200;
                if ($startsNew && count($chain) > 1) {
                    $out[] = $this->suggestion('sameWindow', $day, $chain);
                }
                if ($startsNew) {
                    $chain = [];
                    $chainEnd = null;
                }
                if ($need !== null) {
                    $chain[] = $need;
                    $chainEnd = $chainEnd === null || strcmp($need['ends_at'], $chainEnd) > 0 ? $need['ends_at'] : $chainEnd;
                }
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $group
     *
     * @return array{id: string, reason: string, need_ids: list<string>, day: string, starts_at: string, ends_at: string}
     */
    private function suggestion(string $reason, string $day, array $group): array
    {
        $ids = array_map(static fn (array $n) => (string) $n['id'], $group);

        return [
            'id' => substr(md5($reason . implode('|', $ids)), 0, 12),
            'reason' => $reason,
            'need_ids' => $ids,
            'day' => $day,
            'starts_at' => min(array_map(static fn (array $n) => $n['starts_at'], $group)),
            'ends_at' => max(array_map(static fn (array $n) => $n['ends_at'], $group)),
        ];
    }

    /**
     * @param array<string, string> $placeNames
     *
     * @return array<string, mixed>
     */
    private function tripNeed(DepartmentGrossanlassEinsatz $row, array $placeNames): array
    {
        $commitment = $row->getCommitment();
        $packs = $this->packs->serializePacks($row);
        $entities = $this->entityManager->getRepository(DepartmentGrossanlassPack::class)
            ->findBy(['einsatzId' => $row->getId()]);

        $incomplete = array_values(array_filter($packs, static fn (array $p) => $p['incomplete']));
        $hasPacks = $packs !== [];
        $ready = $row->isPacked() || ($hasPacks && $incomplete === []);
        $warning = $incomplete[0]['warning'] ?? null;

        $cargo = [];
        foreach ($packs as $pack) {
            foreach ($pack['lines'] as $line) {
                $cargo[] = sprintf('%d× %s', $line['qty_packed'] > 0 ? $line['qty_packed'] : $line['qty_needed'], $line['label']);
            }
        }
        $name = $commitment?->getName() ?? $row->getWho();
        if ($cargo === []) {
            $cargo[] = sprintf('%d× %s', $row->getQty(), $name);
        }

        $group = $row->getGroup() ?? $commitment?->getProcurementLine()?->getGroup();
        $fromPlace = null;
        $fromPlaceId = null;
        foreach ($packs as $pack) {
            if ($pack['current_place_name']) {
                $fromPlace = $pack['current_place_name'];
                $fromPlaceId = $pack['current_place_id'];
                break;
            }
        }

        return [
            'id' => $row->getId(),
            'einsatz_id' => $row->getId(),
            'vehicle_need_id' => null,
            'converted_from_vehicle_need_id' => $row->getVehicleNeedId(),
            'source' => $hasPacks ? self::SOURCE_PACKEN : ($commitment?->getProcurementLine() !== null ? self::SOURCE_BESCHAFFUNG : self::SOURCE_RESSORT),
            'title' => $name,
            'from' => $fromPlace,
            'from_place_id' => $fromPlaceId,
            'to_place_id' => $row->getDestinationPlaceId(),
            'to' => $row->getDestinationPlaceId() !== null ? ($placeNames[$row->getDestinationPlaceId()] ?? '') : '',
            'starts_at' => $row->getStartsAt()->format('Y-m-d\TH:i:s'),
            'ends_at' => $row->getEndsAt()->format('Y-m-d\TH:i:s'),
            'ready' => $ready,
            'ready_note' => $ready ? null : ($warning ?? ($hasPacks ? null : 'noPack')),
            'cargo' => implode(', ', $cargo),
            'target' => $group?->getName() ?? '',
            'requirements' => $commitment?->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE ? ['driveCard'] : [],
            'scope' => $commitment?->getProcurementLine() !== null ? 'extern' : 'intern',
            'status' => $this->tripStatus($row, $entities),
            'released' => $row->isReleasedForExecution(),
            'einsatz_status' => $row->getStatus(),
            'chauffeur_user_id' => $row->getChauffeurUserId(),
            'vehicle_id' => $commitment?->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE ? $commitment->getId() : null,
            'pack_ids' => array_map(static fn (array $p) => $p['id'], $packs),
            'pack_status' => $this->packStatus($entities),
        ];
    }

    /**
     * Offen: weder Chauffeur noch Freigabe. Disponiert: Chauffeur oder «Fahrt frei». Unterwegs: ausgegeben oder Pack
     * unterwegs. Erledigt: zurückgegeben oder alle Packs am Ziel.
     *
     * @param list<DepartmentGrossanlassPack> $packs
     */
    private function tripStatus(DepartmentGrossanlassEinsatz $row, array $packs): string
    {
        $allAtPlace = $packs !== [] && array_reduce(
            $packs,
            static fn (bool $carry, DepartmentGrossanlassPack $p) => $carry && $p->getStatus() === DepartmentGrossanlassPack::STATUS_AT_PLACE,
            true,
        );
        if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_RETURNED || $allAtPlace) {
            return self::STATUS_DONE;
        }
        $inTransit = array_filter($packs, static fn (DepartmentGrossanlassPack $p) => $p->getStatus() === DepartmentGrossanlassPack::STATUS_IN_TRANSIT);
        if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_ISSUED || $inTransit !== []) {
            return self::STATUS_UNDERWAY;
        }
        if ($row->getChauffeurUserId() !== null || $row->isTripReleased()) {
            return self::STATUS_PLANNED;
        }

        return self::STATUS_OPEN;
    }

    /**
     * Fahrzeugwünsche der Bauaufträge ohne zugehörigen Fahrauftrag (Verknüpfung über die Beschaffungslinie).
     *
     * @param array<string, true> $coveredLineIds
     * @param array<string, true> $convertedIds Fahrzeugwünsche mit direkt verknüpftem Fahrauftrag (nie doppelt als Bedarf)
     * @param array<string, mixed> $assignments Bedarfs-ID → Tour; Fahrzeugwünsche in einer Tour bleiben sichtbar (mit Tour), auch bei vorhandenem Fahrauftrag
     *
     * @return list<array<string, mixed>>
     */
    private function vehicleNeeds(Department $department, array $coveredLineIds, array $assignments, array $convertedIds = []): array
    {
        $out = [];
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassVehicleNeed::class)
            ->findBy(['departmentId' => $department->getId()], ['startsAt' => 'ASC']);
        foreach ($rows as $row) {
            if (!$row instanceof DepartmentGrossanlassVehicleNeed || $row->getStartsAt() === null) {
                continue;
            }
            if (isset($convertedIds[$row->getId()])) {
                continue;
            }
            $lineId = $row->getProcurementLineId();
            if ($lineId !== null && isset($coveredLineIds[$lineId]) && !isset($assignments['vn:' . $row->getId()])) {
                continue;
            }
            $start = $row->getStartsAt();
            $end = (clone $start)->modify(sprintf('+%d minutes', max(15, (int) $row->getDurationMinutes())));
            $out[] = [
                'id' => 'vn:' . $row->getId(),
                'einsatz_id' => null,
                'vehicle_need_id' => $row->getId(),
                'purpose' => $row->getPurpose(),
                'source' => self::SOURCE_PLANUNG,
                'title' => $row->getVehicleLabel(),
                'from' => null,
                'from_place_id' => null,
                'to_place_id' => $this->places->findForGroup($department, $row->getGroupId())?->getId(),
                'to' => $row->getGroup()->getName(),
                'starts_at' => $start->format('Y-m-d\TH:i:s'),
                'ends_at' => $end->format('Y-m-d\TH:i:s'),
                'ready' => true,
                'ready_note' => null,
                'cargo' => $row->getTaskLabel() !== '' ? $row->getVehicleLabel() . ' · ' . $row->getTaskLabel() : $row->getVehicleLabel(),
                'target' => $row->getGroup()->getName(),
                'requirements' => [],
                'scope' => 'intern',
                'status' => self::STATUS_OPEN,
                'released' => false,
                'einsatz_status' => null,
                'chauffeur_user_id' => null,
                'vehicle_id' => null,
                'pack_ids' => [],
                'pack_status' => 'none',
            ];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function placeNames(Department $department): array
    {
        $names = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findBy(['departmentId' => $department->getId()]) as $place) {
            if ($place instanceof DepartmentGrossanlassPlace) {
                $names[$place->getId()] = $place->getName();
            }
        }

        return $names;
    }

    /** @return list<array{id: string, name: string}> */
    private function drivers(Department $department): array
    {
        $out = [];
        $cards = $this->entityManager->getRepository(DepartmentGrossanlassUserCard::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($cards as $card) {
            if (!$card instanceof DepartmentGrossanlassUserCard || !$card->getMayDrive()) {
                continue;
            }
            $user = $this->entityManager->getRepository(User::class)->find($card->getUserId());
            if (!$user instanceof User) {
                continue;
            }
            $out[] = ['id' => $user->getId(), 'name' => $user->getProfile()?->getDisplayName() ?? 'Unbekannt'];
        }
        usort($out, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /** @return list<array{id: string, name: string, kind: string, trailer_ok: bool, defect: bool}> */
    private function vehicles(Department $department): array
    {
        $out = [];
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->findBy(
            ['departmentId' => $department->getId(), 'family' => DepartmentGrossanlassCommitment::FAMILY_VEHICLE],
            ['name' => 'ASC'],
        );
        foreach ($rows as $row) {
            if ($row instanceof DepartmentGrossanlassCommitment) {
                $out[] = ['id' => $row->getId(), 'name' => $row->getName(), 'kind' => '', 'trailer_ok' => false, 'defect' => false];
            }
        }

        return $out;
    }
}
