<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassBudgetArea;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassUserCard;
use App\Entity\DepartmentVehicle;
use App\Entity\Profile;
use App\Entity\PublicCode;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Entity\User;
use App\Service\Demo\DemoStableCodes;
use App\Service\Demo\Organisation\ManagedSeedApplier;
use App\Service\Demo\Organisation\ManagedSpec;
use App\Service\Demo\Organisation\SyncReport;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassCostService;
use App\Service\Grossanlass\GrossanlassDriveCategories;
use App\Service\Public\PublicCodeService;
use App\Util\DemoAccounts;
use App\Util\GrossanlassIdGenerator;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logistik-Stammdaten des Szenarios `grossanlass-event` (Katalog `grossanlass.logistics`): Budget-Limits für Material und
 * Logistik, Regale und Fächer im Lagerstandort samt QR, Fahrzeuge, Fahrerkarten und Chargen mit Eigentümer. Alles läuft über den
 * Seed-Ledger (Dreiwege-Vergleich: von Hand Geändertes bleibt, Gelöschtes wird mit demselben QR-Code wieder angelegt).
 *
 * Es entstehen nur Stammdaten und der Ausgangsbestand. Prozessschritte (Wareneingang, Packen, Transport) löst weiterhin nur eine
 * ausdrückliche Aktion aus; der Seed bucht keine Bewegungen.
 */
