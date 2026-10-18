<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassTour;
use App\Entity\DepartmentGrossanlassTourEvent;
use App\Entity\DepartmentGrossanlassTourItem;
use App\Entity\DepartmentGrossanlassTourStop;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transporttouren der Logistik-Disposition. Eine Tour fasst bestehende Transportbedarfe (Fahraufträge und Fahrzeugwünsche)
 * mit Fahrzeug, Chauffeur und geordneten Stopps zusammen; sie kopiert weder Material noch Fahraufträge und setzt weder
 * `tripReleasedAt` noch Pack-/Einsatzstatus. Die Bedarfe kommen als `$needs` (Schlüssel = Bedarfs-ID) vom
 * {@see GrossanlassDispatchService}, der auch die Berechtigung prüft.
 *
 * Bedarf: id, einsatz_id, vehicle_need_id, title, from, to, from_place_id, to_place_id, source, starts_at, ends_at, status,
 * chauffeur_user_id, vehicle_id, tour_id.
 */
final class GrossanlassTourService
{
    public const EFFECTIVE_CLOSED = 'closed';
    public const EFFECTIVE_UNDERWAY = 'underway';

    /** @var array<string, array<string, array{id: string, name: string, status: string}>> Abteilung → Einsatz-ID → Tour (nur für die Planungsansicht) */
    private array $infoCache = [];
    public const EFFECTIVE_DONE = 'done';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassUserCardService $cards,
        private BusinessClock $clock,
    ) {}

    // ── Befehle ────────────────────────────────────────────────

    /**
     * @param array<string, mixed>                $data
     * @param array<string, array<string, mixed>> $needs
     */
    public function create(Department $department, User $user, array $data, array $needs): DepartmentGrossanlassTour
    {
        $needIds = array_values(array_unique(array_map('strval', (array) ($data['need_ids'] ?? []))));
        if ($needIds === []) {
            throw new \InvalidArgumentException('Mindestens ein Transportbedarf ist erforderlich');
        }
        foreach ($needIds as $id) {
            $this->assertAssignable($needs, $id);
        }

        $tour = new DepartmentGrossanlassTour();
        $tour->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TOUR, DepartmentGrossanlassTour::class));
        $tour->setDepartment($department);
        $tour->setCreatedByUserId($user->getId());
        $tour->setName(trim((string) ($data['name'] ?? '')) !== '' ? $this->name((string) $data['name']) : $this->nextName($department));
        $this->applyResources($department, $tour, $data);
        $this->entityManager->persist($tour);

        $sort = 0;
        $stops = [];
        foreach ($needIds as $id) {
            $item = $this->newItem($tour, $needs[$id], $sort++);
            array_push($stops, ...$this->defaultStops($tour, $item, $needs[$id]));
        }
        usort($stops, static fn (DepartmentGrossanlassTourStop $a, DepartmentGrossanlassTourStop $b) => ($a->getPlannedAt()?->getTimestamp() ?? 0) <=> ($b->getPlannedAt()?->getTimestamp() ?? 0));
        foreach ($stops as $i => $stop) {
            $stop->setSortOrder($i);
        }
        $this->log($tour, $user, 'created', implode(', ', array_map(static fn (string $id) => (string) ($needs[$id]['title'] ?? $id), $needIds)));
        $this->entityManager->flush();

        return $tour;
    }

    /**
     * @param array<string, mixed>                $data
     * @param array<string, array<string, mixed>> $needs
     */
    public function update(Department $department, User $user, string $tourId, array $data, array $needs): DepartmentGrossanlassTour
    {
        $tour = $this->editable($department, $tourId, $needs);
        $before = [$tour->getName(), $tour->getVehicleId(), $tour->getChauffeurUserId(), $tour->getTrailer()];
        if (array_key_exists('name', $data) && trim((string) $data['name']) !== '') {
            $tour->setName($this->name((string) $data['name']));
        }
        $this->applyResources($department, $tour, $data);
        if ($before !== [$tour->getName(), $tour->getVehicleId(), $tour->getChauffeurUserId(), $tour->getTrailer()]) {
            $this->markChanged($tour, $user, 'updated', 'Name, Fahrzeug, Chauffeur oder Anhänger');
        }
        $this->entityManager->flush();

        return $tour;
    }

    /** @param array<string, array<string, mixed>> $needs */
    public function addNeed(Department $department, User $user, string $tourId, string $needId, array $needs): DepartmentGrossanlassTour
    {
        $tour = $this->editable($department, $tourId, $needs);
        $this->assertAssignable($needs, $needId);
        $items = $this->items($tour);
        $item = $this->newItem($tour, $needs[$needId], count($items));
        $stops = $this->stops($tour);
        $next = count($stops);
        foreach ($this->defaultStops($tour, $item, $needs[$needId]) as $stop) {
            $stop->setSortOrder($next++);
        }
        $this->markChanged($tour, $user, 'need_added', (string) $needs[$needId]['title']);
        $this->entityManager->flush();

        return $tour;
    }

    /** @param array<string, array<string, mixed>> $needs */
    public function removeNeed(Department $department, User $user, string $tourId, string $needId, array $needs): ?DepartmentGrossanlassTour
    {
        $tour = $this->editable($department, $tourId, $needs);
        $item = null;
        foreach ($this->items($tour) as $candidate) {
            if ($candidate->needKey() === $needId) {
                $item = $candidate;
            }
        }
        if ($item === null) {
            throw new \InvalidArgumentException('Bedarf gehört nicht zu dieser Tour');
        }
        foreach ($this->stops($tour) as $stop) {
            if ($stop->getItemId() === $item->getId()) {
                $this->entityManager->remove($stop);
            }
        }
        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->renumber($tour);
        $title = (string) ($needs[$needId]['title'] ?? $needId);
        if ($this->items($tour) === []) {
            $this->entityManager->remove($tour);
            $this->entityManager->flush();

            return null;
        }
        $this->markChanged($tour, $user, 'need_removed', $title);
        $this->entityManager->flush();

        return $tour;
    }

    /**
     * Ersetzt Reihenfolge, Art, Standort und geplante Zeit der Stopps.
     *
     * @param list<array<string, mixed>>          $rows
     * @param array<string, array<string, mixed>> $needs
     */
    public function replaceStops(Department $department, User $user, string $tourId, array $rows, array $needs): DepartmentGrossanlassTour
    {
        $tour = $this->editable($department, $tourId, $needs);
        $items = [];
        foreach ($this->items($tour) as $item) {
            $items[$item->needKey()] = $item;
        }
        $new = [];
        foreach (array_values($rows) as $i => $row) {
            $kind = (string) ($row['kind'] ?? '');
            if (!in_array($kind, DepartmentGrossanlassTourStop::KINDS, true)) {
                throw new \InvalidArgumentException('Ungültige Stopp-Art');
            }
            $needKey = isset($row['need_id']) && $row['need_id'] !== '' ? (string) $row['need_id'] : null;
            if ($needKey !== null && !isset($items[$needKey])) {
                throw new \InvalidArgumentException('Stopp verweist auf einen Bedarf ausserhalb der Tour');
            }
            $new[] = ['kind' => $kind, 'item' => $needKey !== null ? $items[$needKey] : null, 'key' => $needKey, 'place' => $row['place_id'] ?? null, 'label' => (string) ($row['label'] ?? ''), 'at' => $row['planned_at'] ?? null, 'sort' => $i];
        }
        $this->assertStopOrder(array_keys($items), $new, $needs);
        foreach ($this->stops($tour) as $stop) {
            $this->entityManager->remove($stop);
        }
        $this->entityManager->flush();
        foreach ($new as $row) {
            $stop = new DepartmentGrossanlassTourStop();
            $stop->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TOUR_STOP, DepartmentGrossanlassTourStop::class));
            $stop->setTour($tour)->setItem($row['item'])->setKind($row['kind'])->setSortOrder($row['sort']);
            $stop->setPlaceId(is_string($row['place']) ? $row['place'] : null)->setLabel(mb_substr(trim($row['label']), 0, 255));
            $stop->setPlannedAt($this->date($row['at']));
            $this->entityManager->persist($stop);
        }
        $this->markChanged($tour, $user, 'stops_changed', count($new) . ' Stopps');
        $this->entityManager->flush();

        return $tour;
    }

    /** @param array<string, array<string, mixed>> $needs */
    public function confirm(Department $department, User $user, string $tourId, array $needs): DepartmentGrossanlassTour
    {
        $tour = $this->editable($department, $tourId, $needs, allowConfirmed: true);
        if ($tour->isConfirmed()) {
            return $tour;
        }
        $problems = [];
        if ($this->items($tour) === []) {
            $problems[] = 'Die Tour enthält keinen Bedarf';
        }
        if ($tour->getVehicleId() === null) {
            $problems[] = 'Fahrzeug fehlt';
        }
        if ($tour->getChauffeurUserId() === null) {
            $problems[] = 'Chauffeur fehlt';
        } else {
            $this->cards->assertMayDrive($department, $tour->getChauffeurUserId(), $tour->getVehicleId() !== null);
        }
        foreach ($this->conflicts($tour, $needs) as $conflict) {
            $problems[] = sprintf('Konflikt (%s): %s', $conflict['kind'] === 'vehicle' ? 'Fahrzeug' : 'Chauffeur', $conflict['with']);
        }
        if ($problems !== []) {
            throw new \InvalidArgumentException('Tour nicht bestätigbar: ' . implode('; ', $problems));
        }
        $tour->setStatus(DepartmentGrossanlassTour::STATUS_CONFIRMED);
        $tour->setConfirmation($user->getId(), $this->clock->now($department));
        $this->log($tour, $user, 'confirmed', '');
        $this->entityManager->flush();

        return $tour;
    }

    /** @param array<string, array<string, mixed>> $needs */
    public function unconfirm(Department $department, User $user, string $tourId, array $needs): DepartmentGrossanlassTour
    {
        $tour = $this->editable($department, $tourId, $needs, allowConfirmed: true);
        if ($tour->isConfirmed()) {
            $this->markChanged($tour, $user, 'unconfirmed', '');
            $this->entityManager->flush();
        }

        return $tour;
    }

    /** @param array<string, array<string, mixed>> $needs */
    public function delete(Department $department, string $tourId, array $needs): void
    {
        $tour = $this->editable($department, $tourId, $needs, allowConfirmed: true);
        $this->infoCache = [];
        $this->entityManager->remove($tour);
        $this->entityManager->flush();
    }

    // ── Lesen ──────────────────────────────────────────────────

    /** @return list<DepartmentGrossanlassTour> */
    public function tours(Department $department): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassTour::class)
            ->findBy(['departmentId' => $department->getId()], ['createdAt' => 'ASC']);
    }

    /** @return array<string, array{tour: DepartmentGrossanlassTour, item: DepartmentGrossanlassTourItem}> Bedarfs-ID → Tour */
    public function assignments(Department $department): array
    {
        $tours = [];
        foreach ($this->tours($department) as $tour) {
            $tours[$tour->getId()] = $tour;
        }
        if ($tours === []) {
            return [];
        }
        $out = [];
        $items = $this->entityManager->getRepository(DepartmentGrossanlassTourItem::class)->findBy(['tourId' => array_keys($tours)]);
        foreach ($items as $item) {
            if ($item instanceof DepartmentGrossanlassTourItem && isset($tours[$item->getTourId()]) && $item->getOutcome() !== 'not_executed') {
                $out[$item->needKey()] = ['tour' => $tours[$item->getTourId()], 'item' => $item];
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $needs
     *
     * @return array<string, mixed>
     */
    public function serialize(DepartmentGrossanlassTour $tour, array $needs): array
    {
        $items = $this->items($tour);
        $needIds = array_map(static fn (DepartmentGrossanlassTourItem $i) => $i->needKey(), $items);
        $stops = array_map(static fn (DepartmentGrossanlassTourStop $s) => [
            'id' => $s->getId(),
            'kind' => $s->getKind(),
            'place_id' => $s->getPlaceId(),
            'label' => $s->getLabel(),
            'planned_at' => $s->getPlannedAt()?->format('Y-m-d\TH:i:s'),
            'need_id' => null,
            'item_id' => $s->getItemId(),
        ], $this->stops($tour));
        $byItem = [];
        foreach ($items as $item) {
            $byItem[$item->getId()] = $item->needKey();
        }
        foreach ($stops as &$stop) {
            $stop['need_id'] = $stop['item_id'] !== null ? ($byItem[$stop['item_id']] ?? null) : null;
            unset($stop['item_id']);
        }
        unset($stop);
        [$start, $end] = $this->window($tour, $needs);

        return [
            'id' => $tour->getId(),
            'name' => $tour->getName(),
            'status' => $this->effectiveStatus($tour, $needs),
            'confirmed' => $tour->isConfirmed(),
            'vehicle_id' => $tour->getVehicleId(),
            'chauffeur_user_id' => $tour->getChauffeurUserId(),
            'trailer' => $tour->getTrailer(),
            'confirmed_at' => $tour->getConfirmedAt()?->format('Y-m-d\TH:i:s'),
            'need_ids' => $needIds,
            'outcomes' => array_map(static fn (DepartmentGrossanlassTourItem $i) => ['need_id' => $i->needKey(), 'outcome' => $i->getOutcome()], $items),
            'closed_at' => $tour->getClosedAt()?->format('Y-m-d\TH:i:s'),
            'stops' => $stops,
            'starts_at' => $start?->format('Y-m-d\TH:i:s'),
            'ends_at' => $end?->format('Y-m-d\TH:i:s'),
            'conflicts' => $this->conflicts($tour, $needs),
            'events' => $this->events($tour),
        ];
    }

    /**
     * Zeitliche Überschneidung von Fahrzeug oder Chauffeur mit anderen Touren und mit Fahraufträgen ausserhalb dieser Tour.
     *
     * @param array<string, array<string, mixed>> $needs
     *
     * @return list<array{kind: string, with: string, from: string, to: string}>
     */
    public function conflicts(DepartmentGrossanlassTour $tour, array $needs): array
    {
        [$start, $end] = $this->window($tour, $needs);
        if ($tour->isClosed() || $start === null || $end === null || ($tour->getVehicleId() === null && $tour->getChauffeurUserId() === null)) {
            return [];
        }
        $mine = [];
        foreach ($this->items($tour) as $item) {
            $mine[$item->needKey()] = true;
        }
        $out = [];
        $seen = [];
        // Andere Touren (Entwurf und bestätigt)
        foreach ($this->tours($this->entityManager->find(Department::class, $tour->getDepartmentId())) as $other) {
            if ($other->getId() === $tour->getId()) {
                continue;
            }
            [$os, $oe] = $this->window($other, $needs);
            if ($os === null || $oe === null || $os >= $end || $oe <= $start || in_array($this->effectiveStatus($other, $needs), [self::EFFECTIVE_DONE, self::EFFECTIVE_CLOSED], true)) {
                continue;
            }
            foreach ([['vehicle', $tour->getVehicleId(), $other->getVehicleId()], ['chauffeur', $tour->getChauffeurUserId(), $other->getChauffeurUserId()]] as [$kind, $a, $b]) {
                if ($a !== null && $a === $b) {
                    $out[] = ['kind' => $kind, 'with' => $other->getName(), 'from' => $os->format('Y-m-d\TH:i:s'), 'to' => $oe->format('Y-m-d\TH:i:s')];
                    $seen[$kind . $other->getId()] = true;
                }
            }
        }
        // Fahraufträge ausserhalb dieser Tour, die dasselbe Fahrzeug oder denselben Chauffeur binden
        foreach ($needs as $need) {
            if (isset($mine[$need['id']]) || ($need['status'] ?? '') === 'done') {
                continue;
            }
            $ns = $this->date($need['starts_at'] ?? null);
            $ne = $this->date($need['ends_at'] ?? null);
            if ($ns === null || $ne === null || $ns >= $end || $ne <= $start) {
                continue;
            }
            foreach ([['vehicle', $tour->getVehicleId(), $need['vehicle_id'] ?? null], ['chauffeur', $tour->getChauffeurUserId(), $need['chauffeur_user_id'] ?? null]] as [$kind, $a, $b]) {
                if ($a !== null && $a === $b && !isset($seen[$kind . ($need['tour_id'] ?? '')])) {
                    $out[] = ['kind' => $kind, 'with' => (string) $need['title'], 'from' => $ns->format('Y-m-d\TH:i:s'), 'to' => $ne->format('Y-m-d\TH:i:s')];
                }
            }
        }

        return $out;
    }

    /**
     * Gespeichert ist nur Entwurf oder bestätigt; «unterwegs» und «abgeschlossen» ergeben sich aus den Fahraufträgen.
     *
     * @param array<string, array<string, mixed>> $needs
     */
    public function effectiveStatus(DepartmentGrossanlassTour $tour, array $needs): string
    {
        if ($tour->isClosed()) {
            return self::EFFECTIVE_CLOSED;
        }
        if (!$tour->isConfirmed()) {
            return DepartmentGrossanlassTour::STATUS_DRAFT;
        }
        $statuses = [];
        foreach ($this->items($tour) as $item) {
            $statuses[] = $needs[$item->needKey()]['status'] ?? 'planned';
        }
        if ($statuses !== [] && array_unique($statuses) === ['done']) {
            return self::EFFECTIVE_DONE;
        }
        if (array_intersect($statuses, ['underway', 'done']) !== []) {
            return self::EFFECTIVE_UNDERWAY;
        }

        return DepartmentGrossanlassTour::STATUS_CONFIRMED;
    }

    public function find(Department $department, string $tourId): DepartmentGrossanlassTour
    {
        $tour = $this->entityManager->getRepository(DepartmentGrossanlassTour::class)->findOneBy(['id' => $tourId, 'departmentId' => $department->getId()]);
        if (!$tour instanceof DepartmentGrossanlassTour) {
            throw new \InvalidArgumentException('Tour nicht gefunden');
        }

        return $tour;
    }

    /** @return list<DepartmentGrossanlassTourItem> */
    public function tourItems(DepartmentGrossanlassTour $tour): array
    {
        return $this->items($tour);
    }

    /** @return list<DepartmentGrossanlassTourStop> */
    public function tourStops(DepartmentGrossanlassTour $tour): array
    {
        return $this->stops($tour);
    }

    /**
     * Schliesst eine bestätigte Tour ab. Ausgelieferte Fahrten bleiben als `delivered` dokumentiert, nicht ausgeführte werden
     * als `not_executed` aus der Tour gelöst und sind wieder offener Bedarf (Fahrauftrag und Historie bleiben).
     *
     * @param array<string, string> $outcomes Bedarfs-ID → `delivered` | `not_executed`
     */
    public function close(DepartmentGrossanlassTour $tour, User $user, array $outcomes): void
    {
        foreach ($this->items($tour) as $item) {
            $item->setOutcome($outcomes[$item->needKey()] ?? 'not_executed');
        }
        $notExecuted = count(array_filter($outcomes, static fn (string $o) => $o === 'not_executed'));
        $tour->close($user->getId(), $this->clock->now($this->entityManager->find(Department::class, $tour->getDepartmentId())));
        $this->log($tour, $user, 'closed', sprintf('%d ausgeliefert, %d nicht ausgeführt', count($outcomes) - $notExecuted, $notExecuted));
    }

    /**
     * Eine Änderung am Bedarf (z. B. entschiedener Änderungswunsch) macht eine bestätigte Tour wieder zum Entwurf.
     */
    public function noteNeedChanged(Department $department, User $user, string $needKey, string $note): void
    {
        $assignment = $this->assignments($department)[$needKey] ?? null;
        if ($assignment === null || $assignment['tour']->isClosed()) {
            return;
        }
        $this->markChanged($assignment['tour'], $user, 'need_changed', $note);
        $this->entityManager->flush();
    }

    /**
     * Tour-Angabe zu einem Fahrauftrag für die Planungsansicht (nur Anzeige).
     *
     * @return array{id: string, name: string, status: string}|null
     */
    public function tourInfo(string $departmentId, string $einsatzId): ?array
    {
        if (!isset($this->infoCache[$departmentId])) {
            $map = [];
            $department = $this->entityManager->find(Department::class, $departmentId);
            if ($department instanceof Department) {
                foreach ($this->assignments($department) as $key => $row) {
                    $map[$key] = ['id' => $row['tour']->getId(), 'name' => $row['tour']->getName(), 'status' => $row['tour']->getStatus()];
                }
            }
            $this->infoCache[$departmentId] = $map;
        }

        return $this->infoCache[$departmentId][$einsatzId] ?? null;
    }

    // ── intern ─────────────────────────────────────────────────

    /** @param array<string, array<string, mixed>> $needs */
    private function editable(Department $department, string $tourId, array $needs, bool $allowConfirmed = true): DepartmentGrossanlassTour
    {
        $tour = $this->entityManager->getRepository(DepartmentGrossanlassTour::class)->findOneBy(['id' => $tourId, 'departmentId' => $department->getId()]);
        if (!$tour instanceof DepartmentGrossanlassTour) {
            throw new \InvalidArgumentException('Tour nicht gefunden');
        }
        if (in_array($this->effectiveStatus($tour, $needs), [self::EFFECTIVE_UNDERWAY, self::EFFECTIVE_DONE, self::EFFECTIVE_CLOSED], true)) {
            throw new \InvalidArgumentException('Die Tour ist unterwegs oder abgeschlossen und nicht mehr änderbar');
        }

        return $tour;
    }

    /** @param array<string, array<string, mixed>> $needs */
    private function assertAssignable(array $needs, string $id): void
    {
        $need = $needs[$id] ?? null;
        if ($need === null) {
            throw new \InvalidArgumentException('Transportbedarf nicht gefunden');
        }
        if (!empty($need['tour_id'])) {
            throw new \InvalidArgumentException('Der Bedarf «' . $need['title'] . '» gehört bereits zu einer Tour');
        }
        if (!in_array($need['status'], ['open', 'planned'], true)) {
            throw new \InvalidArgumentException('Der Bedarf «' . $need['title'] . '» ist bereits unterwegs oder erledigt');
        }
    }

    /** @param array<string, mixed> $need */
    private function newItem(DepartmentGrossanlassTour $tour, array $need, int $sort): DepartmentGrossanlassTourItem
    {
        $item = new DepartmentGrossanlassTourItem();
        $item->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TOUR_ITEM, DepartmentGrossanlassTourItem::class));
        $item->setTour($tour)->setSortOrder($sort);
        if (!empty($need['einsatz_id'])) {
            $item->setEinsatzId((string) $need['einsatz_id']);
        } else {
            $item->setVehicleNeedId((string) $need['vehicle_need_id']);
        }
        $this->entityManager->persist($item);

        return $item;
    }

    /**
     * @param array<string, mixed> $need
     *
     * @return list<DepartmentGrossanlassTourStop>
     */
    private function defaultStops(DepartmentGrossanlassTour $tour, DepartmentGrossanlassTourItem $item, array $need): array
    {
        $load = new DepartmentGrossanlassTourStop();
        $load->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TOUR_STOP, DepartmentGrossanlassTourStop::class));
        $load->setTour($tour)->setItem($item)
            ->setKind(in_array($need['source'] ?? '', ['beschaffung', 'retour'], true) ? DepartmentGrossanlassTourStop::KIND_PICKUP : DepartmentGrossanlassTourStop::KIND_LOAD)
            ->setPlaceId($need['from_place_id'] ?? null)->setLabel((string) ($need['from'] ?? ''))->setPlannedAt($this->date($need['starts_at'] ?? null));
        $this->entityManager->persist($load);
        $unload = new DepartmentGrossanlassTourStop();
        $unload->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TOUR_STOP, DepartmentGrossanlassTourStop::class));
        $unload->setTour($tour)->setItem($item)->setKind(DepartmentGrossanlassTourStop::KIND_UNLOAD)
            ->setPlaceId($need['to_place_id'] ?? null)->setLabel((string) ($need['to'] ?? ''))->setPlannedAt($this->date($need['ends_at'] ?? null));
        $this->entityManager->persist($unload);
        // IDs werden beim Anlegen sofort vergeben; ein zweiter Aufruf in derselben Unit of Work bleibt eindeutig, weil persist registriert ist.
        $this->entityManager->flush();

        return [$load, $unload];
    }

    /**
     * Jeder Bedarf braucht einen Aufladen-/Abholen-Stopp vor seinem Abladen-Stopp.
     *
     * @param list<string>                        $needKeys
     * @param list<array<string, mixed>>          $rows
     * @param array<string, array<string, mixed>> $needs
     */
    private function assertStopOrder(array $needKeys, array $rows, array $needs): void
    {
        foreach ($needKeys as $key) {
            $load = null;
            $unload = null;
            foreach ($rows as $i => $row) {
                if ($row['key'] !== $key) {
                    continue;
                }
                if ($row['kind'] === DepartmentGrossanlassTourStop::KIND_UNLOAD) {
                    $unload = $i;
                } elseif ($load === null) {
                    $load = $i;
                }
            }
            if ($load === null || $unload === null || $load > $unload) {
                throw new \InvalidArgumentException('Stopps von «' . ($needs[$key]['title'] ?? $key) . '»: Aufladen muss vor dem Abladen liegen');
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function applyResources(Department $department, DepartmentGrossanlassTour $tour, array $data): void
    {
        if (array_key_exists('vehicle_id', $data)) {
            $vehicleId = $data['vehicle_id'] !== null && $data['vehicle_id'] !== '' ? (string) $data['vehicle_id'] : null;
            if ($vehicleId !== null) {
                $exists = $this->entityManager->getConnection()->fetchOne(
                    "SELECT 1 FROM department_grossanlass_commitment WHERE id = ? AND department_id = ? AND family = 'vehicle'",
                    [$vehicleId, $department->getId()],
                );
                if (!$exists) {
                    throw new \InvalidArgumentException('Fahrzeug nicht gefunden');
                }
            }
            $tour->setVehicleId($vehicleId);
        }
        if (array_key_exists('chauffeur_user_id', $data)) {
            $userId = $data['chauffeur_user_id'] !== null && $data['chauffeur_user_id'] !== '' ? (string) $data['chauffeur_user_id'] : null;
            if ($userId !== null) {
                $this->cards->assertMayDrive($department, $userId, false);
            }
            $tour->setChauffeurUserId($userId);
        }
        if (array_key_exists('trailer', $data)) {
            $tour->setTrailer($data['trailer'] !== null ? (string) $data['trailer'] : null);
        }
    }

    private function markChanged(DepartmentGrossanlassTour $tour, User $user, string $action, string $note): void
    {
        if ($tour->isConfirmed()) {
            $tour->setStatus(DepartmentGrossanlassTour::STATUS_DRAFT);
            $tour->setConfirmation(null, null);
            $this->log($tour, $user, 'unconfirmed', 'Änderung nach Bestätigung: erneut bestätigen');
        }
        $this->log($tour, $user, $action, $note);
        $tour->touch();
    }

    private function log(DepartmentGrossanlassTour $tour, User $user, string $action, string $note): void
    {
        $this->infoCache = [];
        $event = new DepartmentGrossanlassTourEvent();
        $event->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TOUR_EVENT, DepartmentGrossanlassTourEvent::class));
        $event->setTour($tour)->setUserId($user->getId())->setAction($action)->setNote(mb_substr($note, 0, 500))->setCreatedAt($this->clock->now($tour->getDepartmentId()));
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    /** @return list<array{action: string, note: string, at: string, user: string}> */
    private function events(DepartmentGrossanlassTour $tour): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassTourEvent::class)->findBy(['tourId' => $tour->getId()], ['createdAt' => 'DESC'], 20);
        $out = [];
        foreach ($rows as $row) {
            $user = $row->getUserId() !== null ? $this->entityManager->getRepository(User::class)->find($row->getUserId()) : null;
            $out[] = ['action' => $row->getAction(), 'note' => $row->getNote(), 'at' => $row->getCreatedAt()->format('Y-m-d\TH:i:s'), 'user' => $user instanceof User ? ($user->getProfile()?->getDisplayName() ?? '') : ''];
        }

        return $out;
    }

    /** @return list<DepartmentGrossanlassTourItem> */
    private function items(DepartmentGrossanlassTour $tour): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassTourItem::class)->findBy(['tourId' => $tour->getId()], ['sortOrder' => 'ASC']);
    }

    /** @return list<DepartmentGrossanlassTourStop> */
    private function stops(DepartmentGrossanlassTour $tour): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassTourStop::class)->findBy(['tourId' => $tour->getId()], ['sortOrder' => 'ASC']);
    }

    private function renumber(DepartmentGrossanlassTour $tour): void
    {
        foreach ($this->stops($tour) as $i => $stop) {
            $stop->setSortOrder($i);
        }
        foreach ($this->items($tour) as $i => $item) {
            $item->setSortOrder($i);
        }
        $this->entityManager->flush();
    }

    /**
     * @param array<string, array<string, mixed>> $needs
     *
     * @return array{0: ?\DateTime, 1: ?\DateTime}
     */
    private function window(DepartmentGrossanlassTour $tour, array $needs): array
    {
        $times = [];
        foreach ($this->items($tour) as $item) {
            foreach (['starts_at', 'ends_at'] as $field) {
                $d = $this->date($needs[$item->needKey()][$field] ?? null);
                if ($d !== null) {
                    $times[] = $d;
                }
            }
        }
        foreach ($this->stops($tour) as $stop) {
            if ($stop->getPlannedAt() !== null) {
                $times[] = $stop->getPlannedAt();
            }
        }
        if ($times === []) {
            return [null, null];
        }

        return [min($times), max($times)];
    }

    private function nextName(Department $department): string
    {
        return 'Tour ' . (count($this->tours($department)) + 1);
    }

    private function name(string $name): string
    {
        return mb_substr(trim($name), 0, 120);
    }

    private function date(mixed $value): ?\DateTime
    {
        if ($value instanceof \DateTime) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            // Wandzeit wie in Einsätzen: Offset wird ignoriert
            return new \DateTime(substr(str_replace('T', ' ', trim($value)), 0, 19));
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültige Zeitangabe');
        }
    }
}
