<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\Group;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Demo\Organisation\ManagedSeedApplier;
use App\Service\Demo\Organisation\ManagedSpec;
use App\Service\Demo\Organisation\SyncReport;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassMailMergeService;
use App\Service\Grossanlass\GrossanlassMapService;
use App\Service\Grossanlass\GrossanlassPackService;
use App\Service\Grossanlass\GrossanlassPlaceService;
use App\Service\Grossanlass\GrossanlassProcurementService;
use App\Service\Grossanlass\GrossanlassUebersichtService;
use App\Service\Media\MediaStorageService;
use App\Util\DemoAccounts;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Event-Prozessdaten des Szenarios `grossanlass-event`: ein nachvollziehbarer Beschaffungsablauf (Bedarf → Anfrage → Offerte mit
 * PDF → Budget → Bestellung → Zusage → Einsatz → Packauftrag), die Geländekarte samt platzierten Orten und die Mail-Vorlagen.
 *
 * Die Abläufe laufen über die bestehenden Dienste (Beschaffung, Zusagen, Einsätze, Packen, Karte), damit Beziehungen und Zustände
 * dieselben sind wie bei der Bedienung der App. Jeder Schritt entsteht genau einmal und wird im Seed-Ledger festgehalten; ein
 * späterer Sync legt nichts doppelt an und überschreibt nichts, was von Hand geändert wurde. Ein gelöschter Schritt wird gemeldet
 * und nicht neu erzeugt (Folgeschritte hängen daran). Neue Codes (QR, öffentliche Pack-Codes) vergibt die App; sie bleiben über
 * den Ledger stabil. Es werden keine Daten des Legacy-Events kopiert.
 */