final class DemoGrossanlassEventLogisticsSeedService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagedSeedApplier $applier,
        private GrossanlassCommitmentService $commitments,
        private GrossanlassCostService $costs,
        private PublicCodeService $publicCodes,
    ) {
    }

    /**
     * @param array<string, mixed> $def Katalog `grossanlass.logistics`
     *
     * @return list<string>
     */
    public function expectedSeedKeys(string $scenarioKey, array $def): array
    {
        $keys = [];
        foreach (DepartmentGrossanlassBudgetArea::AREAS as $area) {
            if (isset($def['budget'][$area])) {
                $keys[] = $scenarioKey . ':budget:' . $area;
            }
        }
        foreach ($def['racks'] ?? [] as $rack) {
            $keys[] = $scenarioKey . ':lager:regal:' . $rack['key'];
            foreach ($rack['slots'] as $i => $slot) {
                $keys[] = $scenarioKey . ':lager:fach:' . $rack['key'] . ':' . ($i + 1);
            }
        }
        foreach ($def['vehicles'] ?? [] as $vehicle) {
            $keys[] = $scenarioKey . ':fahrzeug:' . $vehicle['key'];
        }
        foreach ($def['cards'] ?? [] as $card) {
            $keys[] = $scenarioKey . ':fahrerkarte:' . $card['account'];
        }
        foreach ($def['charges'] ?? [] as $charge) {
            $keys[] = $scenarioKey . ':charge:' . $charge['key'];
        }
        if (($def['racks'] ?? []) !== []) {
            $keys[] = $scenarioKey . ':lager:qr';
        }

        return $keys;
    }

    /** @param array<string, mixed> $def */
    public function verifyScenario(SeedContext $context, array $def): array
    {
        $violations = [];
        $records = [];
        foreach ($context->records() as $record) {
            $records[$record->getSeedKey()] = $record;
        }
        foreach ($this->expectedSeedKeys($context->scenarioKey(), $def) as $seedKey) {
            $record = $records[$seedKey] ?? null;
            if ($record === null) {
                $violations[] = sprintf('Seed-Eintrag «%s» fehlt (Sync ergänzt ihn).', $seedKey);
            } elseif ($record->getEntityClass() !== PublicCode::class && !$this->exists($record->getEntityClass(), $record->getEntityId(), $context)) {
                $violations[] = sprintf('Datensatz zu «%s» existiert nicht mehr (Sync legt ihn neu an).', $seedKey);
            }
        }

        return $violations;
    }

    /** @param array<string, mixed> $def */
    public function syncScenario(SeedContext $context, SyncReport $report, array $def, string $version): void
    {
        if ($def === []) {
            return;
        }
        $department = $context->requireDepartment();
        $key = $context->scenarioKey();
        $actor = $this->userFor('ga-mw');

        $this->syncBudget($context, $report, $department, $actor, $key, $def['budget'] ?? [], $version);
        $this->syncStorage($context, $report, $department, $actor, $key, $def['racks'] ?? [], $version);
        foreach ($def['vehicles'] ?? [] as $vehicle) {
            $this->syncVehicle($context, $report, $department, $key . ':fahrzeug:' . $vehicle['key'], $vehicle, $version);
        }
        foreach ($def['cards'] ?? [] as $card) {
            $this->syncCard($context, $report, $department, $key, $card, $version);
        }
        if ($actor instanceof User) {
            foreach ($def['charges'] ?? [] as $charge) {
                $this->syncCharge($context, $report, $department, $actor, $key . ':charge:' . $charge['key'], $charge, $version);
            }
        } else {
            $report->warnings[] = 'Chargen übersprungen: Demo-Konto ga-mw fehlt.';
        }
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $budget */
    private function syncBudget(SeedContext $context, SyncReport $report, Department $department, ?User $actor, string $key, array $budget, string $version): void
    {
        $createdBefore = $report->created + $report->recreated;
        foreach (DepartmentGrossanlassBudgetArea::AREAS as $area) {
            if (!isset($budget[$area])) {
                continue;
            }
            $this->applier->ensure($context, $report, new ManagedSpec(
                seedKey: $key . ':budget:' . $area,
                create: fn (): object => new DepartmentGrossanlassBudgetArea($department->getId(), $area),
                find: fn (string $id): ?object => $this->entityManager->find(DepartmentGrossanlassBudgetArea::class, ['departmentId' => $department->getId(), 'area' => $area]),
                idOf: static fn (object $b): string => $b->getDepartmentId() . ':' . $b->getArea(),
                read: static fn (object $b): array => ['limit_chf' => $b->getLimitChf()],
                write: static function (object $b, array $d): void {
                    $b->setLimitChf($d['limit_chf']);
                },
                desired: ['limit_chf' => number_format((float) $budget[$area], 2, '.', '')],
                resettable: true,
            ), $version);
        }
        // Der Anlass-Rahmen der Kostenübersicht folgt der Summe der Limits, solange der Seed die Limits neu anlegt.
        if ($actor instanceof User && $report->created + $report->recreated > $createdBefore) {
            $sum = array_sum(array_map(static fn ($v): float => (float) $v, $budget));
            $this->entityManager->flush();
            $this->costs->upsertBudget($department, $actor, ['payer_group_id' => null, 'rahmen_chf' => $sum > 0 ? number_format($sum, 2, '.', '') : null]);
        }
    }

    /** @param list<array<string, mixed>> $racks */
    private function syncStorage(SeedContext $context, SyncReport $report, Department $department, ?User $actor, string $key, array $racks, string $version): void
    {
        if ($racks === []) {
            return;
        }
        $addressRecord = $context->findRecord($key . ':storage-address');
        $address = $addressRecord !== null ? $this->entityManager->find(Address::class, $addressRecord->getEntityId()) : null;
        if (!$address instanceof Address) {
            $report->warnings[] = 'Lagerregale übersprungen: Lagerstandort fehlt (Organisationsstruktur zuerst synchronisieren).';

            return;
        }
        $this->stableAddressCode($context, $report, $key . ':lager:qr', $address, $actor, $version);
        foreach ($racks as $rackDef) {
            $rackKey = $key . ':lager:regal:' . $rackDef['key'];
            $rack = $this->applier->ensure($context, $report, new ManagedSpec(
                seedKey: $rackKey,
                create: function () use ($department, $address): object {
                    $r = new StorageRack();
                    $r->setId(IdGenerator::generate());
                    $r->setDepartment($department);
                    $r->setStorageAddress($address);

                    return $r;
                },
                find: fn (string $id): ?object => $this->entityManager->find(StorageRack::class, $id),
                idOf: static fn (object $r): string => (string) $r->getId(),
                read: static fn (object $r): array => ['name' => $r->getName(), 'sort' => $r->getSortOrder(), 'active' => $r->getIsActive()],
                write: static function (object $r, array $d): void {
                    $r->setName($d['name'])->setSortOrder($d['sort'])->setIsActive($d['active']);
                },
                desired: ['name' => (string) $rackDef['name'], 'sort' => (int) ($rackDef['sort'] ?? 0), 'active' => true],
                resettable: true,
            ), $version);
            if (!$rack instanceof StorageRack) {
                continue;
            }
            $this->stableCode(PublicCodeService::ENTITY_STORAGE_RACK, (string) $rack->getId(), $department, $actor, DemoStableCodes::internal($key, $rackKey));
            foreach ($rackDef['slots'] as $i => $slotName) {
                $slotKey = $key . ':lager:fach:' . $rackDef['key'] . ':' . ($i + 1);
                $slot = $this->applier->ensure($context, $report, new ManagedSpec(
                    seedKey: $slotKey,
                    create: function () use ($rack): object {
                        $s = new StorageSlot();
                        $s->setId(IdGenerator::generate());
                        $s->setRack($rack);

                        return $s;
                    },
                    find: fn (string $id): ?object => $this->entityManager->find(StorageSlot::class, $id),
                    idOf: static fn (object $s): string => (string) $s->getId(),
                    read: static fn (object $s): array => ['name' => $s->getName(), 'sort' => $s->getSortOrder(), 'active' => $s->getIsActive()],
                    write: static function (object $s, array $d): void {
                        $s->setName($d['name'])->setSortOrder($d['sort'])->setIsActive($d['active']);
                    },
                    desired: ['name' => (string) $slotName, 'sort' => $i, 'active' => true],
                    resettable: true,
                ), $version);
                if ($slot instanceof StorageSlot) {
                    $this->stableCode(PublicCodeService::ENTITY_STORAGE_SLOT, (string) $slot->getId(), $department, $actor, DemoStableCodes::internal($key, $slotKey));
                }
            }
        }
    }

    /** QR des Lagerstandorts: stabiler Code, im Ledger als `:lager:qr` festgehalten. */
    private function stableAddressCode(SeedContext $context, SyncReport $report, string $seedKey, Address $address, ?User $actor, string $version): void
    {
        $department = $context->requireDepartment();
        $entry = $this->stableCode(PublicCodeService::ENTITY_STORAGE_ADDRESS, (string) $address->getId(), $department, $actor, DemoStableCodes::internal($context->scenarioKey(), $seedKey));
        if ($entry !== null && $context->findRecord($seedKey) === null) {
            $context->record($seedKey, $entry, (string) $entry->getId(), ManagedSeedApplier::hash(['seeded' => true]), $version);
            ++$report->created;
        }
    }

    /**
     * Interner Lager-QR mit reproduzierbarem Code. Ein bereits vergebener Code bleibt unverändert (auch ein von Hand erzeugter);
     * nur ein neu entstandener Eintrag erhält den stabilen Wert.
     */
    private function stableCode(string $entityType, string $entityId, Department $department, ?User $actor, string $code): ?PublicCode
    {
        $existing = $this->entityManager->getRepository(PublicCode::class)->findOneBy(['entityType' => $entityType, 'entityId' => $entityId, 'isActive' => true]);
        if ($existing instanceof PublicCode) {
            return $existing;
        }
        $taken = $this->entityManager->getRepository(PublicCode::class)->findOneBy(['publicCode' => $code]);
        if ($taken instanceof PublicCode) {
            return null;
        }
        $entry = new PublicCode();
        $entry->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, PublicCode::class, 'pc'));
        $entry->setEntityType($entityType);
        $entry->setEntityId($entityId);
        $entry->setDepartmentId((string) $department->getId());
        $entry->setIsPublic(false);
        $entry->setIsActive(true);
        $entry->setVersion(1);
        $entry->setPublicCode($code);
        if ($actor instanceof User) {
            $entry->setCreatedByUserId((string) $actor->getId());
        }
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    /** @param array<string, mixed> $def */
    private function syncVehicle(SeedContext $context, SyncReport $report, Department $department, string $seedKey, array $def, string $version): void
    {
        $desired = [
            'name' => (string) $def['name'],
            'plate' => $def['plate'] ?? null,
            'length_m' => $this->dec($def['length_m'] ?? null),
            'width_m' => $this->dec($def['width_m'] ?? null),
            'height_m' => $this->dec($def['height_m'] ?? null),
            'payload_kg' => $this->dec($def['payload_kg'] ?? null),
            'volume_m3' => $this->dec($def['volume_m3'] ?? null),
            'notes' => (string) ($def['notes'] ?? ''),
        ];
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: function () use ($department): object {
                $v = new DepartmentVehicle();
                $v->setId(IdGenerator::generate());
                $v->setDepartment($department);
                $v->setIsActive(true);

                return $v;
            },
            find: fn (string $id): ?object => $this->entityManager->find(DepartmentVehicle::class, $id),
            idOf: static fn (object $v): string => (string) $v->getId(),
            read: static fn (object $v): array => [
                'name' => $v->getName(),
                'plate' => $v->getPlate(),
                'length_m' => $v->getLengthM(),
                'width_m' => $v->getWidthM(),
                'height_m' => $v->getHeightM(),
                'payload_kg' => $v->getMaxPayloadKg(),
                'volume_m3' => $v->getMaxVolumeM3(),
                'notes' => (string) $v->getNotes(),
            ],
            write: static function (object $v, array $d): void {
                $v->setName($d['name'])->setPlate($d['plate'])->setLengthM($d['length_m'])->setWidthM($d['width_m'])->setHeightM($d['height_m'])
                    ->setMaxPayloadKg($d['payload_kg'])->setMaxVolumeM3($d['volume_m3'])->setNotes($d['notes'] !== '' ? $d['notes'] : null);
            },
            desired: $desired,
            resettable: true,
        ), $version);
    }

    /** @param array<string, mixed> $def */
    private function syncCard(SeedContext $context, SyncReport $report, Department $department, string $key, array $def, string $version): void
    {
        $user = $this->userFor((string) $def['account']);
        if (!$user instanceof User) {
            $report->warnings[] = sprintf('Fahrerkarte «%s» übersprungen: Demo-Konto fehlt.', $def['account']);

            return;
        }
        $seedKey = $key . ':fahrerkarte:' . $def['account'];
        $classes = GrossanlassDriveCategories::sanitize($def['classes']);
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: function () use ($department, $user, $key, $seedKey): object {
                $c = new DepartmentGrossanlassUserCard();
                $c->setDepartment($department);
                $c->setUser($user);
                $c->setPublicCode(DemoStableCodes::prefixed(GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::USER_CARD), $key, $seedKey));

                return $c;
            },
            find: fn (string $id): ?object => $this->entityManager->getRepository(DepartmentGrossanlassUserCard::class)->find(['departmentId' => $department->getId(), 'userId' => $id]),
            idOf: static fn (object $c): string => (string) $c->getUserId(),
            read: static fn (object $c): array => ['may_drive' => $c->getMayDrive(), 'classes' => array_values($c->getDriveClasses())],
            write: function (object $c, array $d): void {
                $c->setMayDrive($d['may_drive']);
                $c->setDriveClasses($d['classes']);
                if ($d['may_drive'] && $c->getDriveVerifiedAt() === null) {
                    $c->setDriveProofKind(GrossanlassDriveCategories::PROOF_IN_PERSON);
                    $c->setDriveVerified(true);
                    $c->setDriveVerifiedAt(new \DateTime('2026-10-01 09:00:00'));
                }
            },
            desired: ['may_drive' => (bool) $def['may_drive'], 'classes' => array_values($classes)],
            adopt: fn (): ?object => $this->entityManager->getRepository(DepartmentGrossanlassUserCard::class)->find(['departmentId' => $department->getId(), 'userId' => $user->getId()]),
            resettable: true,
        ), $version);
    }

    /** @param array<string, mixed> $def */
    private function syncCharge(SeedContext $context, SyncReport $report, Department $department, User $actor, string $seedKey, array $def, string $version): void
    {
        $record = $context->findRecord($seedKey);
        if ($record !== null) {
            if ($this->entityManager->find(DepartmentGrossanlassCommitment::class, $record->getEntityId()) === null) {
                $report->warnings[] = sprintf('«%s» wurde gelöscht und wird nicht neu angelegt (Bewegungen hängen daran).', $seedKey);
            } else {
                ++$report->unchanged;
            }

            return;
        }
        $data = [
            'name' => $def['name'],
            'source' => $def['source'],
            'family' => $def['family'] ?? DepartmentGrossanlassCommitment::FAMILY_MATERIAL,
            'origin' => $def['origin'],
            'quantity' => $def['quantity'],
            'released' => true,
            'present_from' => $def['present_from'] ?? null,
            'present_to' => $def['present_to'] ?? null,
            'handover_from' => $def['handover_from'] ?? null,
            'handover_to' => $def['handover_to'] ?? null,
            'return_from' => $def['return_from'] ?? null,
            'return_to' => $def['return_to'] ?? null,
        ];
        if (isset($def['owner_kind'])) {
            $data['owner_kind'] = $def['owner_kind'];
        }
        $created = $this->commitments->create($department, $actor, $data);
        $row = $this->entityManager->find(DepartmentGrossanlassCommitment::class, (string) $created['id']);
        if ($row instanceof DepartmentGrossanlassCommitment) {
            $context->record($seedKey, $row, (string) $row->getId(), ManagedSeedApplier::hash(['seeded' => true]), $version);
            ++$report->created;
        }
    }

    private function exists(string $class, string $id, SeedContext $context): bool
    {
        if ($class === DepartmentGrossanlassUserCard::class) {
            return $this->entityManager->getRepository($class)->find(['departmentId' => $context->requireDepartment()->getId(), 'userId' => $id]) !== null;
        }
        if ($class === DepartmentGrossanlassBudgetArea::class) {
            [$departmentId, $area] = explode(':', $id, 2) + [1 => ''];

            return $this->entityManager->find($class, ['departmentId' => $departmentId, 'area' => $area]) !== null;
        }

        return $this->entityManager->find($class, $id) !== null;
    }

    private function dec(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }

    private function userFor(string $account): ?User
    {
        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy(['email' => DemoAccounts::email($account)]);
        $user = $profile instanceof Profile ? $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]) : null;

        return $user instanceof User ? $user : null;
    }
}
