<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassRoundForm;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\ActivityGrossanlassWishResponse;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCost;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\DepartmentGrossanlassVehicleNeed;
use App\Entity\Group;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Demo\DemoStableCodes;
use App\Service\Demo\Organisation\ManagedSeedApplier;
use App\Service\Demo\Organisation\ManagedSpec;
use App\Service\Demo\Organisation\SyncReport;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Grossanlass\GrossanlassMaterialStage;
use App\Service\Grossanlass\GrossanlassPlaceService;
use App\Service\Grossanlass\GrossanlassPlanningRoundService;
use App\Service\Grossanlass\GrossanlassRoundFormService;
use App\Util\GrossanlassIdGenerator;
use App\Util\DemoAccounts;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Demo-Event: 20 Bauaufträge mit Material, nach Gewerken gruppiert für Firmen-Anfragen.
 */
final class DemoGrossanlassEventJobsSeedService
{
    public const DEFAULT_DEPARTMENT_NAME = 'Demo-Grossanlass-Event';
    public const SEED_TAG = '[demo:event-jobs:v1]';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassRoundFormService $formService,
        private ?ManagedSeedApplier $applier = null,
        private ?GrossanlassPlanningRoundService $planningRounds = null,
        private ?GrossanlassPlaceService $places = null,
    ) {
    }

    /**
     * @return array{department: string, jobs: int, wishes: int, created_jobs: int, created_wishes: int}
     */
    public function seedByName(string $departmentName, bool $markDemo = false): array
    {
        if (\App\Service\Demo\Legacy\LegacyDemoRename::hasPrefix($departmentName)) {
            throw new \InvalidArgumentException(sprintf('«%s» ist ein ausgemustertes Legacy-Department (old-) und wird nicht mehr bespielt.', $departmentName));
        }

        $department = $this->entityManager->getRepository(Department::class)->findOneBy([
            'name' => $departmentName,
        ]);
        if (!$department instanceof Department || !$department->isGrossanlass()) {
            throw new \InvalidArgumentException(sprintf(
                'Grossanlass «%s» nicht gefunden.',
                $departmentName,
            ));
        }

        if ($department->getDemoScenarioKey() !== null) {
            throw new \InvalidArgumentException(sprintf('«%s» gehört zum Demo-Szenario «%s»; die Event-Daten werden mit app:demo:sync --scenario=%s angelegt.', $departmentName, $department->getDemoScenarioKey(), $department->getDemoScenarioKey()));
        }

        if (!$department->isDemoMode()) {
            if (!$markDemo) {
                throw new \InvalidArgumentException(sprintf(
                    'Grossanlass «%s» ist nicht als Demo (demo_mode) markiert; Seed schreibt nicht in fremde Departments. Mit --mark-demo ausdrücklich freigeben.',
                    $departmentName,
                ));
            }
            $department->setDemoMode(true);
            $this->entityManager->flush();
        }

        return $this->seed($department);
    }

    /**
     * @return array{department: string, jobs: int, wishes: int, created_jobs: int, created_wishes: int}
     */
    public function seed(Department $department): array
    {
        $bauten = $this->findBauten($department);
        $actor = $this->resolveActor();
        $round = $this->findOpenMaterialRound($department);
        $form = $this->formService->findOrCreateFormForRound($round);
        [$eventStart] = $this->eventWindow($department);

        $createdJobs = 0;
        $createdWishes = 0;
        $jobCount = 0;
        $wishCount = 0;

        foreach ($this->catalog() as $cluster) {
            [$bereich] = $this->ensureGroup(
                $department,
                (string) $cluster['bereich'],
                $bauten,
                Group::GROSSANLASS_KIND_BEREICH,
                (int) $cluster['sort'],
                (string) ($cluster['bereich_desc'] ?? ''),
                $this->dateFromOffset($eventStart, (int) $cluster['from']),
                $this->dateFromOffset($eventStart, (int) $cluster['to']),
                Group::BUILD_STATUS_PLANNED,
            );

            foreach ($cluster['jobs'] as $jobDef) {
                /** @var array<string, mixed> $jobDef */
                ++$jobCount;
                [$job, $jobCreated] = $this->ensureGroup(
                    $department,
                    (string) $jobDef['name'],
                    $bereich,
                    Group::GROSSANLASS_KIND_TEILBEREICH,
                    (int) $jobDef['sort'],
                    (string) $jobDef['description'],
                    $this->dateFromOffset($eventStart, (int) $jobDef['from']),
                    $this->dateFromOffset($eventStart, (int) $jobDef['to']),
                    Group::BUILD_STATUS_PLANNED,
                );
                if ($jobCreated) {
                    ++$createdJobs;
                }

                [$from, $to] = $this->windowFor((string) $jobDef['name']);
                $validFrom = new \DateTime($from . ' 08:00:00');
                $validTo = new \DateTime($to . ' 18:00:00');

                foreach ($jobDef['materials'] as $material) {
                    /** @var array<string, mixed> $material */
                    ++$wishCount;
                    $created = $this->ensureWish(
                        $round,
                        $form,
                        $job,
                        $actor,
                        $material,
                        $validFrom,
                        $validTo,
                        (string) $cluster['firma'],
                    );
                    if ($created) {
                        ++$createdWishes;
                    }
                }
            }
        }

        $this->entityManager->flush();

        return [
            'department' => $department->getName(),
            'jobs' => $jobCount,
            'wishes' => $wishCount,
            'created_jobs' => $createdJobs,
            'created_wishes' => $createdWishes,
        ];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Szenario-Sync (grossanlass-event): dieselben Kataloge, aber über den Seed-Ledger verwaltet. Fehlendes wird ergänzt,
    // manuell Geändertes bleibt (Abweichung), nichts wird gelöscht. Zeitfenster relativ zum Beginn des Anlasses.
    // ---------------------------------------------------------------------------------------------------------------

    /** Ressort (Seed-Schlüssel der Szenario-Gruppe), unter dem die Gewerke (Bereiche) liegen. */
    public const SCENARIO_PARENT_GROUP = 'bauten';

    /** @return list<string> alle Ledger-Schlüssel, die {@see syncScenario()} für das Szenario führt */
    public function expectedSeedKeys(string $scenarioKey): array
    {
        $keys = [];
        foreach ($this->catalog() as $cluster) {
            $keys[] = $scenarioKey . ':bauprojekt:bereich:' . self::slug((string) $cluster['bereich']);
            foreach ($cluster['jobs'] as $job) {
                $jobSlug = self::slug((string) $job['name']);
                $keys[] = $scenarioKey . ':bauprojekt:' . $jobSlug;
                $keys[] = $scenarioKey . ':ort:' . $jobSlug;
                foreach ($job['materials'] as $material) {
                    $keys[] = $scenarioKey . ':wunsch:' . $jobSlug . ':' . self::slug((string) $material['label']);
                }
            }
        }
        foreach ($this->taskCatalog() as $task) {
            $keys[] = $scenarioKey . ':aufgabe:' . $task['key'];
        }
        foreach ($this->vehicleNeedCatalog() as $need) {
            $keys[] = $scenarioKey . ':fahrzeugbedarf:' . $need['key'];
        }
        foreach ($this->procurementCatalog() as $line) {
            $keys[] = $scenarioKey . ':beschaffung:' . $line['key'];
            $keys[] = $scenarioKey . ':kosten:' . $line['key'];
        }

        return $keys;
    }

    /** @return list<string> Verstösse: fehlende Seed-Einträge oder gelöschte Datensätze */
    public function verifyScenario(SeedContext $context): array
    {
        $violations = [];
        $records = [];
        foreach ($context->records() as $record) {
            $records[$record->getSeedKey()] = $record;
        }
        foreach ($this->expectedSeedKeys($context->scenarioKey()) as $seedKey) {
            $record = $records[$seedKey] ?? null;
            if ($record === null) {
                $violations[] = sprintf('Seed-Eintrag «%s» fehlt (Sync ergänzt ihn).', $seedKey);
            } elseif ($this->entityManager->find($record->getEntityClass(), $record->getEntityId()) === null) {
                $violations[] = sprintf('Datensatz zu «%s» existiert nicht mehr (Sync legt ihn neu an).', $seedKey);
            }
        }

        return $violations;
    }

    public function syncScenario(SeedContext $context, SyncReport $report, string $version): void
    {
        if ($this->applier === null || $this->planningRounds === null || $this->places === null) {
            throw new \LogicException('Der Szenario-Sync braucht ManagedSeedApplier, Planungsrunden und Ortsdienst.');
        }
        $department = $context->requireDepartment();
        $key = $context->scenarioKey();
        $parentRecord = $context->findRecord($key . ':group:' . self::SCENARIO_PARENT_GROUP);
        $aufbau = $parentRecord !== null ? $this->entityManager->find(Group::class, $parentRecord->getEntityId()) : null;
        if (!$aufbau instanceof Group) {
            $report->warnings[] = 'Event-Inhalt übersprungen: Ressort «Aufbau» fehlt (Organisationsstruktur zuerst synchronisieren).';

            return;
        }
        $actor = $this->resolveActor();
        $round = $this->planningRounds->ensureOpenMaterialWishRound($department, $actor);
        $form = $this->formService->findOrCreateFormForRound($round);
        $jobs = [];
        foreach ($this->catalog() as $cluster) {
            $bereich = $this->managedGroup($context, $report, $key . ':bauprojekt:bereich:' . self::slug((string) $cluster['bereich']), $department, $aufbau, [
                'name' => (string) $cluster['bereich'],
                'kind' => Group::GROSSANLASS_KIND_BEREICH,
                'sort' => (int) $cluster['sort'],
                'description' => (string) ($cluster['bereich_desc'] ?? ''),
                'window' => $this->windowFor((string) $cluster['bereich']),
            ], $version);
            if (!$bereich instanceof Group) {
                continue;
            }
            foreach ($cluster['jobs'] as $jobDef) {
                $jobSlug = self::slug((string) $jobDef['name']);
                $job = $this->managedGroup($context, $report, $key . ':bauprojekt:' . $jobSlug, $department, $bereich, [
                    'name' => (string) $jobDef['name'],
                    'kind' => Group::GROSSANLASS_KIND_TEILBEREICH,
                    'sort' => (int) $jobDef['sort'],
                    'description' => (string) $jobDef['description'],
                    'window' => $this->windowFor((string) $jobDef['name']),
                ], $version);
                if (!$job instanceof Group) {
                    continue;
                }
                $jobs[(string) $jobDef['name']] = $job;
                $this->managedPlace($context, $report, $key . ':ort:' . $jobSlug, $department, $job, $version);
                [$from, $to] = $this->windowFor((string) $jobDef['name']);
                $validFrom = new \DateTime($from . ' 08:00:00');
                $validTo = new \DateTime($to . ' 18:00:00');
                foreach ($jobDef['materials'] as $material) {
                    $this->managedWish($context, $report, $key . ':wunsch:' . $jobSlug . ':' . self::slug((string) $material['label']), $round, $form, $job, $actor, $material, $validFrom, $validTo, (string) $cluster['firma'], $version);
                }
            }
        }

        foreach ($this->taskCatalog() as $def) {
            $group = $jobs[$def['job']] ?? null;
            if ($group instanceof Group) {
                $this->managedTask($context, $report, $key . ':aufgabe:' . $def['key'], $department, $group, $def, $version);
            }
        }
        foreach ($this->vehicleNeedCatalog() as $def) {
            $group = $jobs[$def['job']] ?? null;
            if ($group instanceof Group) {
                $this->managedVehicleNeed($context, $report, $key . ':fahrzeugbedarf:' . $def['key'], $department, $group, $def, $version);
            }
        }
        foreach ($this->procurementCatalog() as $def) {
            $group = $jobs[$def['job']] ?? null;
            if ($group instanceof Group) {
                $line = $this->managedProcurementLine($context, $report, $key . ':beschaffung:' . $def['key'], $department, $group, $actor, $def, $version);
                if ($line instanceof ActivityGrossanlassProcurementLine) {
                    $this->managedCost($context, $report, $key . ':kosten:' . $def['key'], $department, $group, $line, $def, $version);
                }
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Feste Zeitfenster der Gewerke und Bauprojekte im Event-Zeitplan 2027 (Aufbau 19.05.–04.06., Durchführung 04.–06.06.,
     * Abbau 06.–23.06.): Beginn der Aufbauarbeiten bis Ende des Rückbaus bzw. der Rücklieferung. Absolute Tage, einmalig
     * fachlich festgelegt; nichts wird aus dem heutigen Datum oder dem Anlassbeginn abgeleitet. Gewerke umfassen ihre Bauprojekte.
     *
     * @return array<string, array{0: string, 1: string}> Name → [von, bis] (Y-m-d)
     */
    private function scenarioWindows(): array
    {
        return [
            // Holzbau: früher Aufbau, Rückbau gleich nach dem Anlass
            'Ticket-Häuschen Eingang' => ['2027-05-19', '2027-06-09'],
            'Bar-Tresen West' => ['2027-05-21', '2027-06-09'],
            'Crew-Hütte Backstage' => ['2027-05-20', '2027-06-09'],
            'Rollstuhlrampe Haupteingang' => ['2027-05-24', '2027-06-08'],
            'Holzbau' => ['2027-05-19', '2027-06-09'],
            // Bühne & Gerüst: Aufbau in der letzten Woche, Rückbau mit dem Gerüstbauer
            'Hauptbühnen-Podest' => ['2027-05-31', '2027-06-09'],
            'Delay-Tower Ost' => ['2027-05-31', '2027-06-08'],
            'FOH-Erweiterung' => ['2027-05-31', '2027-06-08'],
            'Lichtbrücke Traversen' => ['2027-06-01', '2027-06-09'],
            'Bühne & Gerüst' => ['2027-05-31', '2027-06-09'],
            // Sanitär- und Wasserbau: bis zur Rücklieferung der Anlagen
            'WC-Park West' => ['2027-06-01', '2027-06-14'],
            'Crew-Duschen' => ['2027-06-01', '2027-06-14'],
            'Grauwasser Hauptbühne' => ['2027-06-01', '2027-06-11'],
            'Sanitär- & Wasserbau' => ['2027-06-01', '2027-06-14'],
            // Energie & Strom
            'Hauptverteiler CEE' => ['2027-05-28', '2027-06-11'],
            'Notstromaggregat' => ['2027-05-30', '2027-06-14'],
            'Wegeleuchten Gelände' => ['2027-06-01', '2027-06-14'],
            'Energie & Strom' => ['2027-05-28', '2027-06-14'],
            // Zelte & Wetterschutz
            'Catering-Zelt 20×30' => ['2027-06-01', '2027-06-15'],
            'Crew-Zelt Backstage' => ['2027-06-01', '2027-06-15'],
            'Info-Pagode Eingang' => ['2027-06-02', '2027-06-12'],
            'Zelte & Wetterschutz' => ['2027-06-01', '2027-06-15'],
            // Sicherheit & Zaun
            'Bauzaun Perimeter' => ['2027-05-31', '2027-06-16'],
            'Dränggitter Front of Stage' => ['2027-06-01', '2027-06-12'],
            'Fluchtweg-Markierung' => ['2027-06-02', '2027-06-14'],
            'Sicherheit & Zaun' => ['2027-05-31', '2027-06-16'],
        ];
    }

    /** @return array{0: string, 1: string} */
    private function windowFor(string $name): array
    {
        return $this->scenarioWindows()[$name] ?? throw new \LogicException(sprintf('Kein Zeitfenster für «%s» im Event-Zeitplan 2027.', $name));
    }

    /** Stabiler Schlüsselteil aus einem Anzeigenamen (Umlaute und Sonderzeichen ersetzt). */
    public static function slug(string $name): string
    {
        $ascii = strtr(mb_strtolower($name), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', '×' => 'x', '&' => 'und']);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    }

    /** @param array<string, mixed> $def */
    private function managedGroup(SeedContext $context, SyncReport $report, string $seedKey, Department $department, Group $parent, array $def, string $version): ?Group
    {
        $desired = [
            'name' => $def['name'],
            'parent_id' => $parent->getId(),
            'kind' => $def['kind'],
            'sort' => $def['sort'],
            'description' => $def['description'],
            'window_start' => $def['window'][0],
            'window_end' => $def['window'][1],
            'build_status' => Group::BUILD_STATUS_PLANNED,
        ];
        $entity = $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: function () use ($department): Group {
                $g = new Group();
                $g->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::GROUP, Group::class));
                $g->setDepartment($department);

                return $g;
            },
            find: fn (string $id): ?object => $this->entityManager->find(Group::class, $id),
            idOf: static fn (object $g): string => (string) $g->getId(),
            read: static fn (object $g): array => [
                'name' => $g->getName(),
                'parent_id' => $g->getParentId(),
                'kind' => $g->getGrossanlassKind(),
                'sort' => $g->getSortOrder(),
                'description' => (string) $g->getDescription(),
                'window_start' => $g->getWindowStart()?->format('Y-m-d'),
                'window_end' => $g->getWindowEnd()?->format('Y-m-d'),
                'build_status' => $g->getBuildStatus(),
            ],
            write: function (object $g, array $d): void {
                $g->setName($d['name']);
                $g->setParent($this->entityManager->find(Group::class, $d['parent_id']));
                $g->setGrossanlassKind($d['kind']);
                $g->setSortOrder($d['sort']);
                $g->setDescription($d['description']);
                $g->setWindowStart(new \DateTime($d['window_start']));
                $g->setWindowEnd(new \DateTime($d['window_end']));
                $g->setBuildStatus($d['build_status']);
            },
            desired: $desired,
            adopt: fn (): ?object => $this->adoptUnrecorded(Group::class, ['departmentId' => $department->getId(), 'name' => $def['name'], 'parentId' => $parent->getId()]),
            resettable: true,
        ), $version);

        return $entity instanceof Group ? $entity : null;
    }

    private function managedPlace(SeedContext $context, SyncReport $report, string $seedKey, Department $department, Group $job, string $version): void
    {
        $ensure = fn (): DepartmentGrossanlassPlace => $this->places->ensureForBauprojekt($department, $job);
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: $ensure,
            find: fn (string $id): ?object => $this->entityManager->find(DepartmentGrossanlassPlace::class, $id),
            idOf: static fn (object $p): string => (string) $p->getId(),
            read: static fn (object $p): array => ['group_id' => $p->getGroupId(), 'kind' => $p->getKind()],
            write: static function (object $p, array $d): void {
            },
            desired: ['group_id' => $job->getId(), 'kind' => 'bauprojekt'],
            adopt: fn (): ?object => $this->adoptUnrecorded(DepartmentGrossanlassPlace::class, ['departmentId' => $department->getId(), 'groupId' => $job->getId()]),
            afterCreate: function (object $p) use ($context, $seedKey): void {
                // Neu angelegter Ort: QR-Code reproduzierbar aus dem Seed-Schlüssel, damit er nach einem Reset derselbe bleibt.
                $code = DemoStableCodes::prefixed(GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::PLACE_PUBLIC), $context->scenarioKey(), $seedKey);
                if ($p instanceof DepartmentGrossanlassPlace && $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findOneBy(['publicCode' => $code]) === null) {
                    $p->setPublicCode($code);
                }
            },
            resettable: true,
        ), $version);
    }

    /** @param array<string, mixed> $material */
    private function managedWish(SeedContext $context, SyncReport $report, string $seedKey, ActivityGrossanlassRound $round, ActivityGrossanlassRoundForm $form, Group $job, User $actor, array $material, \DateTime $validFrom, \DateTime $validTo, string $firmaTyp, string $version): void
    {
        $label = (string) $material['label'];
        $desired = [
            'label' => $label,
            'quantity' => max(1, (int) ($material['qty'] ?? 1)),
            'unit' => (string) ($material['unit'] ?? 'Stk'),
            'location' => (string) ($material['location'] ?? $job->getName()),
            'valid_from' => $validFrom->format('Y-m-d H:i:s'),
            'valid_to' => $validTo->format('Y-m-d H:i:s'),
            'timeframe' => (string) ($material['timeframe'] ?? ''),
            'notes' => sprintf("Firma-Typ: %s\n%s", $firmaTyp, (string) ($material['notes'] ?? '')),
            'pickup_need' => isset($material['pickup']) ? (string) $material['pickup'] : null,
            'pickup_place' => isset($material['pickup_place']) ? (string) $material['pickup_place'] : null,
            'return_needed' => (bool) ($material['return'] ?? false),
        ];
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: function () use ($round, $form, $job, $actor): ActivityGrossanlassWishLine {
                $response = new ActivityGrossanlassWishResponse();
                $response->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::WISH_RESPONSE, ActivityGrossanlassWishResponse::class));
                $response->setRound($round);
                $response->setForm($form);
                $response->setGroup($job);
                $response->setCreatedByUser($actor);
                $response->setStatus(ActivityGrossanlassWishResponse::STATUS_REQUESTED);
                $this->entityManager->persist($response);

                $line = new ActivityGrossanlassWishLine();
                $line->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::WISH_LINE, ActivityGrossanlassWishLine::class));
                $line->setRound($round);
                $line->setGroup($job);
                $line->setCreatedByUser($actor);
                $line->setResponse($response);
                $line->setStatus(ActivityGrossanlassWishLine::STATUS_REQUESTED);
                $line->setWishKind(ActivityGrossanlassWishLine::KIND_MATERIAL);
                $line->setLastStage(GrossanlassMaterialStage::GROB);
                $line->setSelfOrganized(false);

                return $line;
            },
            find: fn (string $id): ?object => $this->entityManager->find(ActivityGrossanlassWishLine::class, $id),
            idOf: static fn (object $l): string => (string) $l->getId(),
            read: static fn (object $l): array => [
                'label' => $l->getLabel(),
                'quantity' => $l->getQuantity(),
                'unit' => $l->getQuantityUnit(),
                'location' => $l->getLocation(),
                'valid_from' => $l->getValidFrom()->format('Y-m-d H:i:s'),
                'valid_to' => $l->getValidTo()->format('Y-m-d H:i:s'),
                'timeframe' => (string) $l->getTimeframeNotes(),
                'notes' => (string) $l->getNotes(),
                'pickup_need' => $l->getPickupNeed(),
                'pickup_place' => $l->getPickupPlace(),
                'return_needed' => $l->isReturnNeeded(),
            ],
            write: static function (object $l, array $d): void {
                $l->setLabel($d['label']);
                $l->setQuantity($d['quantity']);
                $l->setQuantityUnit($d['unit']);
                $l->setLocation($d['location']);
                $l->setValidFrom(new \DateTime($d['valid_from']));
                $l->setValidTo(new \DateTime($d['valid_to']));
                $l->setTimeframeNotes($d['timeframe']);
                $l->setNotes($d['notes']);
                $l->setPickupNeed($d['pickup_need']);
                $l->setPickupPlace($d['pickup_place']);
                $l->setReturnNeeded($d['return_needed']);
                $l->touchUpdatedAt();
            },
            desired: $desired,
            adopt: function () use ($job, $label): ?object {
                $line = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->findOneBy(['group' => $job, 'label' => $label]);

                return $line instanceof ActivityGrossanlassWishLine && !$this->isRecorded($line::class, (string) $line->getId()) ? $line : null;
            },
            resettable: true,
        ), $version);
    }

    /** @param array<string, mixed> $def */
    private function managedTask(SeedContext $context, SyncReport $report, string $seedKey, Department $department, Group $group, array $def, string $version): void
    {
        $desired = [
            'group_id' => $group->getId(),
            'title' => (string) $def['title'],
            'description' => (string) $def['description'],
            'sort' => (int) $def['sort'],
            'starts_at' => sprintf('%s %02d:%02d:00', $def['date'], (int) $def['hour'], (int) ($def['minute'] ?? 0)),
            'duration' => (int) $def['duration'],
        ];
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: function () use ($department, $group): DepartmentGrossanlassTask {
                $t = new DepartmentGrossanlassTask();
                $t->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TASK, DepartmentGrossanlassTask::class));
                $t->setDepartment($department);
                $t->setGroup($group);

                return $t;
            },
            find: fn (string $id): ?object => $this->entityManager->find(DepartmentGrossanlassTask::class, $id),
            idOf: static fn (object $t): string => (string) $t->getId(),
            read: static fn (object $t): array => [
                'group_id' => $t->getGroupId(),
                'title' => $t->getTitle(),
                'description' => (string) $t->getDescription(),
                'sort' => $t->getSortOrder(),
                'starts_at' => $t->getStartsAt()?->format('Y-m-d H:i:s'),
                'duration' => $t->getDurationMinutes(),
            ],
            write: static function (object $t, array $d): void {
                $t->setTitle($d['title'])->setDescription($d['description'])->setSortOrder($d['sort'])
                    ->setStartsAt(new \DateTime($d['starts_at']))->setDurationMinutes($d['duration']);
            },
            desired: $desired,
            resettable: true,
        ), $version);
    }

    /** @param array<string, mixed> $def */
    private function managedVehicleNeed(SeedContext $context, SyncReport $report, string $seedKey, Department $department, Group $group, array $def, string $version): void
    {
        $desired = [
            'group_id' => $group->getId(),
            'vehicle' => (string) $def['vehicle'],
            'task' => (string) $def['task'],
            'sort' => (int) $def['sort'],
            'starts_at' => sprintf('%s %02d:%02d:00', $def['date'], (int) $def['hour'], (int) ($def['minute'] ?? 0)),
            'duration' => (int) $def['duration'],
        ];
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: function () use ($department, $group): DepartmentGrossanlassVehicleNeed {
                $n = new DepartmentGrossanlassVehicleNeed();
                $n->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::VEHICLE_NEED, DepartmentGrossanlassVehicleNeed::class));
                $n->setDepartment($department);
                $n->setGroup($group);

                return $n;
            },
            find: fn (string $id): ?object => $this->entityManager->find(DepartmentGrossanlassVehicleNeed::class, $id),
            idOf: static fn (object $n): string => (string) $n->getId(),
            read: static fn (object $n): array => [
                'group_id' => $n->getGroupId(),
                'vehicle' => $n->getVehicleLabel(),
                'task' => $n->getTaskLabel(),
                'sort' => $n->getSortOrder(),
                'starts_at' => $n->getStartsAt()?->format('Y-m-d H:i:s'),
                'duration' => $n->getDurationMinutes(),
            ],
            write: static function (object $n, array $d): void {
                $n->setVehicleLabel($d['vehicle'])->setTaskLabel($d['task'])->setSortOrder($d['sort'])
                    ->setStartsAt(new \DateTime($d['starts_at']))->setDurationMinutes($d['duration']);
            },
            desired: $desired,
            resettable: true,
        ), $version);
    }

    /** @param array<string, mixed> $def */
    private function managedProcurementLine(SeedContext $context, SyncReport $report, string $seedKey, Department $department, Group $group, User $actor, array $def, string $version): ?ActivityGrossanlassProcurementLine
    {
        $category = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)->findOneBy(['departmentId' => $department->getId(), 'name' => $def['category']]);
        $desired = [
            'group_id' => $group->getId(),
            'wish_kind' => (string) $def['kind'],
            'label' => (string) $def['label'],
            'quantity' => (int) $def['qty'],
            'location' => $group->getName(),
            'notes' => (string) $def['notes'],
            'status' => ActivityGrossanlassProcurementLine::STATUS_BEDARF,
            'category_id' => $category?->getId(),
        ];
        $entity = $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: function () use ($department, $group, $actor): ActivityGrossanlassProcurementLine {
                $l = new ActivityGrossanlassProcurementLine();
                $l->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::PROCUREMENT_LINE, ActivityGrossanlassProcurementLine::class));
                $l->setDepartment($department);
                $l->setGroup($group);
                $l->setCreatedByUser($actor);
                $l->setSource(ActivityGrossanlassProcurementLine::SOURCE_DIRECT);
                $l->setSupplyMode(ActivityGrossanlassProcurementLine::SUPPLY_OPEN);
                $l->setSelfOrganized(false);

                return $l;
            },
            find: fn (string $id): ?object => $this->entityManager->find(ActivityGrossanlassProcurementLine::class, $id),
            idOf: static fn (object $l): string => (string) $l->getId(),
            read: static fn (object $l): array => [
                'group_id' => $l->getGroupId(),
                'wish_kind' => $l->getWishKind(),
                'label' => $l->getLabel(),
                'quantity' => $l->getQuantity(),
                'location' => $l->getLocation(),
                'notes' => (string) $l->getNotes(),
                'status' => $l->getStatus(),
                'category_id' => $l->getCategoryId(),
            ],
            write: function (object $l, array $d): void {
                $l->setWishKind($d['wish_kind'])->setLabel($d['label'])->setQuantity($d['quantity'])->setLocation($d['location'])
                    ->setNotes($d['notes'])->setStatus($d['status']);
                $l->setCategory($d['category_id'] !== null ? $this->entityManager->find(ActivityGrossanlassProcurementCategory::class, $d['category_id']) : null);
            },
            desired: $desired,
            resettable: true,
        ), $version);

        return $entity instanceof ActivityGrossanlassProcurementLine ? $entity : null;
    }

    /** @param array<string, mixed> $def */
    private function managedCost(SeedContext $context, SyncReport $report, string $seedKey, Department $department, Group $group, ActivityGrossanlassProcurementLine $line, array $def, string $version): void
    {
        $desired = [
            'line_id' => $line->getId(),
            'kind' => (string) $def['cost_kind'],
            'label' => (string) $def['label'],
            'soll_chf' => number_format((float) $def['soll_chf'], 2, '.', ''),
            'status' => DepartmentGrossanlassCost::STATUS_PLANNED,
            'group_id' => $group->getId(),
        ];
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: function () use ($department): DepartmentGrossanlassCost {
                $c = new DepartmentGrossanlassCost();
                $c->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::COST, DepartmentGrossanlassCost::class));
                $c->setDepartment($department);

                return $c;
            },
            find: fn (string $id): ?object => $this->entityManager->find(DepartmentGrossanlassCost::class, $id),
            idOf: static fn (object $c): string => (string) $c->getId(),
            read: static fn (object $c): array => [
                'line_id' => $c->getProcurementLineId(),
                'kind' => $c->getCostKind(),
                'label' => $c->getLabel(),
                'soll_chf' => $c->getSollChf() !== null ? number_format((float) $c->getSollChf(), 2, '.', '') : null,
                'status' => $c->getStatus(),
                'group_id' => $c->getRequestingGroupId(),
            ],
            write: function (object $c, array $d): void {
                $group = $this->entityManager->find(Group::class, $d['group_id']);
                $c->setProcurementLine($this->entityManager->find(ActivityGrossanlassProcurementLine::class, $d['line_id']))
                    ->setCostKind($d['kind'])->setLabel($d['label'])->setSollChf($d['soll_chf'])->setStatus($d['status'])
                    ->setRequestingGroup($group)->setPayerGroup($group);
            },
            desired: $desired,
            resettable: true,
        ), $version);
    }

    /** @param array<string, mixed> $criteria */
    private function adoptUnrecorded(string $class, array $criteria): ?object
    {
        $entity = $this->entityManager->getRepository($class)->findOneBy($criteria);

        return $entity !== null && !$this->isRecorded($class, (string) $entity->getId()) ? $entity : null;
    }

    private function isRecorded(string $class, string $id): bool
    {
        return $this->entityManager->getRepository(\App\Entity\DemoSeedRecord::class)->findOneBy(['entityClass' => $class, 'entityId' => $id]) !== null;
    }

    /**
     * Aufgaben der Bauprojekte im Aufbau und Abbau (feste Tage, siehe {@see scenarioWindows()}).
     *
     * @return list<array<string, mixed>>
     */
    private function taskCatalog(): array
    {
        return [
            ['key' => 'ticket-staenderwerk', 'job' => 'Ticket-Häuschen Eingang', 'title' => 'Ständerwerk aufstellen', 'description' => 'Rahmen nach Bauplan zusammenbauen und ausrichten.', 'sort' => 1, 'date' => '2027-05-20', 'hour' => 8, 'duration' => 120],
            ['key' => 'ticket-dach', 'job' => 'Ticket-Häuschen Eingang', 'title' => 'Dach eindecken', 'description' => 'OSB-Beplankung verlegen und Schindeln aufbringen.', 'sort' => 2, 'date' => '2027-05-26', 'hour' => 8, 'duration' => 180],
            ['key' => 'podest-montage', 'job' => 'Hauptbühnen-Podest', 'title' => 'Podest montieren', 'description' => 'Podest-Elemente verschrauben, Höhe und Waage prüfen.', 'sort' => 1, 'date' => '2027-06-01', 'hour' => 8, 'duration' => 480],
            ['key' => 'podest-demontage', 'job' => 'Hauptbühnen-Podest', 'title' => 'Podest demontieren', 'description' => 'Elemente lösen, zählen und für die Rücklieferung verladen.', 'sort' => 2, 'date' => '2027-06-07', 'hour' => 8, 'duration' => 360],
            ['key' => 'zaun-felder', 'job' => 'Bauzaun Perimeter', 'title' => 'Zaunfelder stellen', 'description' => 'Zaunfelder entlang des Perimeters stellen und verklammern.', 'sort' => 1, 'date' => '2027-06-01', 'hour' => 7, 'minute' => 30, 'duration' => 240],
            ['key' => 'zaun-abbau', 'job' => 'Bauzaun Perimeter', 'title' => 'Zaunfelder abbauen', 'description' => 'Felder und Klammern einsammeln, Vollständigkeit prüfen.', 'sort' => 2, 'date' => '2027-06-08', 'hour' => 8, 'duration' => 300],
            ['key' => 'zelt-aufbau', 'job' => 'Catering-Zelt 20×30', 'title' => 'Zelt aufstellen', 'description' => 'Zelthülle aufrichten, abspannen und Böden verlegen.', 'sort' => 1, 'date' => '2027-06-02', 'hour' => 8, 'duration' => 360],
        ];
    }

    /**
     * Fahrzeugbedarfe der Bauprojekte (feste Tage).
     *
     * @return list<array<string, mixed>>
     */
    private function vehicleNeedCatalog(): array
    {
        return [
            ['key' => 'tresen-liefern', 'job' => 'Bar-Tresen West', 'vehicle' => 'Lieferwagen', 'task' => 'Tresen-Elemente liefern', 'sort' => 1, 'date' => '2027-05-21', 'hour' => 9, 'duration' => 45],
            ['key' => 'aggregat-anliefern', 'job' => 'Notstromaggregat', 'vehicle' => 'Lieferwagen mit Hebebühne', 'task' => 'Dieselaggregat anliefern', 'sort' => 1, 'date' => '2027-05-30', 'hour' => 8, 'duration' => 60],
            ['key' => 'podest-entladen', 'job' => 'Hauptbühnen-Podest', 'vehicle' => 'Teleskoplader', 'task' => 'Podest-Elemente entladen', 'sort' => 1, 'date' => '2027-05-31', 'hour' => 7, 'minute' => 30, 'duration' => 90],
            ['key' => 'zelt-abholen', 'job' => 'Catering-Zelt 20×30', 'vehicle' => '7,5-t-Lastwagen', 'task' => 'Zeltmaterial abholen', 'sort' => 1, 'date' => '2027-06-01', 'hour' => 13, 'duration' => 60],
            ['key' => 'aggregat-retour', 'job' => 'Notstromaggregat', 'vehicle' => 'Lieferwagen mit Hebebühne', 'task' => 'Dieselaggregat zurückbringen', 'sort' => 2, 'date' => '2027-06-14', 'hour' => 8, 'duration' => 60],
            ['key' => 'zelt-retour', 'job' => 'Catering-Zelt 20×30', 'vehicle' => '7,5-t-Lastwagen', 'task' => 'Zeltmaterial zurückbringen', 'sort' => 2, 'date' => '2027-06-15', 'hour' => 9, 'duration' => 60],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function procurementCatalog(): array
    {
        $category = 'Bauten, Überdachungen & Raummodule';

        return [
            ['key' => 'festzelt-3x3', 'job' => 'Info-Pagode Eingang', 'kind' => 'material', 'label' => 'Festzelt 3x3 m', 'qty' => 4, 'notes' => 'Als Wetterschutz an den Eingängen.', 'category' => $category, 'cost_kind' => DepartmentGrossanlassCost::KIND_LOAN, 'soll_chf' => 480],
            ['key' => 'stromverteiler-63a', 'job' => 'Hauptverteiler CEE', 'kind' => 'material', 'label' => 'Stromverteiler 63 A', 'qty' => 6, 'notes' => 'Mietgeräte für die Unterverteilung.', 'category' => $category, 'cost_kind' => DepartmentGrossanlassCost::KIND_RENTAL, 'soll_chf' => 360],
        ];
    }

    private function findBauten(Department $department): Group
    {
        foreach (['Demo-Bauten', 'Bauten'] as $name) {
            $group = $this->entityManager->getRepository(Group::class)->findOneBy([
                'departmentId' => $department->getId(),
                'name' => $name,
            ]);
            if ($group instanceof Group) {
                return $group;
            }
        }

        throw new \RuntimeException('Ressort Bauten fehlt im Demo-Event.');
    }

    private function resolveActor(): User
    {
        foreach ([DemoAccounts::email('ga-ok'), DemoAccounts::email('ga-mw'), DemoAccounts::email('superadmin')] as $email) {
            $profile = $this->entityManager->getRepository(Profile::class)->findOneBy(['email' => $email]);
            if (!$profile instanceof Profile) {
                continue;
            }
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);
            if ($user instanceof User) {
                return $user;
            }
        }

        throw new \RuntimeException('Kein Demo-User (ga-ok / ga-mw) gefunden.');
    }

    private function findOpenMaterialRound(Department $department): ActivityGrossanlassRound
    {
        $round = $this->entityManager->getRepository(ActivityGrossanlassRound::class)
            ->createQueryBuilder('r')
            ->innerJoin('r.activity', 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('r.formPurpose = :purpose')
            ->andWhere('r.status = :status')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('purpose', ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH)
            ->setParameter('status', ActivityGrossanlassRound::STATUS_OPEN)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$round instanceof ActivityGrossanlassRound) {
            throw new \RuntimeException('Keine offene Materialrunde im Demo-Event.');
        }

        return $round;
    }

    /**
     * @return array{0: \DateTime, 1: \DateTime}
     */
    private function eventWindow(Department $department): array
    {
        $config = $department->getGrossanlassConfig();
        $start = $config?->getPlannedEventStart() ?? new \DateTime('today');
        $end = $config?->getPlannedEventEnd() ?? (clone $start)->modify('+2 days');

        return [$start, $end];
    }

    private function dateFromOffset(\DateTimeInterface $eventStart, int $offsetDays): \DateTime
    {
        $date = \DateTime::createFromInterface($eventStart)->setTime(0, 0, 0);
        if ($offsetDays !== 0) {
            $date->modify(sprintf('%+d days', $offsetDays));
        }

        return $date;
    }

    /**
     * @return array{0: Group, 1: bool}
     */
    private function ensureGroup(
        Department $department,
        string $name,
        Group $parent,
        string $kind,
        int $sortOrder,
        string $description,
        \DateTimeInterface $windowStart,
        \DateTimeInterface $windowEnd,
        string $buildStatus,
    ): array {
        $group = $this->entityManager->getRepository(Group::class)->findOneBy([
            'departmentId' => $department->getId(),
            'name' => $name,
            'parentId' => $parent->getId(),
        ]);
        $isNew = !$group instanceof Group;
        if ($isNew) {
            $group = new Group();
            $group->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::GROUP, Group::class));
            $group->setDepartment($department);
            $group->setName($name);
            $group->setParent($parent);
            $this->entityManager->persist($group);
        }

        $group->setGrossanlassKind($kind);
        $group->setSortOrder($sortOrder);
        $group->setDescription($description);
        $group->setWindowStart($windowStart);
        $group->setWindowEnd($windowEnd);
        $group->setBuildStatus($buildStatus);

        if ($isNew) {
            $this->entityManager->flush();
        }

        return [$group, $isNew];
    }

    /**
     * @param array<string, mixed> $material
     */
    private function ensureWish(
        ActivityGrossanlassRound $round,
        ActivityGrossanlassRoundForm $form,
        Group $job,
        User $actor,
        array $material,
        \DateTime $validFrom,
        \DateTime $validTo,
        string $firmaTyp,
    ): bool {
        $label = (string) $material['label'];
        $existing = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->where('w.group = :group')
            ->andWhere('w.label = :label')
            ->andWhere('w.notes LIKE :tag')
            ->setParameter('group', $job)
            ->setParameter('label', $label)
            ->setParameter('tag', '%' . self::SEED_TAG . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $isNew = !$existing instanceof ActivityGrossanlassWishLine;
        if ($isNew) {
            $response = new ActivityGrossanlassWishResponse();
            $response->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::WISH_RESPONSE,
                ActivityGrossanlassWishResponse::class,
            ));
            $response->setRound($round);
            $response->setForm($form);
            $response->setGroup($job);
            $response->setCreatedByUser($actor);
            $response->setStatus(ActivityGrossanlassWishResponse::STATUS_REQUESTED);
            $this->entityManager->persist($response);

            $existing = new ActivityGrossanlassWishLine();
            $existing->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::WISH_LINE,
                ActivityGrossanlassWishLine::class,
            ));
            $existing->setRound($round);
            $existing->setGroup($job);
            $existing->setCreatedByUser($actor);
            $existing->setResponse($response);
            $existing->setStatus(ActivityGrossanlassWishLine::STATUS_REQUESTED);
            $this->entityManager->persist($existing);
        }

        $notes = sprintf(
            "Firma-Typ: %s\n%s\n%s",
            $firmaTyp,
            self::SEED_TAG,
            (string) ($material['notes'] ?? ''),
        );

        $existing->setWishKind(ActivityGrossanlassWishLine::KIND_MATERIAL);
        $existing->setLastStage(GrossanlassMaterialStage::GROB);
        $existing->setLabel($label);
        $existing->setQuantity(max(1, (int) ($material['qty'] ?? 1)));
        $existing->setQuantityUnit((string) ($material['unit'] ?? 'Stk'));
        $existing->setLocation((string) ($material['location'] ?? $job->getName()));
        $existing->setValidFrom($validFrom);
        $existing->setValidTo($validTo);
        $existing->setTimeframeNotes((string) ($material['timeframe'] ?? ''));
        $existing->setNotes($notes);
        $existing->setSelfOrganized(false);
        $existing->setPickupNeed(isset($material['pickup']) ? (string) $material['pickup'] : null);
        $existing->setPickupPlace(isset($material['pickup_place']) ? (string) $material['pickup_place'] : null);
        $existing->setReturnNeeded((bool) ($material['return'] ?? false));
        $existing->touchUpdatedAt();

        return $isNew;
    }

    /**
     * 6 Gewerke → 20 Bauaufträge. Material bewusst nach Firmen-Typen gebündelt.
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'bereich' => 'Holzbau',
                'bereich_desc' => 'Zimmerei / Schreinerei — Ticket, Tresen, Hütten, Rampen.',
                'firma' => 'Holzbau',
                'sort' => 110,
                'from' => -10,
                'to' => -1,
                'jobs' => [
                    [
                        'name' => 'Ticket-Häuschen Eingang',
                        'description' => 'Kleiner Holzbau am Haupteingang. Anfrage Zimmerei.',
                        'sort' => 10,
                        'from' => -10,
                        'to' => -2,
                        'materials' => [
                            $this->mat('KVH 60×80 Ständerholz', 40, 'Stk', 'Eingang Nord', 'Kantholz für Ständerwerk und Pfetten.', false),
                            $this->mat('OSB 18 mm Platten', 24, 'Stk', 'Eingang Nord', 'Beplankung Wände und Dach.', false),
                            $this->mat('Bitumenschindeln anthrazit', 8, 'Stk', 'Eingang Nord', 'Dachdeckung Ticket-Häuschen, Pakete à 3 m².', false),
                            $this->mat('Holzbauschrauben 6×80 Sortiment', 4, 'Stk', 'Eingang Nord', 'Schrauben-Kartons inkl. Winkelverbinder.', false),
                        ],
                    ],
                    [
                        'name' => 'Bar-Tresen West',
                        'description' => 'Theke aus Palettenholz und Arbeitsplatte. Anfrage Schreinerei.',
                        'sort' => 20,
                        'from' => -8,
                        'to' => -1,
                        'materials' => [
                            $this->mat('Euro-Paletten neuwertig', 18, 'Stk', 'Gastro West', 'Unterkonstruktion Tresen, stapelbar.', true, 'can', 'Holzbau-Partner'),
                            $this->mat('Arbeitsplatte 40 mm Buche 4 m', 3, 'Stk', 'Gastro West', 'Tresenplatte, wasserfest versiegelt.', false),
                            $this->mat('Sperrholz 15 mm 250×125', 10, 'Stk', 'Gastro West', 'Frontverkleidung und Seitenteile.', false),
                        ],
                    ],
                    [
                        'name' => 'Crew-Hütte Backstage',
                        'description' => 'Wetterfeste Holzhütte als Crew-Raum. Anfrage Zimmerei.',
                        'sort' => 30,
                        'from' => -9,
                        'to' => -2,
                        'materials' => [
                            $this->mat('Holzschalung 22 mm Nut+Feder', 80, 'm', 'Backstage', 'Aussenverschalung, Fichte vorimprägniert.', false),
                            $this->mat('Dachlatten 30×50', 60, 'm', 'Backstage', 'Lattung für Wellplatten-Dach.', false),
                            $this->mat('Wellplatten Trapez 1 m', 24, 'Stk', 'Backstage', 'Dach und Wetterseite.', false),
                        ],
                    ],
                    [
                        'name' => 'Rollstuhlrampe Haupteingang',
                        'description' => 'Barrierefreie Rampe 1:12. Anfrage Zimmerei / Metallbau Holz.',
                        'sort' => 40,
                        'from' => -7,
                        'to' => -1,
                        'materials' => [
                            $this->mat('Bohlen 50×200 geriffelt', 28, 'Stk', 'Eingang Nord', 'Lauffläche Rampe, rutschhemmend.', false),
                            $this->mat('Geländerhölzer 90×90', 12, 'Stk', 'Eingang Nord', 'Pfosten und Holm, inkl. Handlauf.', false),
                            $this->mat('Abstandhalter / Keile Sortiment', 2, 'Stk', 'Eingang Nord', 'Gefälle 8 % ausgleichen.', false),
                        ],
                    ],
                ],
            ],
            [
                'bereich' => 'Bühne & Gerüst',
                'bereich_desc' => 'Gerüstbauer / Bühnenverleih — Podeste, Towers, Traversen.',
                'firma' => 'Gerüst & Bühne',
                'sort' => 120,
                'from' => -3,
                'to' => 0,
                'jobs' => [
                    [
                        'name' => 'Hauptbühnen-Podest',
                        'description' => 'Bühnenfläche 12×8 m aus Systempratzen. Anfrage Bühnenverleih.',
                        'sort' => 10,
                        'from' => -3,
                        'to' => 0,
                        'materials' => [
                            $this->rent('Bühnenpratzen 2×1 m', 48, 'Hauptbühne', 'Systempodeste 2×1 m, Höhe 1.2 m, rutschfest.'),
                            $this->rent('Bühnengeländer 2 m', 16, 'Hauptbühne', 'Seitengeländer inkl. Klammern.'),
                            $this->rent('Treppenmodul 1.2 m', 4, 'Hauptbühne', 'Aufgänge links/rechts, geländerseitig.'),
                        ],
                    ],
                    [
                        'name' => 'Delay-Tower Ost',
                        'description' => 'Layher-Turm für Delay-PAs. Anfrage Gerüstbau.',
                        'sort' => 20,
                        'from' => -3,
                        'to' => -1,
                        'materials' => [
                            $this->rent('Layher-Feld 2.57 m', 24, 'Ostwiese', 'Vertikalrahmen + Riegel für 8 m Turm.'),
                            $this->rent('Ballaststeine 25 kg', 40, 'Ostwiese', 'Beschwerung Fussplatten, Windlast.'),
                            $this->rent('Gerüstbelag 0.32 m', 16, 'Ostwiese', 'Arbeitsbühne oben + Zwischenbelag.'),
                        ],
                    ],
                    [
                        'name' => 'FOH-Erweiterung',
                        'description' => 'Zusatzpodest und Dach am FoH. Anfrage Bühnenverleih.',
                        'sort' => 30,
                        'from' => -3,
                        'to' => -1,
                        'materials' => [
                            $this->rent('Podest 2×1 m Höhe 0.6 m', 12, 'FOH', 'Erweiterung Mischpult-Fläche.'),
                            $this->rent('Wetterdach 4×4 m', 1, 'FOH', 'Leichtes Zeltdach über FoH, inkl. Fussplatten.'),
                            $this->rent('Seitenvorhang 4 m', 2, 'FOH', 'Sicht- und Wetterschutz FoH-Seiten.'),
                        ],
                    ],
                    [
                        'name' => 'Lichtbrücke Traversen',
                        'description' => 'Alu-Traversen über der Bühne. Anfrage Licht-/Rigging-Verleih.',
                        'sort' => 40,
                        'from' => -2,
                        'to' => 0,
                        'materials' => [
                            $this->rent('Alu-Traverse 2 m 4-Punkt', 18, 'Hauptbühne', 'Hauptträger Lichtbrücke, schwarz.'),
                            $this->rent('Kettenzug 1 t', 6, 'Hauptbühne', 'Hängepunkte inkl. Anschlagmittel.'),
                            $this->rent('Ground-Support Tower 6 m', 2, 'Hauptbühne', 'Seitliche Stütztürme für Traverse.'),
                        ],
                    ],
                ],
            ],
            [
                'bereich' => 'Sanitär- & Wasserbau',
                'bereich_desc' => 'Sanitärverleih — WCs, Duschen, Grauwasser.',
                'firma' => 'Sanitär',
                'sort' => 130,
                'from' => -2,
                'to' => 3,
                'jobs' => [
                    [
                        'name' => 'WC-Park West',
                        'description' => 'Mobile Toiletten und Waschbecken. Anfrage Sanitärverleih.',
                        'sort' => 10,
                        'from' => -2,
                        'to' => 3,
                        'materials' => [
                            $this->rent('Mobile Toilette Standard', 24, 'WC West', 'Inkl. wöchentlicher Entsorgung.'),
                            $this->rent('Rollstuhl-WC', 2, 'WC West', 'Barrierefrei, extra breit.'),
                            $this->rent('Handwaschbecken 4-er', 4, 'WC West', 'Mit Frisch- und Grauwassertank.'),
                        ],
                    ],
                    [
                        'name' => 'Crew-Duschen',
                        'description' => 'Duschcontainer Backstage. Anfrage Sanitär-/Containerverleih.',
                        'sort' => 20,
                        'from' => -2,
                        'to' => 3,
                        'materials' => [
                            $this->rent('Duschcontainer 20 ft 4 Kabinen', 1, 'Backstage', 'Warmwasser, Dieselboiler, Anschluss CEE 16 A.'),
                            $this->rent('Frischwassertank 1000 l IBC', 2, 'Backstage', 'Zuleitung Duschen, lebensmittelecht.'),
                        ],
                    ],
                    [
                        'name' => 'Grauwasser Hauptbühne',
                        'description' => 'Ableitung Bar und Bühne. Anfrage Sanitärverleih.',
                        'sort' => 30,
                        'from' => -2,
                        'to' => 2,
                        'materials' => [
                            $this->mat('Spiralschlauch 50 mm', 80, 'm', 'Hauptbühne', 'Grauwasserleitung Bar → Sammelgrube.', true, 'can', 'Sanitär-Partner'),
                            $this->rent('Schmutzwasserpumpe 1.5 kW', 1, 'Hauptbühne', 'Tauchpumpe inkl. Schwimmerschalter.'),
                            $this->rent('Sammelgrube 5 m³', 1, 'Technikhof', 'Abfuhr alle 2 Tage einkalkuliert.'),
                        ],
                    ],
                ],
            ],
            [
                'bereich' => 'Energie & Strom',
                'bereich_desc' => 'Elektro / Stromverleih — Verteiler, Aggregat, Wegeleuchten.',
                'firma' => 'Elektro',
                'sort' => 140,
                'from' => -5,
                'to' => 3,
                'jobs' => [
                    [
                        'name' => 'Hauptverteiler CEE',
                        'description' => 'Einspeisung und Unterverteilung. Anfrage Elektroverleih.',
                        'sort' => 10,
                        'from' => -5,
                        'to' => 2,
                        'materials' => [
                            $this->rent('CEE-Verteiler 63 A 12-fach', 4, 'Technikhof', 'Haupt- und Unterverteilung, FI/LS bestückt.'),
                            $this->mat('Gummikabel 5×16 63 A', 120, 'm', 'Technikhof', 'Zuleitungen Bühne / Gastro / Sanitär.', true, 'can', 'Elektro-Partner'),
                            $this->rent('Kabeltrommel 32 A 50 m', 8, 'Technikhof', 'Verlängerungen Sub-Verteiler.'),
                        ],
                    ],
                    [
                        'name' => 'Notstromaggregat',
                        'description' => 'Diesel-Netzersatz 100 kVA. Anfrage Stromverleih.',
                        'sort' => 20,
                        'from' => -4,
                        'to' => 3,
                        'materials' => [
                            $this->rent('Dieselaggregat 100 kVA schallgedämmt', 1, 'Technikhof', 'Inkl. 24-h-Notdienst und Erstbetankung.'),
                            $this->rent('Dieseltank 1000 l doppelwandig', 1, 'Technikhof', 'Nachfüllen alle 36 h einplanen.'),
                            $this->rent('Erdungsset Bau-NEA', 1, 'Technikhof', 'Erder, Klemmen, Messprotokoll.'),
                        ],
                    ],
                    [
                        'name' => 'Wegeleuchten Gelände',
                        'description' => 'Beleuchtung Wege und Notausgänge. Anfrage Elektroverleih.',
                        'sort' => 30,
                        'from' => -3,
                        'to' => 3,
                        'materials' => [
                            $this->rent('LED-Mastleuchte 4 m 50 W', 30, 'Gelände', 'Wegeführung Eingang, Gastro, WC.'),
                            $this->mat('Gummikabel 3×2.5', 200, 'm', 'Gelände', 'Ringleitung Wegeleuchten, IP67 Stecker.', true, 'can', 'Elektro-Partner'),
                            $this->rent('Timer-Schaltuhr Outdoor', 4, 'Gelände', 'Dämmerung + Hand, verteilt auf Ringe.'),
                        ],
                    ],
                ],
            ],
            [
                'bereich' => 'Zelte & Wetterschutz',
                'bereich_desc' => 'Zeltverleih — Catering, Crew, Info-Pagode.',
                'firma' => 'Zelte',
                'sort' => 150,
                'from' => -2,
                'to' => 3,
                'jobs' => [
                    [
                        'name' => 'Catering-Zelt 20×30',
                        'description' => 'Festzelt für Verpflegung. Anfrage Zeltverleih.',
                        'sort' => 10,
                        'from' => -2,
                        'to' => 3,
                        'materials' => [
                            $this->rent('Festzelt 20×30 m', 1, 'Gastro West', 'Inkl. Aufbau/Abbau, Sturmsicherung, Beleuchtung.'),
                            $this->rent('Holzboden-Kassetten 2×1 m', 300, 'Gastro West', 'Ganzer Zeltboden, eben.'),
                            $this->rent('Seitenwände Festzelt 5 m', 20, 'Gastro West', 'Wetter- und Windschutz, 4 Öffnungen.'),
                        ],
                    ],
                    [
                        'name' => 'Crew-Zelt Backstage',
                        'description' => '10×10 m Crew-Bereich. Anfrage Zeltverleih.',
                        'sort' => 20,
                        'from' => -2,
                        'to' => 3,
                        'materials' => [
                            $this->rent('Pagodenzelt 10×10 m', 1, 'Backstage', 'Mit Bodenplane und Beschwerung.'),
                            $this->rent('Stehtische Holz 110 cm', 8, 'Backstage', 'Crew-Verpflegung, klappbar.'),
                        ],
                    ],
                    [
                        'name' => 'Info-Pagode Eingang',
                        'description' => '5×5 m Infostand. Anfrage Zeltverleih.',
                        'sort' => 30,
                        'from' => -2,
                        'to' => 2,
                        'materials' => [
                            $this->rent('Pagode 5×5 m', 1, 'Eingang Nord', 'Mit Volant, weiss, Sturmsicherung.'),
                            $this->rent('Thekentisch 2 m', 2, 'Eingang Nord', 'Info-Theke, zusammenklappbar.'),
                        ],
                    ],
                ],
            ],
            [
                'bereich' => 'Sicherheit & Zaun',
                'bereich_desc' => 'Zaunverleih / Absperrtechnik — Perimeter, Dränggitter, Fluchtwege.',
                'firma' => 'Zaun & Sicherheit',
                'sort' => 160,
                'from' => -3,
                'to' => 3,
                'jobs' => [
                    [
                        'name' => 'Bauzaun Perimeter',
                        'description' => 'Aussenzaun ums Gelände. Anfrage Zaunverleih.',
                        'sort' => 10,
                        'from' => -3,
                        'to' => 3,
                        'materials' => [
                            $this->rent('Bauzaunfeld 3.5×2 m', 180, 'Perimeter', 'Inkl. Füsse und Verbindungsschellen.'),
                            $this->rent('Bauzaun-Tor 2-flügelig 4 m', 4, 'Perimeter', 'Zufahrten Technik, Gastro, Notausgang.'),
                            $this->rent('Sichtschutzvlies 1.8 m', 120, 'm', 'Wind- und Sichtschutz an Publikumsseiten.'),
                        ],
                    ],
                    [
                        'name' => 'Dränggitter Front of Stage',
                        'description' => 'Crowd-Barrier vor der Bühne. Anfrage Absperrtechnik.',
                        'sort' => 20,
                        'from' => -2,
                        'to' => 2,
                        'materials' => [
                            $this->rent('Crowd-Barrier 1.2 m', 40, 'Hauptbühne', 'Mojo-Style, mit Fronttritt, verzahnt.'),
                            $this->rent('Barrier-Ecke 90°', 4, 'Hauptbühne', 'Abschlüsse links/rechts Stage-Pit.'),
                        ],
                    ],
                    [
                        'name' => 'Fluchtweg-Markierung',
                        'description' => 'Notausgänge und Warnbaken. Anfrage Sicherheitsverleih.',
                        'sort' => 30,
                        'from' => -2,
                        'to' => 3,
                        'materials' => [
                            $this->rent('Fluchtwegschild LED 400 mm', 16, 'Gelände', 'Akku, 24 h Notlicht, zweisprachig.'),
                            $this->rent('Warnbake gelb blinkend', 12, 'Gelände', 'Zufahrten und Notausgänge in der Dunkelheit.'),
                            $this->rent('Absperrband rot/weiss 500 m', 6, 'Gelände', 'Temporäre Sperrungen Aufbau.'),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mat(
        string $label,
        int $qty,
        string $unit,
        string $location,
        string $notes,
        bool $return = false,
        ?string $pickup = null,
        ?string $pickupPlace = null,
    ): array {
        return [
            'label' => $label,
            'qty' => $qty,
            'unit' => $unit,
            'location' => $location,
            'notes' => $notes,
            'return' => $return,
            'pickup' => $pickup,
            'pickup_place' => $pickupPlace,
            'timeframe' => 'Aufbau bis Anlassende',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rent(string $label, int $qty, string $location, string $notes, string $unit = 'Stk'): array
    {
        return $this->mat($label, $qty, $unit, $location, $notes, true, 'can', 'Partnerlager');
    }
}