final class DemoGrossanlassEventProcessSeedService
{
    private const CATEGORY_BAU = 'Bauten, Überdachungen & Raummodule';
    private const CATEGORY_HEBEZEUG = 'Flurförderzeuge & Hebezeuge';
    private const CATEGORY_STROM = 'Energie- & Stromversorgung';
    private const FIXTURE_DIR = __DIR__ . '/../../../data/seeds/dev-demo/files';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassProcurementService $procurement,
        private GrossanlassCommitmentService $commitments,
        private GrossanlassUebersichtService $einsaetze,
        private GrossanlassPackService $packs,
        private GrossanlassMapService $maps,
        private GrossanlassPlaceService $places,
        private GrossanlassMailMergeService $mail,
        private MediaStorageService $mediaStorage,
        private DemoGrossanlassQuotePdf $quotePdf,
        private ManagedSeedApplier $managedApplier,
    ) {
    }

    /**
     * @param array<string, mixed> $mapDef Katalog `grossanlass.map`
     *
     * @return list<string>
     */
    public function expectedSeedKeys(string $scenarioKey, array $mapDef): array
    {
        $keys = [];
        foreach ($this->chains() as $chain) {
            foreach (['bedarf', 'anfrage', 'offerte', 'bestellung', 'zusage'] as $step) {
                if ($this->chainHasStep($chain, $step)) {
                    $keys[] = $scenarioKey . ':ablauf:' . $chain['key'] . ':' . $step;
                }
            }
        }
        $keys[] = $scenarioKey . ':ablauf:entwurf:anfrage';
        foreach ($this->einsatzDefs() as $def) {
            $keys[] = $scenarioKey . ':einsatz:' . $def['key'];
            for ($i = 1; $i <= $def['packs']; ++$i) {
                $keys[] = $scenarioKey . ':pack:' . $def['key'] . ':' . $i;
            }
        }
        if ($mapDef !== []) {
            $keys[] = $scenarioKey . ':karte:gelaende';
            foreach (array_keys($mapDef['placements'] ?? []) as $jobName) {
                $keys[] = $scenarioKey . ':ort-karte:' . DemoGrossanlassEventJobsSeedService::slug((string) $jobName);
            }
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $mapDef
     *
     * @return list<string>
     */
    public function verifyScenario(SeedContext $context, array $mapDef): array
    {
        $violations = [];
        $records = [];
        foreach ($context->records() as $record) {
            $records[$record->getSeedKey()] = $record;
        }
        foreach ($this->expectedSeedKeys($context->scenarioKey(), $mapDef) as $seedKey) {
            $record = $records[$seedKey] ?? null;
            if ($record === null) {
                $violations[] = sprintf('Seed-Eintrag «%s» fehlt (Sync ergänzt ihn).', $seedKey);
            } elseif ($this->find($record->getEntityClass(), $record->getEntityId()) === null) {
                $violations[] = sprintf('Datensatz zu «%s» existiert nicht mehr.', $seedKey);
            }
        }
        // Dateiintegrität: Fixture im Repo (Hash aus dem Katalog) und gespeicherte Kartendatei
        if ($mapDef !== []) {
            $fixture = self::FIXTURE_DIR . '/' . $mapDef['fixture'];
            if (!is_file($fixture)) {
                $violations[] = sprintf('Fixture «%s» fehlt im Repository.', $mapDef['fixture']);
            } elseif (hash_file('sha256', $fixture) !== $mapDef['sha256']) {
                $violations[] = sprintf('Fixture «%s» stimmt nicht mit dem Hash im Katalog überein.', $mapDef['fixture']);
            }
            $mapRecord = $records[$context->scenarioKey() . ':karte:gelaende'] ?? null;
            $map = $mapRecord !== null ? $this->find(DepartmentGrossanlassMap::class, $mapRecord->getEntityId()) : null;
            if ($map instanceof DepartmentGrossanlassMap) {
                $violations = array_merge($violations, $this->verifyStoredFile($context->requireDepartment(), $map, 'Karte', MediaStorageService::CONTEXT_GROSSANLASS_MAP, $map->getImageFilename()));
            }
        }
        foreach ($records as $seedKey => $record) {
            if (str_contains($seedKey, ':ablauf:') && str_ends_with($seedKey, ':offerte')) {
                $quote = $this->find($record->getEntityClass(), $record->getEntityId());
                if ($quote instanceof ActivityGrossanlassProcurementQuote && $quote->getPdfFilename() !== null) {
                    $department = $context->requireDepartment();
                    $path = $this->quotePdfPath($department, $quote);
                    if ($path === null) {
                        $violations[] = sprintf('Offerten-PDF zu «%s» fehlt im Dateispeicher.', $seedKey);
                    }
                }
            }
        }

        return $violations;
    }

    public function syncScenario(SeedContext $context, SyncReport $report, array $mapDef, string $version): void
    {
        $department = $context->requireDepartment();
        $actor = $this->userFor('ga-mw');
        $driver = $this->userFor('ga-lw');
        if (!$actor instanceof User) {
            $report->warnings[] = 'Event-Prozessdaten übersprungen: Demo-Konto ga-mw fehlt.';

            return;
        }
        $created = [];
        foreach ($this->chains() as $chain) {
            $this->syncChain($context, $report, $department, $actor, $chain, $version, $created);
        }
        $this->syncDraftInquiry($context, $report, $department, $version);
        $this->mail->listTemplates($department); // legt die Standard-Mailvorlagen (Anfrage, Präzisieren) an, falls noch nicht vorhanden

        foreach ($this->einsatzDefs() as $def) {
            $this->syncEinsatz($context, $report, $department, $actor, $driver, $def, $version);
        }
        if ($mapDef !== []) {
            $this->syncMap($context, $report, $department, $actor, $mapDef, $version);
        }
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $chain
     * @param array<string, object> $created
     */
    private function syncChain(SeedContext $context, SyncReport $report, Department $department, User $actor, array $chain, string $version, array &$created): void
    {
        $key = $context->scenarioKey() . ':ablauf:' . $chain['key'] . ':';
        $group = $this->jobGroup($context, (string) $chain['job']);
        if (!$group instanceof Group) {
            $report->warnings[] = sprintf('Ablauf «%s» übersprungen: Bauprojekt «%s» fehlt.', $chain['key'], $chain['job']);

            return;
        }

        $this->adoptEarlierLine($context, $report, $key . 'bedarf', (string) ($chain['earlier_key'] ?? ''), $version);
        $line = $this->once($context, $report, $key . 'bedarf', $version, function () use ($department, $actor, $group, $chain): ActivityGrossanlassProcurementLine {
            $row = $this->procurement->createLineDirect($department, $actor, [
                'group_id' => $group->getId(),
                'label' => $chain['line']['label'],
                'quantity' => $chain['line']['quantity'],
                'quantity_unit' => $chain['line']['unit'],
                'location' => $group->getName(),
                'wish_kind' => $chain['line']['kind'],
                'notes' => $chain['line']['notes'],
                'category_id' => $this->categoryId($department, $chain['line']['category']),
                'cost_kind' => $chain['line']['cost_kind'],
            ]);

            return $this->entityManager->find(ActivityGrossanlassProcurementLine::class, $row['id']) ?? throw new \LogicException('Beschaffungszeile nicht gefunden');
        });
        if (!$line instanceof ActivityGrossanlassProcurementLine) {
            return;
        }

        $inquiry = $this->once($context, $report, $key . 'anfrage', $version, fn (): DepartmentGrossanlassInquiry => $this->createInquiry($department, $chain['inquiry'], [$line->getId()]));
        if (!$inquiry instanceof DepartmentGrossanlassInquiry) {
            return;
        }

        $quote = null;
        if ($this->chainHasStep($chain, 'offerte')) {
            $quote = $this->once($context, $report, $key . 'offerte', $version, function () use ($department, $actor, $line, $chain, $inquiry): ActivityGrossanlassProcurementQuote {
                $q = $chain['quote'];
                $data = $this->procurement->createQuote($department, $actor, $line->getId(), [
                    'supplier' => $q['supplier'],
                    'amount_chf' => $q['amount'],
                    'notes' => $q['notes'] . "\nanfrage:" . $inquiry->getId(),
                    'delivery_at' => $q['delivery_at'],
                    'lead_days' => $q['lead_days'],
                    'inbound_mode' => $q['inbound_mode'],
                    'return_needed' => $q['return_needed'],
                    'return_at' => $q['return_at'] ?? null,
                ]);
                $quote = $this->entityManager->find(ActivityGrossanlassProcurementQuote::class, $data['id']) ?? throw new \LogicException('Offerte nicht gefunden');
                if ($q['pdf']) {
                    $this->attachQuotePdf($department, $actor, $line, $quote, $q, (string) $chain['quote_reference']);
                }
                if ($q['selected']) {
                    $this->procurement->selectQuote($department, $actor, $line->getId(), $quote->getId());
                }

                return $quote;
            });
        }

        if ($quote instanceof ActivityGrossanlassProcurementQuote && $this->chainHasStep($chain, 'bestellung')) {
            $order = $chain['order'];
            $this->once($context, $report, $key . 'bestellung', $version, function () use ($department, $actor, $line, $order) {
                $this->procurement->upsertOrder($department, $actor, $line->getId(), [
                    'ordered_at' => $order['ordered_at'],
                    'cost_chf' => $order['cost'],
                    'order_ref' => $order['ref'],
                    'delivery_at' => $order['delivery_at'],
                    'notes' => $order['notes'],
                ]);

                return $this->entityManager->getRepository(\App\Entity\ActivityGrossanlassProcurementOrder::class)->findOneBy(['procurementLineId' => $line->getId()])
                    ?? throw new \LogicException('Bestellung nicht gefunden');
            });
            $this->once($context, $report, $key . 'zusage', $version, fn (): DepartmentGrossanlassCommitment => $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
                ->findOneBy(['departmentId' => $department->getId(), 'procurementLineId' => $line->getId()]) ?? throw new \LogicException('Zusage der Bestellung nicht gefunden'));
        } elseif ($this->chainHasStep($chain, 'zusage')) {
            $this->once($context, $report, $key . 'zusage', $version, function () use ($department, $actor, $inquiry): DepartmentGrossanlassCommitment {
                $row = $this->commitments->ensureFromInquiry($department, $actor, $inquiry->getId());
                $this->entityManager->refresh($inquiry);
                $thread = $inquiry->getThread();
                if ($thread !== []) {
                    $thread[array_key_last($thread)]['at'] = '2027-03-20T10:00:00+00:00';
                    $inquiry->setThread($thread);
                }

                return $this->entityManager->find(DepartmentGrossanlassCommitment::class, $row['id']) ?? throw new \LogicException('Zusage nicht gefunden');
            });
        }
    }

    /**
     * Frühere Seed-Fassungen führten «Schwartenbretter» und «Teleskoplader» als direkte Beschaffungszeilen (`beschaffung:<key>`).
     * Der Ablauf übernimmt diese Zeile (kein Duplikat), statt eine zweite mit gleichem Namen anzulegen.
     */
    private function adoptEarlierLine(SeedContext $context, SyncReport $report, string $seedKey, string $earlierKey, string $version): void
    {
        if ($earlierKey === '' || $context->findRecord($seedKey) !== null) {
            return;
        }
        $earlier = $context->findRecord($context->scenarioKey() . ':beschaffung:' . $earlierKey);
        $line = $earlier !== null ? $this->find(ActivityGrossanlassProcurementLine::class, $earlier->getEntityId()) : null;
        if ($line instanceof ActivityGrossanlassProcurementLine) {
            $context->record($seedKey, $line, (string) $line->getId(), ManagedSeedApplier::hash(['seeded' => true]), $version);
            $this->entityManager->flush(); // der folgende once()-Aufruf muss den neuen Ledger-Eintrag sehen
            ++$report->adopted;
        }
    }

    /**
     * Ledger-Schlüssel früherer Fassungen, die vorhanden bleiben, aber nicht mehr geführt werden (nicht als verwaist melden).
     *
     * @return list<string>
     */
    public function earlierSeedKeys(SeedContext $context): array
    {
        $keys = [];
        foreach (['schwartenbretter', 'teleskoplader'] as $name) {
            foreach (['beschaffung', 'kosten'] as $kind) {
                $key = $context->scenarioKey() . ':' . $kind . ':' . $name;
                if ($context->findRecord($key) !== null) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /** @param array<string, mixed> $q */
    private function attachQuotePdf(Department $department, User $actor, ActivityGrossanlassProcurementLine $line, ActivityGrossanlassProcurementQuote $quote, array $q, string $reference): void
    {
        $binary = $this->quotePdf->render([
            'supplier' => $q['supplier'],
            'reference' => $reference,
            'date' => $q['date'],
            'customer' => $department->getName(),
            'label' => $line->getLabel(),
            'quantity' => $line->getQuantity(),
            'unit' => $line->getQuantityUnit(),
            'amount' => number_format((float) $q['amount'], 2, '.', "'"),
            'delivery' => $q['delivery_label'],
            'note' => $q['notes'],
        ]);
        $tmp = tempnam(sys_get_temp_dir(), 'quote');
        if ($tmp === false || file_put_contents($tmp, $binary) === false) {
            throw new \RuntimeException('Offerten-PDF konnte nicht geschrieben werden');
        }
        try {
            $file = new UploadedFile($tmp, 'offerte-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $reference) ?? 'demo') . '.pdf', 'application/pdf', null, true);
            $this->procurement->uploadQuotePdf($department, $actor, $line->getId(), $quote->getId(), $file);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function syncDraftInquiry(SeedContext $context, SyncReport $report, Department $department, string $version): void
    {
        $this->once($context, $report, $context->scenarioKey() . ':ablauf:entwurf:anfrage', $version, fn (): DepartmentGrossanlassInquiry => $this->createInquiry($department, [
            'name' => 'Elektro Muster AG',
            'email' => 'offerte@elektro-muster.example',
            'place' => 'Sursee',
            'phone' => '041 000 00 33',
            'contact_name' => 'Verkauf Elektro',
            'offering' => 'Stromverteilung, Kabel und Beleuchtung',
            'category' => self::CATEGORY_STROM,
            'status' => DepartmentGrossanlassInquiry::STATUS_ENTWURF,
            'asked_via' => null,
            'thread' => [],
        ], []));
    }

    /**
     * @param array<string, mixed> $def
     * @param list<string>         $lineIds
     */
    private function createInquiry(Department $department, array $def, array $lineIds): DepartmentGrossanlassInquiry
    {
        $inquiry = new DepartmentGrossanlassInquiry();
        $inquiry->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::INQUIRY, DepartmentGrossanlassInquiry::class));
        $inquiry->setDepartment($department);
        $inquiry->setName((string) $def['name']);
        $inquiry->setEmail((string) $def['email']);
        $inquiry->setPlace((string) $def['place']);
        $inquiry->setLatitude(isset($def['latitude']) ? (float) $def['latitude'] : null);
        $inquiry->setLongitude(isset($def['longitude']) ? (float) $def['longitude'] : null);
        $inquiry->setPhone((string) $def['phone']);
        $inquiry->setContactName((string) $def['contact_name']);
        $inquiry->setOffering((string) $def['offering']);
        $inquiry->setNotes('Demo-Firma für die Testumgebung (fiktive Kontaktdaten).');
        $category = $this->categoryId($department, (string) $def['category']);
        $inquiry->setCategoryIds($category !== null ? [$category] : []);
        $inquiry->setLineIds($lineIds);
        $inquiry->setStatus((string) $def['status']);
        if ($def['asked_via'] !== null) {
            $inquiry->setAskedVia((string) $def['asked_via']);
            $inquiry->setAskedAt(new \DateTime((string) $def['asked_at']));
            $inquiry->setAskedLines($def['asked_lines'] ?? []);
        }
        $inquiry->setThread($def['thread']);
        $this->entityManager->persist($inquiry);
        $this->entityManager->flush();

        return $inquiry;
    }

    /** @param array<string, mixed> $def */
    private function syncEinsatz(SeedContext $context, SyncReport $report, Department $department, User $actor, ?User $driver, array $def, string $version): void
    {
        $key = $context->scenarioKey();
        $einsatz = $this->once($context, $report, $key . ':einsatz:' . $def['key'], $version, function () use ($context, $department, $actor, $driver, $def, $key): ?DepartmentGrossanlassEinsatz {
            $data = [
                'kind' => $def['kind'],
                'qty' => $def['qty'],
                'from' => $def['from'],
                'to' => $def['to'],
                'who' => $def['who'],
                'delivery' => $def['delivery'],
            ];
            if ($def['kind'] === DepartmentGrossanlassEinsatz::KIND_EINSATZ) {
                $commitmentRecord = $context->findRecord($key . ':ablauf:' . $def['chain'] . ':zusage');
                if ($commitmentRecord === null || $this->find(DepartmentGrossanlassCommitment::class, $commitmentRecord->getEntityId()) === null) {
                    return null;
                }
                $data['commitment_id'] = $commitmentRecord->getEntityId();
                $data['chauffeur_user_id'] = $driver?->getId();
            }
            if (isset($def['job'])) {
                $group = $this->jobGroup($context, (string) $def['job']);
                $data['group_id'] = $group?->getId();
                $wishRecord = isset($def['wish']) ? $context->findRecord($key . ':wunsch:' . $def['wish']) : null;
                if ($wishRecord !== null && $this->find(ActivityGrossanlassWishLine::class, $wishRecord->getEntityId()) !== null) {
                    $data['wish_line_id'] = $wishRecord->getEntityId();
                }
            }
            $this->einsaetze->createEinsatz($department, $actor, $data);
            $row = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)->findOneBy(['departmentId' => $department->getId(), 'who' => $def['who']]);

            return $row instanceof DepartmentGrossanlassEinsatz ? $row : null;
        });
        if (!$einsatz instanceof DepartmentGrossanlassEinsatz) {
            return;
        }
        // Packaufträge: der Standard-Pack entsteht mit dem Einsatz, weitere über den Packdienst; Codes vergibt die App und bleiben stabil.
        for ($i = 1; $i <= $def['packs']; ++$i) {
            $this->once($context, $report, $key . ':pack:' . $def['key'] . ':' . $i, $version, function () use ($department, $actor, $einsatz): ?DepartmentGrossanlassPack {
                $existing = $this->packRows($einsatz);
                $recorded = $this->recordedPackIds($einsatz);
                foreach ($existing as $pack) {
                    if (!isset($recorded[$pack->getId()])) {
                        return $pack;
                    }
                }
                $this->packs->addPack($department, $actor, $einsatz->getId());
                foreach ($this->packRows($einsatz) as $pack) {
                    if (!isset($recorded[$pack->getId()])) {
                        return $pack;
                    }
                }

                return null;
            });
        }
    }

    /** @return list<DepartmentGrossanlassPack> */
    private function packRows(DepartmentGrossanlassEinsatz $einsatz): array
    {
        return array_values($this->entityManager->getRepository(DepartmentGrossanlassPack::class)->findBy(['einsatzId' => $einsatz->getId()], ['createdAt' => 'ASC', 'id' => 'ASC']));
    }

    /** @return array<string, true> */
    private function recordedPackIds(DepartmentGrossanlassEinsatz $einsatz): array
    {
        $this->entityManager->flush(); // eben gebundene Ledger-Einträge sichtbar machen
        $out = [];
        foreach ($this->entityManager->getRepository(\App\Entity\DemoSeedRecord::class)->findBy(['entityClass' => DepartmentGrossanlassPack::class]) as $record) {
            $out[$record->getEntityId()] = true;
        }

        return $out;
    }

    /** @param array<string, mixed> $mapDef */
    private function syncMap(SeedContext $context, SyncReport $report, Department $department, User $actor, array $mapDef, string $version): void
    {
        $key = $context->scenarioKey();
        $fixture = self::FIXTURE_DIR . '/' . $mapDef['fixture'];
        if (!is_file($fixture) || hash_file('sha256', $fixture) !== $mapDef['sha256']) {
            $report->warnings[] = sprintf('Geländekarte übersprungen: Fixture «%s» fehlt oder stimmt nicht mit dem Katalog-Hash überein.', $mapDef['fixture']);

            return;
        }
        $map = $this->once($context, $report, $key . ':karte:gelaende', $version, function () use ($department, $actor, $mapDef, $fixture): DepartmentGrossanlassMap {
            $bounds = ['bounds_north' => $mapDef['bounds']['north'], 'bounds_south' => $mapDef['bounds']['south'], 'bounds_east' => $mapDef['bounds']['east'], 'bounds_west' => $mapDef['bounds']['west']];
            $created = $this->maps->create($department, $actor, ['name' => $mapDef['name']] + $bounds);
            $mapId = (string) $created['id'];
            $tmp = tempnam(sys_get_temp_dir(), 'map');
            if ($tmp === false || !copy($fixture, $tmp)) {
                throw new \RuntimeException('Kartenfixture konnte nicht kopiert werden');
            }
            try {
                $this->maps->uploadBackground($department, $actor, $mapId, new UploadedFile($tmp, (string) $mapDef['fixture'], 'image/webp', null, true), $bounds);
            } finally {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
            $this->maps->update($department, $actor, $mapId, ['overlay_opacity' => $mapDef['overlayOpacity']]);

            return $this->entityManager->find(DepartmentGrossanlassMap::class, $mapId) ?? throw new \LogicException('Karte nicht gefunden');
        });
        if (!$map instanceof DepartmentGrossanlassMap) {
            return;
        }
        // Platzierung der Orte auf der Karte (Dreiwege-Vergleich über den Ledger); Koordinaten leitet der Ortsdienst aus den Grenzen ab.
        foreach ($mapDef['placements'] as $jobName => [$x, $y]) {
            $slug = DemoGrossanlassEventJobsSeedService::slug((string) $jobName);
            $placeRecord = $context->findRecord($key . ':ort:' . $slug);
            $place = $placeRecord !== null ? $this->find(DepartmentGrossanlassPlace::class, $placeRecord->getEntityId()) : null;
            if (!$place instanceof DepartmentGrossanlassPlace) {
                continue;
            }
            $desired = ['map_id' => $map->getId(), 'map_x' => round((float) $x, 4), 'map_y' => round((float) $y, 4)];
            $this->applyPlacement($context, $report, $key . ':ort-karte:' . $slug, $place, $desired, $version);
        }
        $this->places->syncDepartmentPlacesToMap($department, $map);
    }

    /** @param array<string, mixed> $desired */
    private function applyPlacement(SeedContext $context, SyncReport $report, string $seedKey, DepartmentGrossanlassPlace $place, array $desired, string $version): void
    {
        $this->managedApplier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: static fn (): object => throw new \LogicException('Der Ort wird mit dem Bauprojekt angelegt.'),
            find: fn (string $id): ?object => $this->entityManager->find(DepartmentGrossanlassPlace::class, $id),
            idOf: static fn (object $p): string => (string) $p->getId(),
            read: static fn (object $p): array => ['map_id' => $p->getMapId(), 'map_x' => $p->getMapX() !== null ? round((float) $p->getMapX(), 4) : null, 'map_y' => $p->getMapY() !== null ? round((float) $p->getMapY(), 4) : null],
            write: function (object $p, array $d): void {
                $p->setMap($this->entityManager->find(DepartmentGrossanlassMap::class, $d['map_id']));
                $p->setMapX($d['map_x']);
                $p->setMapY($d['map_y']);
            },
            desired: $desired,
            adopt: static fn (): object => $place,
        ), $version);
    }

    /** @return list<string> */
    private function verifyStoredFile(Department $department, object $row, string $label, string $context, ?string $filename): array
    {
        if ($filename === null || $filename === '') {
            return [sprintf('%s hat keine Bilddatei.', $label)];
        }
        try {
            $this->mediaStorage->resolveStoredFilePath($context, (string) $department->getId(), (string) $row->getId(), $filename);
        } catch (\Throwable) {
            return [sprintf('%s: Datei «%s» fehlt im Dateispeicher.', $label, $filename)];
        }

        return [];
    }

    private function quotePdfPath(Department $department, ActivityGrossanlassProcurementQuote $quote): ?string
    {
        try {
            return $this->mediaStorage->resolveStoredFilePath(MediaStorageService::CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE, (string) $department->getId(), $quote->getId(), (string) $quote->getPdfFilename());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Genau einmal ausführen und im Ledger festhalten. Existiert der Ledger-Eintrag, aber nicht mehr der Datensatz, wird das
     * gemeldet und nichts neu erzeugt.
     */
    private function once(SeedContext $context, SyncReport $report, string $seedKey, string $version, callable $create): ?object
    {
        $record = $context->findRecord($seedKey);
        if ($record !== null) {
            $entity = $this->find($record->getEntityClass(), $record->getEntityId());
            if ($entity === null) {
                $report->warnings[] = sprintf('«%s» wurde gelöscht und wird nicht neu angelegt (Folgeschritte hängen daran).', $seedKey);
            } else {
                ++$report->unchanged;
            }

            return $entity;
        }
        $entity = $create();
        if ($entity === null) {
            return null;
        }
        $id = method_exists($entity, 'getId') ? (string) $entity->getId() : '';
        $context->record($seedKey, $entity, $id, ManagedSeedApplier::hash(['seeded' => true]), $version);
        ++$report->created;

        return $entity;
    }

    private function find(string $class, string $id): ?object
    {
        return $this->entityManager->find($class, $id);
    }

    private function jobGroup(SeedContext $context, string $jobName): ?Group
    {
        $record = $context->findRecord($context->scenarioKey() . ':bauprojekt:' . DemoGrossanlassEventJobsSeedService::slug($jobName));
        $group = $record !== null ? $this->find(Group::class, $record->getEntityId()) : null;

        return $group instanceof Group ? $group : null;
    }

    private function categoryId(Department $department, string $name): ?string
    {
        $category = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)->findOneBy(['departmentId' => $department->getId(), 'name' => $name]);

        return $category instanceof ActivityGrossanlassProcurementCategory ? $category->getId() : null;
    }

    private function userFor(string $account): ?User
    {
        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy(['email' => DemoAccounts::email($account)]);
        $user = $profile instanceof Profile ? $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]) : null;

        return $user instanceof User ? $user : null;
    }

    /** @param array<string, mixed> $chain */
    private function chainHasStep(array $chain, string $step): bool
    {
        return match ($step) {
            'offerte' => isset($chain['quote']),
            'bestellung' => isset($chain['order']),
            'zusage' => isset($chain['order']) || ($chain['zusage'] ?? false) === true,
            default => true,
        };
    }

    /**
     * Beschaffungsketten mit fiktiven Demo-Firmen (nur Beispieladressen, keine Personen). Daten fest in der Vorbereitungs- und
     * Beschaffungsphase des Zeitplans 2027 (Aufbau ab 19.05.).
     *
     * @return list<array<string, mixed>>
     */
    private function chains(): array
    {
        return [
            [
                'key' => 'holz',
                'earlier_key' => 'schwartenbretter',
                'job' => 'Ticket-Häuschen Eingang',
                'quote_reference' => 'HH-2027-014',
                'line' => ['label' => 'Schwartenbretter', 'quantity' => 30, 'unit' => 'Stk', 'kind' => 'material', 'category' => self::CATEGORY_BAU, 'cost_kind' => 'purchase', 'notes' => 'Fassadenverkleidung, Restposten.'],
                'inquiry' => [
                    'name' => 'Häberli Holz', 'email' => 'verkauf@haeberli-holz.example', 'place' => 'Ruswil', 'latitude' => 47.1003743, 'longitude' => 8.1413014, 'phone' => '041 000 00 11', 'contact_name' => 'Verkauf Holz',
                    'offering' => 'Schnittholz, Bretter und Konstruktionsholz', 'category' => self::CATEGORY_BAU, 'status' => DepartmentGrossanlassInquiry::STATUS_ANTWORT,
                    'asked_via' => 'mail', 'asked_at' => '2027-03-02 09:15:00', 'asked_lines' => [],
                    'thread' => [
                        ['who' => 'ok', 'via' => 'mail', 'text' => 'Mail: Anfrage Material & Logistik gesendet.', 'at' => '2027-03-02T09:15:00+00:00'],
                        ['who' => 'firm', 'via' => 'mail', 'text' => 'Offerte HH-2027-014 als PDF erhalten: 30 Schwartenbretter, Abholung ab Lager.', 'at' => '2027-03-04T14:20:00+00:00'],
                    ],
                ],
                'quote' => [
                    'supplier' => 'Häberli Holz', 'amount' => '220.00', 'notes' => 'Offerte HH-2027-014, Abholung ab Lager Ruswil.', 'date' => '04.03.2027',
                    'delivery_at' => '2027-05-19 12:00:00', 'delivery_label' => 'Abholung ab Lager am 19.05.2027, 12:00 Uhr', 'lead_days' => 14,
                    'inbound_mode' => 'pickup', 'return_needed' => false, 'pdf' => true, 'selected' => true,
                ],
                'order' => ['ordered_at' => '2027-04-12 12:00:00', 'cost' => '220.00', 'ref' => 'B-2027-014', 'delivery_at' => '2027-05-19 12:00:00', 'notes' => 'Abholung auf Palette, Lieferschein beilegen.'],
            ],
            [
                'key' => 'lader',
                'earlier_key' => 'teleskoplader',
                'job' => 'Hauptbühnen-Podest',
                'quote_reference' => 'ML-2027-031',
                'zusage' => true,
                'line' => ['label' => 'Teleskoplader', 'quantity' => 1, 'unit' => 'Stk', 'kind' => 'fahrzeug', 'category' => self::CATEGORY_HEBEZEUG, 'cost_kind' => 'rental', 'notes' => 'Für Montage der Podest-Elemente und der Lichtbrücke.'],
                'inquiry' => [
                    'name' => 'Maki Landtechnik', 'email' => 'vermietung@maki-landtechnik.example', 'place' => 'Sursee', 'phone' => '041 000 00 22', 'contact_name' => 'Vermietung',
                    'offering' => 'Miete von Teleskopladern und Traktoren', 'category' => self::CATEGORY_HEBEZEUG, 'status' => DepartmentGrossanlassInquiry::STATUS_ANTWORT,
                    'asked_via' => 'mail', 'asked_at' => '2027-03-10 10:00:00', 'asked_lines' => [],
                    'thread' => [
                        ['who' => 'ok', 'via' => 'mail', 'text' => 'Mail: Anfrage Teleskoplader (31.05.–09.06.2027) gesendet.', 'at' => '2027-03-10T10:00:00+00:00'],
                        ['who' => 'firm', 'via' => 'mail', 'text' => 'Zusage: Teleskoplader steht am 31.05. bereit. Offerte ML-2027-031 im Anhang.', 'at' => '2027-03-12T08:45:00+00:00'],
                    ],
                ],
                'quote' => [
                    'supplier' => 'Maki Landtechnik', 'amount' => '950.00', 'notes' => 'Offerte ML-2027-031, Miete 10 Tage inkl. Anlieferung.', 'date' => '12.03.2027',
                    'delivery_at' => '2027-05-31 07:00:00', 'delivery_label' => 'Anlieferung am 31.05.2027, 07:00 Uhr, Rückholung am 09.06.2027', 'lead_days' => 7,
                    'inbound_mode' => 'delivery', 'return_needed' => true, 'return_at' => '2027-06-09 17:00:00', 'pdf' => true, 'selected' => true,
                ],
            ],
            [
                'key' => 'naegel',
                'job' => 'Ticket-Häuschen Eingang',
                'quote_reference' => 'HH-2027-015',
                'line' => ['label' => 'Nägel 5x80', 'quantity' => 2000, 'unit' => 'Stk', 'kind' => 'material', 'category' => self::CATEGORY_BAU, 'cost_kind' => 'purchase', 'notes' => 'Für Ständerwerk und Dach.'],
                'inquiry' => [
                    'name' => 'Eisenwaren Beispiel', 'email' => 'info@eisenwaren-beispiel.example', 'place' => 'Sursee', 'phone' => '041 000 00 44', 'contact_name' => 'Verkauf',
                    'offering' => 'Befestigungsmaterial', 'category' => self::CATEGORY_BAU, 'status' => DepartmentGrossanlassInquiry::STATUS_GESENDET,
                    'asked_via' => 'phone', 'asked_at' => '2027-03-16 11:30:00', 'asked_lines' => [],
                    'thread' => [
                        ['who' => 'ok', 'via' => 'phone', 'deal' => 'purchase', 'text' => 'Anruf: 2000 Stk Nägel 5x80, Kauf, 30 CHF.', 'at' => '2027-03-16T11:30:00+00:00'],
                    ],
                ],
                'quote' => [
                    'supplier' => 'Eisenwaren Beispiel', 'amount' => '30.00', 'notes' => 'Telefonische Auskunft: 2000 Stk, Grösse 5x80.', 'date' => '16.03.2027',
                    'delivery_at' => '2027-05-18 12:00:00', 'delivery_label' => 'Abholung nach Absprache', 'lead_days' => 3,
                    'inbound_mode' => 'pickup', 'return_needed' => false, 'pdf' => false, 'selected' => false,
                ],
            ],
        ];
    }

    /**
     * Einsätze zum Beschaffungsablauf: Abholung der bestellten Ware (Fahrauftrag) und die Zuteilung von Material an ein Bauprojekt.
     *
     * @return list<array<string, mixed>>
     */
    private function einsatzDefs(): array
    {
        return [
            ['key' => 'abholung-holz', 'chain' => 'holz', 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ, 'qty' => 30, 'from' => '2027-05-19 12:00:00', 'to' => '2027-05-19 13:00:00', 'who' => 'Abholung Häberli Holz', 'delivery' => 'pickup', 'packs' => 1],
            ['key' => 'anlieferung-lader', 'chain' => 'lader', 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ, 'qty' => 1, 'from' => '2027-05-31 07:00:00', 'to' => '2027-05-31 08:30:00', 'who' => 'Anlieferung Teleskoplader Maki Landtechnik', 'delivery' => 'delivery', 'packs' => 1],
            ['key' => 'zuteilung-staenderholz', 'kind' => DepartmentGrossanlassEinsatz::KIND_ORDER, 'qty' => 40, 'from' => '2027-05-20 08:00:00', 'to' => '2027-06-02 18:00:00', 'who' => 'KVH Ständerholz für das Ticket-Häuschen', 'delivery' => 'pickup', 'job' => 'Ticket-Häuschen Eingang', 'wish' => 'ticket-haeuschen-eingang:kvh-60x80-staenderholz', 'packs' => 1],
            ['key' => 'zuteilung-osb', 'kind' => DepartmentGrossanlassEinsatz::KIND_ORDER, 'qty' => 24, 'from' => '2027-05-20 08:00:00', 'to' => '2027-06-02 18:00:00', 'who' => 'OSB-Platten für das Ticket-Häuschen', 'delivery' => 'pickup', 'job' => 'Ticket-Häuschen Eingang', 'wish' => 'ticket-haeuschen-eingang:osb-18-mm-platten', 'packs' => 1],
        ];
    }
}
