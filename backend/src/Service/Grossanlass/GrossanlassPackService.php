<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Service\Clock\BusinessClock;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\DepartmentGrossanlassPackLine;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class GrossanlassPackService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassUserCardService $cards,
        private BusinessClock $clock,
        private GrossanlassAvailabilityService $availability,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $appFrontendUrl,
        #[Autowire('%env(APP_PUBLIC_QR_URL)%')] private string $appPublicQrUrl,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEinsatz(Department $department, User $user, string $einsatzId): array
    {
        $this->assertPackAccess($department, $user);
        $einsatz = $this->findEinsatz($department, $einsatzId);
        $this->ensureDefaultPack($einsatz);
        $this->entityManager->flush();

        return $this->serializePacks($einsatz);
    }

    /**
     * @return array<string, mixed>
     */
    public function addPack(Department $department, User $user, string $einsatzId): array
    {
        $this->assertAusgabe($department, $user);
        $einsatz = $this->findEinsatz($department, $einsatzId);
        $this->ensureDefaultPack($einsatz);
        $existing = $this->packsOf($einsatz);
        $remaining = $this->remainingByKey($einsatz, $existing[0] ?? null);
        if (array_sum($remaining) < 1) {
            throw new \InvalidArgumentException(
                'Einsatzmenge ist schon ganz auf Packs verteilt — zuerst die Menge eines Packs verringern',
            );
        }
        $pack = $this->makePack($einsatz, count($existing));
        $this->copyLinesFrom($existing[0] ?? null, $pack, $einsatz, $remaining);
        $this->entityManager->flush();

        return $this->serializePack($pack);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateLine(Department $department, User $user, string $lineId, array $data): array
    {
        $this->assertAusgabe($department, $user);
        $line = $this->entityManager->getRepository(DepartmentGrossanlassPackLine::class)->find($lineId);
        if (!$line instanceof DepartmentGrossanlassPackLine || $line->getPack()->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Packzeile nicht gefunden');
        }
        $einsatz = $line->getPack()->getEinsatz();
        if (array_key_exists('qty_needed', $data)) {
            $needed = (int) $data['qty_needed'];
            // Verringern geht immer (auch bei Altdaten über der Einsatzmenge), Erhöhen nur bis zur Restmenge.
            if ($needed > $line->getQtyNeeded() || $needed < 1) {
                self::assertNeededFits($einsatz->getQty(), $this->neededOnOtherLines($einsatz, $line), $needed);
            }
            $line->setQtyNeeded($needed);
        }
        if (array_key_exists('qty_packed', $data)) {
            $line->setQtyPacked((int) $data['qty_packed']);
        }
        self::assertPackedFits($line->getQtyNeeded(), $line->getQtyPacked());
        if (array_key_exists('valid_from', $data)) {
            $raw = trim((string) ($data['valid_from'] ?? ''));
            $line->setValidFrom($raw !== '' ? new \DateTime($raw) : null);
        }
        if (array_key_exists('valid_to', $data)) {
            $raw = trim((string) ($data['valid_to'] ?? ''));
            $line->setValidTo($raw !== '' ? new \DateTime($raw) : null);
        }
        $this->syncEinsatzPacked($line->getPack()->getEinsatz());
        $this->entityManager->flush();

        return $this->serializePack($line->getPack());
    }

    /**
     * @return array<string, mixed>
     */
    public function releaseTrip(Department $department, User $user, string $packId): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canReleaseTrip($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Fahrt-Frei');
        }
        $pack = $this->findPack($department, $packId);
        $einsatz = $pack->getEinsatz();
        if (!$einsatz->isTrip()) {
            throw new \InvalidArgumentException('Fahrt-Frei nur bei Checkbox Fahrt');
        }
        if ($einsatz->getStatus() === DepartmentGrossanlassEinsatz::STATUS_PENDING) {
            throw new \InvalidArgumentException('Einsatz ist noch nicht frei');
        }
        if (!$this->hasAnyPacked($pack)) {
            throw new \InvalidArgumentException('Fahrt-Frei erst nach Pack (Teilpack reicht)');
        }
        $pack->setTripReleasedAt($pack->getTripReleasedAt() ?? $this->clock->now($department));
        $pack->setStatus(DepartmentGrossanlassPack::STATUS_TRIP_RELEASED);
        if (!$einsatz->isTripReleased()) {
            $einsatz->setTripReleasedAt($pack->getTripReleasedAt());
        }
        $this->entityManager->flush();

        return $this->serializePack($pack);
    }

    /**
     * Pack-QR: Fahrt starten / unterwegs.
     *
     * @return array<string, mixed>
     */
    public function scanStart(Department $department, User $user, string $packId): array
    {
        $this->assertScanActor($department, $user);
        $pack = $this->findPack($department, $packId);
        $einsatz = $pack->getEinsatz();
        $this->assertTripStartable($einsatz, $pack);
        $vehicle = $einsatz->getCommitment()?->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE;
        $this->cards->assertMayDrive($department, $einsatz->getChauffeurUserId(), $vehicle);
        $this->availability->transactional(function () use ($pack, $einsatz): void {
            if ($einsatz->getStatus() !== DepartmentGrossanlassEinsatz::STATUS_ISSUED) {
                // Der Pack-Scan gibt das Material aus: gleiche Bestandsregel wie die Ausgabe in der Übersicht.
                $commitment = $einsatz->getCommitment();
                if ($commitment instanceof DepartmentGrossanlassCommitment) {
                    $this->availability->lock($commitment);
                    $this->availability->assertIssuable($einsatz);
                }
                $einsatz->setStatus(DepartmentGrossanlassEinsatz::STATUS_ISSUED);
                $einsatz->setPlace(DepartmentGrossanlassEinsatz::PLACE_OUT);
            }
            $pack->setStatus(DepartmentGrossanlassPack::STATUS_IN_TRANSIT);
            $this->entityManager->flush();
        });

        return $this->serializePack($pack);
    }

    /**
     * Ziel-QR: Standort dieses Packs.
     *
     * @return array<string, mixed>
     */
    public function scanArrive(Department $department, User $user, string $packId, string $placeId): array
    {
        $this->assertScanActor($department, $user);
        $pack = $this->findPack($department, $packId);
        $einsatz = $pack->getEinsatz();
        $place = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->find($placeId);
        if (!$place instanceof DepartmentGrossanlassPlace || $place->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Ort nicht gefunden');
        }
        $wanted = $einsatz->getDestinationPlaceId();
        if ($wanted !== null && $wanted !== $place->getId()) {
            throw new \InvalidArgumentException('Falscher Ziel-QR — nicht verbucht');
        }
        if ($pack->getStatus() !== DepartmentGrossanlassPack::STATUS_IN_TRANSIT
            && $einsatz->getStatus() !== DepartmentGrossanlassEinsatz::STATUS_ISSUED
        ) {
            throw new \InvalidArgumentException('Pack zuerst scannen (unterwegs)');
        }
        $pack->setCurrentPlaceId($place->getId());
        $pack->setStatus(DepartmentGrossanlassPack::STATUS_AT_PLACE);
        $this->entityManager->flush();

        return $this->serializePack($pack);
    }

    public function findByCode(string $code): ?DepartmentGrossanlassPack
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassPack::class)
            ->findOneBy(['publicCode' => $code]);

        return $row instanceof DepartmentGrossanlassPack ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvePublic(string $code): ?array
    {
        $row = $this->findByCode($code);
        if (!$row instanceof DepartmentGrossanlassPack) {
            return null;
        }

        return $this->serializePack($row) + [
            'entity_type' => 'ga_pack',
            'department' => [
                'id' => $row->getDepartmentId(),
                'name' => $row->getEinsatz()->getDepartment()->getName(),
            ],
        ];
    }

    public function ensureDefaultPack(DepartmentGrossanlassEinsatz $einsatz): DepartmentGrossanlassPack
    {
        $existing = $this->packsOf($einsatz);
        if ($existing !== []) {
            return $existing[0];
        }
        $pack = $this->makePack($einsatz, 0);
        $this->copyLinesFrom(null, $pack, $einsatz);
        if ($einsatz->isPacked()) {
            foreach ($this->linesOf($pack) as $line) {
                $line->setQtyPacked($line->getQtyNeeded());
            }
        }
        if ($einsatz->isTripReleased()) {
            $pack->setTripReleasedAt($einsatz->getTripReleasedAt());
            $pack->setStatus(DepartmentGrossanlassPack::STATUS_TRIP_RELEASED);
        }

        return $pack;
    }

    /**
     * Pack-Mengen an eine geänderte Einsatzmenge anpassen. Ein einzelner Pack folgt der Einsatzmenge;
     * bei mehreren Packs darf die Summe die neue Menge nicht übersteigen.
     */
    public function fitPacksToEinsatzQty(DepartmentGrossanlassEinsatz $einsatz, int $qty): void
    {
        $byKey = [];
        foreach ($this->packsOf($einsatz) as $pack) {
            foreach ($this->linesOf($pack) as $line) {
                $byKey[self::lineKey($line)][] = $line;
            }
        }
        foreach ($byKey as $lines) {
            if (count($lines) === 1) {
                $line = $lines[0];
                self::assertPackedFits(max(1, $qty), $line->getQtyPacked());
                $line->setQtyNeeded(max(1, $qty));
                continue;
            }
            $total = array_sum(array_map(static fn (DepartmentGrossanlassPackLine $l) => $l->getQtyNeeded(), $lines));
            if ($total > $qty) {
                throw new \InvalidArgumentException(sprintf(
                    'Packs brauchen zusammen %d, Einsatz hätte nur noch %d — zuerst die Pack-Mengen anpassen',
                    $total,
                    $qty,
                ));
            }
        }
    }

    /**
     * Summe der Pack-Mengen eines Einsatzes darf die Einsatzmenge nicht übersteigen (Teilpack bleibt erlaubt).
     *
     * @throws \InvalidArgumentException
     */
    public static function assertNeededFits(int $einsatzQty, int $neededOnOtherPacks, int $needed): void
    {
        if ($needed < 1) {
            throw new \InvalidArgumentException('Pack-Menge muss mindestens 1 sein');
        }
        $max = max(0, $einsatzQty - $neededOnOtherPacks);
        if ($needed > $max) {
            throw new \InvalidArgumentException(sprintf(
                'Pack-Menge %d zu gross: Einsatz %d, andere Packs %d, hier höchstens %d',
                $needed,
                $einsatzQty,
                $neededOnOtherPacks,
                $max,
            ));
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertPackedFits(int $needed, int $packed): void
    {
        if ($packed < 0) {
            throw new \InvalidArgumentException('Gepackte Menge darf nicht negativ sein');
        }
        if ($packed > $needed) {
            throw new \InvalidArgumentException(sprintf('Gepackt %d ist mehr als die Pack-Menge %d', $packed, $needed));
        }
    }

    public function applyBooleanPacked(DepartmentGrossanlassEinsatz $einsatz, bool $packed): void
    {
        $pack = $this->ensureDefaultPack($einsatz);
        foreach ($this->linesOf($pack) as $line) {
            $line->setQtyPacked($packed ? $line->getQtyNeeded() : 0);
        }
        $einsatz->setPacked($packed);
    }

    public function syncEinsatzPacked(DepartmentGrossanlassEinsatz $einsatz): void
    {
        $packed = false;
        foreach ($this->packsOf($einsatz) as $pack) {
            if ($this->hasAnyPacked($pack)) {
                $packed = true;
                break;
            }
        }
        $einsatz->setPacked($packed);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAtPlace(Department $department, string $placeId): array
    {
        $packs = $this->entityManager->getRepository(DepartmentGrossanlassPack::class)
            ->findBy(['currentPlaceId' => $placeId], ['sortOrder' => 'ASC']);
        $out = [];
        foreach ($packs as $pack) {
            if (!$pack instanceof DepartmentGrossanlassPack || $pack->getDepartmentId() !== $department->getId()) {
                continue;
            }
            $out[] = $this->serializePack($pack);
        }

        return $out;
    }

    public function qrUrl(string $code): string
    {
        $base = trim($this->appPublicQrUrl) !== ''
            ? rtrim($this->appPublicQrUrl, '/')
            : rtrim($this->appFrontendUrl, '/');

        return $base . '/i/k/' . rawurlencode($code);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializePacks(DepartmentGrossanlassEinsatz $einsatz): array
    {
        return array_map(fn (DepartmentGrossanlassPack $pack) => $this->serializePack($pack), $this->packsOf($einsatz));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePack(DepartmentGrossanlassPack $pack): array
    {
        $lines = array_map(fn (DepartmentGrossanlassPackLine $line) => $this->serializeLine($line), $this->linesOf($pack));
        $warning = $this->warningFrom($lines);
        $placeName = null;
        if ($pack->getCurrentPlaceId()) {
            $place = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->find($pack->getCurrentPlaceId());
            $placeName = $place instanceof DepartmentGrossanlassPlace ? $place->getName() : null;
        }

        return [
            'id' => $pack->getId(),
            'einsatz_id' => $pack->getEinsatzId(),
            'public_code' => $pack->getPublicCode(),
            'qr_url' => $this->qrUrl($pack->getPublicCode()),
            'status' => $pack->getStatus(),
            'trip_released' => $pack->isTripReleased(),
            'trip_released_at' => $pack->getTripReleasedAt()?->format(\DateTimeInterface::ATOM),
            'current_place_id' => $pack->getCurrentPlaceId(),
            'current_place_name' => $placeName,
            'sort_order' => $pack->getSortOrder(),
            'incomplete' => $warning !== null,
            'warning' => $warning,
            'lines' => $lines,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    public static function warningFrom(array $lines): ?string
    {
        $packed = [];
        $missing = [];
        foreach ($lines as $line) {
            $label = (string) ($line['label'] ?? '');
            $needed = (int) ($line['qty_needed'] ?? 0);
            $got = (int) ($line['qty_packed'] ?? 0);
            if ($got > 0) {
                $packed[] = $label . ' ' . $got . '×';
            }
            if ($got < $needed) {
                $missing[] = $label;
            }
        }
        if ($packed === [] || $missing === []) {
            return null;
        }

        return implode(', ', $packed) . ' gepackt, ' . implode(', ', $missing) . ' noch nicht im Mat';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLine(DepartmentGrossanlassPackLine $line): array
    {
        return [
            'id' => $line->getId(),
            'label' => $line->getLabel(),
            'commitment_id' => $line->getCommitmentId(),
            'wish_line_id' => $line->getWishLineId(),
            'qty_needed' => $line->getQtyNeeded(),
            'qty_packed' => $line->getQtyPacked(),
            'valid_from' => $line->getValidFrom()?->format(\DateTimeInterface::ATOM),
            'valid_to' => $line->getValidTo()?->format(\DateTimeInterface::ATOM),
            'incomplete' => $line->isIncomplete(),
        ];
    }

    private function makePack(DepartmentGrossanlassEinsatz $einsatz, int $sort): DepartmentGrossanlassPack
    {
        $pack = new DepartmentGrossanlassPack();
        $pack->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PACK,
            DepartmentGrossanlassPack::class,
        ));
        $pack->setDepartment($einsatz->getDepartment());
        $pack->setEinsatz($einsatz);
        $pack->setSortOrder($sort);
        $pack->setPublicCode(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PACK,
            DepartmentGrossanlassPack::class,
            'publicCode',
        ));
        $this->entityManager->persist($pack);

        return $pack;
    }

    /**
     * @param array<string, int> $remaining Zeilen-Schlüssel → noch nicht auf Packs verteilte Menge
     */
    private function copyLinesFrom(
        ?DepartmentGrossanlassPack $from,
        DepartmentGrossanlassPack $to,
        DepartmentGrossanlassEinsatz $einsatz,
        array $remaining = [],
    ): void {
        $sources = $from instanceof DepartmentGrossanlassPack ? $this->linesOf($from) : [];
        if ($sources === []) {
            $line = new DepartmentGrossanlassPackLine();
            $line->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::PACK_LINE,
                DepartmentGrossanlassPackLine::class,
            ));
            $line->setPack($to);
            $line->setCommitmentId($einsatz->getCommitmentId());
            $line->setWishLineId($einsatz->getWishLineId());
            $line->setLabel($einsatz->getCommitment()?->getName() ?? $einsatz->getWho() ?: 'Pack');
            $line->setQtyNeeded(max(1, $einsatz->getQty()));
            $fromWish = $einsatz->getWishLineId()
                ? $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->find($einsatz->getWishLineId())
                : null;
            if ($fromWish instanceof ActivityGrossanlassWishLine) {
                $line->setValidFrom($fromWish->getValidFrom());
                $line->setValidTo($fromWish->getValidTo());
            } else {
                $line->setValidFrom($einsatz->getStartsAt());
                $line->setValidTo($einsatz->getEndsAt());
            }
            $this->entityManager->persist($line);

            return;
        }
        foreach ($sources as $source) {
            $rest = $remaining[self::lineKey($source)] ?? 0;
            if ($rest < 1) {
                continue;
            }
            $line = new DepartmentGrossanlassPackLine();
            $line->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::PACK_LINE,
                DepartmentGrossanlassPackLine::class,
            ));
            $line->setPack($to);
            $line->setCommitmentId($source->getCommitmentId());
            $line->setWishLineId($source->getWishLineId());
            $line->setLabel($source->getLabel());
            $line->setQtyNeeded($rest);
            $line->setValidFrom($source->getValidFrom());
            $line->setValidTo($source->getValidTo());
            $this->entityManager->persist($line);
        }
    }

    /**
     * Restmenge je Zeilen-Schlüssel des ersten Packs: Einsatzmenge minus alle Pack-Mengen.
     *
     * @return array<string, int>
     */
    private function remainingByKey(DepartmentGrossanlassEinsatz $einsatz, ?DepartmentGrossanlassPack $template): array
    {
        if (!$template instanceof DepartmentGrossanlassPack) {
            return [];
        }
        $used = [];
        foreach ($this->packsOf($einsatz) as $pack) {
            foreach ($this->linesOf($pack) as $line) {
                $key = self::lineKey($line);
                $used[$key] = ($used[$key] ?? 0) + $line->getQtyNeeded();
            }
        }
        $out = [];
        foreach ($this->linesOf($template) as $line) {
            $key = self::lineKey($line);
            $out[$key] = max(0, $einsatz->getQty() - ($used[$key] ?? 0));
        }

        return $out;
    }

    private function neededOnOtherLines(DepartmentGrossanlassEinsatz $einsatz, DepartmentGrossanlassPackLine $line): int
    {
        $key = self::lineKey($line);
        $sum = 0;
        foreach ($this->packsOf($einsatz) as $pack) {
            foreach ($this->linesOf($pack) as $other) {
                if ($other->getId() !== $line->getId() && self::lineKey($other) === $key) {
                    $sum += $other->getQtyNeeded();
                }
            }
        }

        return $sum;
    }

    /** Gleiche Ware über mehrere Packs eines Einsatzes. */
    private static function lineKey(DepartmentGrossanlassPackLine $line): string
    {
        return ($line->getCommitmentId() ?? '') . '|' . ($line->getWishLineId() ?? '') . '|' . $line->getLabel();
    }

    /** @return list<DepartmentGrossanlassPack> */
    private function packsOf(DepartmentGrossanlassEinsatz $einsatz): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassPack::class)
            ->findBy(['einsatzId' => $einsatz->getId()], ['sortOrder' => 'ASC']);
    }

    /** @return list<DepartmentGrossanlassPackLine> */
    private function linesOf(DepartmentGrossanlassPack $pack): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassPackLine::class)
            ->findBy(['packId' => $pack->getId()]);
    }

    private function hasAnyPacked(DepartmentGrossanlassPack $pack): bool
    {
        foreach ($this->linesOf($pack) as $line) {
            if ($line->getQtyPacked() > 0) {
                return true;
            }
        }

        return false;
    }

    private function assertTripStartable(DepartmentGrossanlassEinsatz $einsatz, DepartmentGrossanlassPack $pack): void
    {
        if (!$einsatz->isTrip()) {
            throw new \InvalidArgumentException('Kein Fahrauftrag');
        }
        if ($einsatz->getStatus() === DepartmentGrossanlassEinsatz::STATUS_PENDING) {
            throw new \InvalidArgumentException('Einsatz ist noch nicht frei');
        }
        if (!$pack->isTripReleased()) {
            throw new \InvalidArgumentException('Fahrt ist noch nicht frei');
        }
        if ($einsatz->getDestinationPlaceId() === null) {
            throw new \InvalidArgumentException('Ziel-Ort fehlt');
        }
        if ($einsatz->getChauffeurUserId() === null) {
            throw new \InvalidArgumentException('Fahrauftrag braucht einen Chauffeur');
        }
    }

    private function findEinsatz(Department $department, string $id): DepartmentGrossanlassEinsatz
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassEinsatz || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Einsatz nicht gefunden');
        }

        return $row;
    }

    private function findPack(Department $department, string $id): DepartmentGrossanlassPack
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassPack::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassPack || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Pack nicht gefunden');
        }

        return $row;
    }

    private function assertAusgabe(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canOperateAusgabe($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Pack');
        }
    }

    private function assertPackAccess(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canOperateAusgabe($user, $department)
            && !$this->access->canSeeAnlassOverview($user, $department)
            && !$this->access->canSubmitEinsatz($user, $department)
        ) {
            throw new \RuntimeException('Keine Berechtigung für Pack');
        }
    }

    private function assertScanActor(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if ($this->access->membershipRole($user, $department) === null) {
            throw new \RuntimeException('Keine Berechtigung zum Scannen');
        }
    }
}
