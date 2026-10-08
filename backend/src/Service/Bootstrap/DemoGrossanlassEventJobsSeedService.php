<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassRoundForm;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\ActivityGrossanlassWishResponse;
use App\Entity\Department;
use App\Entity\Group;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassMaterialStage;
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

                $validFrom = $this->dateFromOffset($eventStart, (int) $jobDef['from'])->setTime(8, 0);
                $validTo = $this->dateFromOffset($eventStart, (int) $jobDef['to'])->setTime(18, 0);

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
                'bereich' => 'Sanitär & Wasser',
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
