<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\DepartmentGrossanlassTaskStep;
use App\Entity\DepartmentGrossanlassTour;
use App\Entity\DepartmentGrossanlassVehicleNeed;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\Group;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Demo\DemoStableCodes;
use App\Service\Demo\Organisation\ManagedSeedApplier;
use App\Service\Demo\Organisation\ManagedSpec;
use App\Service\Demo\Organisation\SyncReport;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassMailMergeService;
use App\Service\Grossanlass\GrossanlassMapService;
use App\Service\Grossanlass\GrossanlassDispatchService;
use App\Service\Grossanlass\GrossanlassPackService;
use App\Service\Grossanlass\GrossanlassPlaceCodes;
use App\Service\Grossanlass\GrossanlassPlaceService;
use App\Service\Grossanlass\GrossanlassProcurementService;
use App\Service\Grossanlass\GrossanlassTaskWorkflowService;
use App\Service\Grossanlass\GrossanlassWorkshopCaseService;
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
    private const CATEGORY_TRANSPORT = 'Logistik- und Transportfahrzeuge (Strasse)';
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
        private GrossanlassDispatchService $dispatch,
        private GrossanlassTaskWorkflowService $taskWorkflow,
        private GrossanlassWorkshopCaseService $workshopCases,
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
        foreach ($this->procurementFlows() as $flow) {
            foreach ($flow['steps'] as $step) {
                $keys[] = $scenarioKey . ':beschaffung:' . $flow['key'] . ':' . $step['key'];
            }
        }
        $keys[] = $scenarioKey . ':ablauf:entwurf:anfrage';
        foreach ($this->einsatzDefs() as $def) {
            $keys[] = $scenarioKey . ':einsatz:' . $def['key'];
            for ($i = 1; $i <= $def['packs']; ++$i) {
                $keys[] = $scenarioKey . ':pack:' . $def['key'] . ':' . $i;
            }
        }
        $keys[] = $scenarioKey . ':tour:anlieferung-lader';
        $keys[] = $scenarioKey . ':tour:materialabholung-aufbau';
        $keys[] = $scenarioKey . ':tour:verteilung-kantholz';
        $keys[] = $scenarioKey . ':freigabe:bauzaun-perimeter';
        foreach (['festbank-leihe', 'holzschnitzel-spende'] as $chargeKey) {
            $keys[] = $scenarioKey . ':charge-binding:' . $chargeKey;
        }
        $keys[] = $scenarioKey . ':einsatz:rampe-material-liefern';
        foreach (['nachschub-getraenke', 'tresen-retour', 'lader-rueckgabe'] as $need) {
            $keys[] = $scenarioKey . ':einsatz:' . $need;
        }
        $keys[] = $scenarioKey . ':tour:nachschub-getraenke';
        $keys[] = $scenarioKey . ':tour:lader-rueckgabe';
        $keys[] = $scenarioKey . ':werkstatt:zeltboden';
        $keys[] = $scenarioKey . ':werkstatt:rampengelaender';
        foreach ($this->taskSteps() as $taskKey => $labels) {
            foreach (array_keys($labels) as $i) {
                $keys[] = $scenarioKey . ':aufgabe:' . $taskKey . ':schritt:' . ($i + 1);
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
        $this->syncProcurementFlows($context, $report, $department, $actor, $version);
        $this->syncDraftInquiry($context, $report, $department, $version);
        $this->mail->listTemplates($department); // legt die Standard-Mailvorlagen (Anfrage, Präzisieren) an, falls noch nicht vorhanden

        foreach ($this->einsatzDefs() as $def) {
            if (!empty($def['late'])) {
                continue; // braucht Chargen aus dem Logistik-Seed, läuft in syncReadyTransport
            }
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
        foreach ((array) ($chain['vehicle_need'] ?? []) as $needKey) {
            $this->linkVehicleNeed($context, (string) $needKey, $line);
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

    /** Verknüpft den Fahrzeugwunsch des Katalogs mit der Beschaffungslinie (nur wenn noch keine gesetzt ist; von Hand Gesetztes bleibt). */
    private function linkVehicleNeed(SeedContext $context, string $needKey, ActivityGrossanlassProcurementLine $line): void
    {
        $record = $context->findRecord($context->scenarioKey() . ':fahrzeugbedarf:' . $needKey);
        $need = $record !== null ? $this->find(\App\Entity\DepartmentGrossanlassVehicleNeed::class, $record->getEntityId()) : null;
        if ($need instanceof \App\Entity\DepartmentGrossanlassVehicleNeed && $need->getProcurementLineId() === null) {
            $need->setProcurementLine($line);
            $this->entityManager->flush();
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
        if ($context->findRecord($context->scenarioKey() . ':tour:abholung-holz') !== null) {
            $keys[] = $context->scenarioKey() . ':tour:abholung-holz';
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
                if (isset($def['commitment_seed'])) {
                    $commitmentRecord = $context->findRecord($key . ':' . $def['commitment_seed']);
                } else {
                    $commitmentRecord = $context->findRecord($key . ':ablauf:' . $def['chain'] . ':zusage');
                }
                if ($commitmentRecord === null || $this->find(DepartmentGrossanlassCommitment::class, $commitmentRecord->getEntityId()) === null) {
                    return null;
                }
                $data['commitment_id'] = $commitmentRecord->getEntityId();
                $data['chauffeur_user_id'] = isset($def['chauffeur']) ? $this->userFor((string) $def['chauffeur'])?->getId() : $driver?->getId();
            }
            if (isset($def['destination'])) {
                $data['destination_place_id'] = $this->resolveDestinationPlaceId($department, (string) $def['destination']);
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
        // Packaufträge: der Standard-Pack entsteht mit dem Einsatz, weitere über den Packdienst. Der öffentliche QR-Code ergibt sich aus
        // dem Seed-Schlüssel und bleibt damit auch nach einem Reset derselbe.
        for ($i = 1; $i <= $def['packs']; ++$i) {
            $packKey = $key . ':pack:' . $def['key'] . ':' . $i;
            $this->once($context, $report, $packKey, $version, function () use ($department, $actor, $einsatz, $key, $packKey): ?DepartmentGrossanlassPack {
                $pack = $this->nextUnrecordedPack($department, $actor, $einsatz);
                if ($pack instanceof DepartmentGrossanlassPack) {
                    $code = DemoStableCodes::prefixed(GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::PACK), $key, $packKey);
                    if ($this->entityManager->getRepository(DepartmentGrossanlassPack::class)->findOneBy(['publicCode' => $code]) === null) {
                        $pack->setPublicCode($code);
                    }
                }

                return $pack;
            });
        }
    }

    /**
     * Disposition der Teleskoplader-Anlieferung: bestätigte Tour (Fahrzeug, Chauffeur) und zur Ausführung freigegebener
     * Fahrauftrag. Die Abholung «Holz» bleibt bewusst offen (nicht freigegeben); unterwegs ist nichts, abgeliefert wird nach
     * dem Zeitsprung selbst (QR oder begründete Ausnahme).
     */
    public function syncDisposition(SeedContext $context, SyncReport $report, string $version): void
    {
        $department = $context->requireDepartment();
        $actor = $this->userFor('ga-mw');
        $driver = $this->userFor('ga-lw');
        if (!$actor instanceof User) {
            return;
        }
        $key = $context->scenarioKey();
        $einsatzRecord = $context->findRecord($key . ':einsatz:anlieferung-lader');
        if ($einsatzRecord === null) {
            return;
        }
        $einsatzId = $einsatzRecord->getEntityId();
        $this->once($context, $report, $key . ':tour:anlieferung-lader', $version, function () use ($department, $actor, $driver, $einsatzId): ?DepartmentGrossanlassTour {
            $einsatz = $this->find(DepartmentGrossanlassEinsatz::class, $einsatzId);
            if (!$einsatz instanceof DepartmentGrossanlassEinsatz) {
                return null;
            }
            $vehicle = $einsatz->getCommitment();
            $payload = $this->dispatch->createTour($department, $actor, [
                'name' => 'Anlieferung Teleskoplader',
                'need_ids' => [$einsatzId],
                'vehicle_id' => $vehicle?->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE ? $vehicle->getId() : null,
                'chauffeur_user_id' => $driver?->getId(),
            ]);
            $tour = $this->find(DepartmentGrossanlassTour::class, (string) $payload['id']);
            if ($vehicle !== null && $driver !== null && $einsatz->getDestinationPlaceId() !== null) {
                $this->einsaetze->updateEinsatz($department, $actor, $einsatzId, ['packed' => true]);
                $this->dispatch->confirmTour($department, $actor, (string) $payload['id']);
                $this->dispatch->releaseNeed($department, $actor, $einsatzId);
            }

            return $tour;
        });
    }

    /**
     * Tour «Materialabholung Aufbau»: Meier (Bitumen) und Häberli (Schwartenbretter) auf dem Lieferwagen, Ziel Lagerplatz.
     * Bestätigt, bewusst noch nicht freigegeben (Disposition vor Ausführung).
     */
    public function syncPlannedPickup(SeedContext $context, SyncReport $report, string $version): void
    {
        $department = $context->requireDepartment();
        $actor = $this->userFor('ga-mw');
        $driver = $this->userFor('ga-lw');
        $key = $context->scenarioKey();
        $holzRecord = $context->findRecord($key . ':einsatz:abholung-holz');
        $bitumenRecord = $context->findRecord($key . ':einsatz:abholung-bitumen');
        $vehicleRecord = $context->findRecord($key . ':ablauf:lieferwagen:zusage');
        if (!$actor instanceof User || !$driver instanceof User || $holzRecord === null || $bitumenRecord === null || $vehicleRecord === null) {
            return;
        }
        $needIds = [$bitumenRecord->getEntityId(), $holzRecord->getEntityId()];
        $vehicleId = $vehicleRecord->getEntityId();
        $this->once($context, $report, $key . ':tour:materialabholung-aufbau', $version, function () use ($department, $actor, $driver, $needIds, $vehicleId): ?DepartmentGrossanlassTour {
            foreach ($needIds as $needId) {
                if ($this->find(DepartmentGrossanlassEinsatz::class, $needId) === null) {
                    return null;
                }
            }
            if ($this->find(DepartmentGrossanlassCommitment::class, $vehicleId) === null) {
                return null;
            }
            $payload = $this->dispatch->createTour($department, $actor, [
                'name' => 'Materialabholung Aufbau',
                'need_ids' => $needIds,
                'vehicle_id' => $vehicleId,
                'chauffeur_user_id' => $driver->getId(),
            ]);
            $this->dispatch->confirmTour($department, $actor, (string) $payload['id']);

            return $this->find(DepartmentGrossanlassTour::class, (string) $payload['id']);
        });
    }

    /**
     * Logistik-Chargen (Festbank-Leihe, Holzschnitzel-Spende) mit Bedarf, Anfrage und verbindlicher Zusage verknüpfen.
     * Bestehende Chargen und QR-Codes bleiben unverändert.
     */
    /**
     * Frühere Demo-Seeds legten Abhol-/Anliefer-Einsätze mit delivery «pickup» an; die Disposition liest nur «trip».
     */
    public function repairTripDeliveryForDispositionEinsaetze(SeedContext $context): void
    {
        $department = $context->department();
        if (!$department instanceof Department) {
            return;
        }
        $key = $context->scenarioKey();
        foreach (['abholung-holz', 'abholung-bitumen', 'anlieferung-lader', 'verteilung-kantholz'] as $einsatzKey) {
            $record = $context->findRecord($key . ':einsatz:' . $einsatzKey);
            if ($record === null) {
                continue;
            }
            $einsatz = $this->find(DepartmentGrossanlassEinsatz::class, $record->getEntityId());
            if ($einsatz instanceof DepartmentGrossanlassEinsatz && $einsatz->getDelivery() !== DepartmentGrossanlassEinsatz::DELIVERY_TRIP) {
                $einsatz->setDelivery(DepartmentGrossanlassEinsatz::DELIVERY_TRIP);
            }
        }
        $this->entityManager->flush();
    }

    /** Setzt fehlende Zielorte aus den Einsatz-Definitionen (z. B. Hauptbühne, Lagerplatz). */
    public function repairEinsatzDestinationsFromSeed(SeedContext $context): void
    {
        $department = $context->department();
        if (!$department instanceof Department) {
            return;
        }
        $key = $context->scenarioKey();
        foreach ($this->einsatzDefs() as $def) {
            if (!isset($def['destination'])) {
                continue;
            }
            $record = $context->findRecord($key . ':einsatz:' . $def['key']);
            if ($record === null) {
                continue;
            }
            $einsatz = $this->find(DepartmentGrossanlassEinsatz::class, $record->getEntityId());
            if (!$einsatz instanceof DepartmentGrossanlassEinsatz || $einsatz->getDestinationPlaceId() !== null) {
                continue;
            }
            $placeId = $this->resolveDestinationPlaceId($department, (string) $def['destination']);
            if ($placeId !== null) {
                $einsatz->setDestinationPlaceId($placeId);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Zusagen aus Anfragen erhalten standardmässig «pickup» in item_details; die Demo-Offerten definieren teils «delivery»
     * (z. B. Teleskoplader-Anlieferung). Idempotent nachziehen, ohne Buchungen.
     */
    public function repairCommitmentInboundModesFromChains(SeedContext $context): void
    {
        $department = $context->department();
        if (!$department instanceof Department) {
            return;
        }
        $prefix = $context->scenarioKey() . ':ablauf:';
        foreach ($this->chains() as $chain) {
            $expected = $chain['quote']['inbound_mode'] ?? null;
            if (!is_string($expected) || $expected === '') {
                continue;
            }
            $lineRecord = $context->findRecord($prefix . $chain['key'] . ':bedarf');
            if ($lineRecord === null) {
                continue;
            }
            $line = $this->find(ActivityGrossanlassProcurementLine::class, $lineRecord->getEntityId());
            if (!$line instanceof ActivityGrossanlassProcurementLine) {
                continue;
            }
            $commitment = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->findOneBy([
                'departmentId' => $department->getId(),
                'procurementLineId' => $line->getId(),
            ]);
            if (!$commitment instanceof DepartmentGrossanlassCommitment) {
                continue;
            }
            $details = $commitment->getItemDetails();
            if (($details['inbound_mode'] ?? null) === $expected) {
                continue;
            }
            $details['inbound_mode'] = $expected;
            $commitment->setItemDetails($details);
        }
        $this->entityManager->flush();
    }

    /**
     * Teleskoplader-Tour: nach gesetztem Zielort bestätigen und Fahrt freigeben (wie {@see syncDisposition()},
     * idempotent wenn der Ledger-Eintrag die Tour schon angelegt hat).
     */
    public function repairAnlieferungLaderDisposition(SeedContext $context): void
    {
        $department = $context->requireDepartment();
        $actor = $this->userFor('ga-mw');
        $driver = $this->userFor('ga-lw');
        if (!$actor instanceof User || !$driver instanceof User) {
            return;
        }
        $key = $context->scenarioKey();
        $einsatzRecord = $context->findRecord($key . ':einsatz:anlieferung-lader');
        $tourRecord = $context->findRecord($key . ':tour:anlieferung-lader');
        if ($einsatzRecord === null || $tourRecord === null) {
            return;
        }
        $einsatz = $this->find(DepartmentGrossanlassEinsatz::class, $einsatzRecord->getEntityId());
        $tour = $this->find(DepartmentGrossanlassTour::class, $tourRecord->getEntityId());
        if (!$einsatz instanceof DepartmentGrossanlassEinsatz || !$tour instanceof DepartmentGrossanlassTour) {
            return;
        }
        if ($einsatz->getDestinationPlaceId() === null) {
            return;
        }
        $vehicle = $einsatz->getCommitment();
        if ($vehicle === null || $vehicle->getFamily() !== DepartmentGrossanlassCommitment::FAMILY_VEHICLE) {
            return;
        }
        if (!$tour->isConfirmed()) {
            $this->dispatch->confirmTour($department, $actor, $tour->getId());
        }
        if (!$einsatz->isPacked()) {
            $this->einsaetze->updateEinsatz($department, $actor, $einsatz->getId(), ['packed' => true]);
        }
        if (!$einsatz->isReleasedForExecution()) {
            $this->dispatch->releaseNeed($department, $actor, $einsatz->getId());
        }
        $this->entityManager->flush();
    }

    public function syncChargeProcurementBindings(SeedContext $context, SyncReport $report, string $version): void
    {
        $department = $context->requireDepartment();
        $actor = $this->userFor('ga-mw');
        if (!$actor instanceof User) {
            return;
        }
        $key = $context->scenarioKey();
        $bindings = [
            [
                'charge' => 'festbank-leihe',
                'line' => ['label' => 'Festbankgarnituren', 'quantity' => 40, 'unit' => 'Stk', 'kind' => 'material', 'category' => self::CATEGORY_BAU, 'cost_kind' => 'loan', 'notes' => 'Leihgabe für die Festwirtschaft.'],
                'inquiry' => [
                    'name' => 'Zeltverleih Beispiel', 'email' => 'info@zeltverleih-beispiel.example', 'place' => 'Sursee', 'phone' => '041 000 00 77', 'contact_name' => 'Vermietung',
                    'offering' => 'Festzelte, Bänke und Garnituren', 'category' => self::CATEGORY_BAU,
                    'thread' => [
                        ['who' => 'ok', 'via' => 'mail', 'text' => 'Mail: Anfrage Festbankgarnituren (02.–08.06.2027) gesendet.', 'at' => '2027-03-05T09:00:00+00:00'],
                        ['who' => 'firm', 'via' => 'mail', 'text' => 'Verbindliche Zusage: 40 Garnituren, Abholung am 02.06.', 'at' => '2027-03-06T10:30:00+00:00'],
                    ],
                ],
                'origin' => DepartmentGrossanlassCommitment::ORIGIN_LOAN,
                'inbound_mode' => 'pickup',
            ],
            [
                'charge' => 'holzschnitzel-spende',
                'line' => ['label' => 'Holzschnitzel (Sackware)', 'quantity' => 25, 'unit' => 'Stk', 'kind' => 'material', 'category' => self::CATEGORY_BAU, 'cost_kind' => 'ancillary', 'notes' => 'Sponsoring für Wege und Flächen (Spendenzusage, keine Kostenposition).'],
                'inquiry' => [
                    'name' => 'Sponsoring Beispiel AG', 'email' => 'sponsoring@beispiel-ag.example', 'place' => 'Sursee', 'phone' => '041 000 00 88', 'contact_name' => 'Sponsoring',
                    'offering' => 'Sachspenden und Patenschaften', 'category' => self::CATEGORY_BAU,
                    'thread' => [
                        ['who' => 'ok', 'via' => 'mail', 'text' => 'Mail: Anfrage Holzschnitzel-Sponsoring gesendet.', 'at' => '2027-03-08T09:00:00+00:00'],
                        ['who' => 'firm', 'via' => 'mail', 'text' => 'Verbindliche Spendenzusage: 25 Säcke, Lieferung am 01.06.', 'at' => '2027-03-09T11:00:00+00:00'],
                    ],
                ],
                'origin' => DepartmentGrossanlassCommitment::ORIGIN_DONATION,
                'inbound_mode' => 'delivery',
                'donation_pledge' => true,
            ],
        ];
        foreach ($bindings as $binding) {
            $seedKey = $key . ':charge-binding:' . $binding['charge'];
            $chargeRecord = $context->findRecord($key . ':charge:' . $binding['charge']);
            if ($chargeRecord === null) {
                continue;
            }
            $this->once($context, $report, $seedKey, $version, function () use ($context, $department, $actor, $binding, $chargeRecord, $key): ?DepartmentGrossanlassCommitment {
                $commitment = $this->find(DepartmentGrossanlassCommitment::class, $chargeRecord->getEntityId());
                if (!$commitment instanceof DepartmentGrossanlassCommitment) {
                    return null;
                }
                if ($commitment->getInquiryId() !== null && $commitment->getProcurementLineId() !== null) {
                    return $commitment;
                }
                $group = $this->jobGroup($context, 'Crew-Zelt Backstage');
                if (!$group instanceof Group) {
                    return null;
                }
                $line = $this->procurement->createLineDirect($department, $actor, [
                    'group_id' => $group->getId(),
                    'label' => $binding['line']['label'],
                    'quantity' => $binding['line']['quantity'],
                    'quantity_unit' => $binding['line']['unit'],
                    'location' => $group->getName(),
                    'wish_kind' => $binding['line']['kind'],
                    'notes' => $binding['line']['notes'],
                    'category_id' => $this->categoryId($department, $binding['line']['category']),
                    'cost_kind' => $binding['line']['cost_kind'],
                ]);
                $lineEntity = $this->entityManager->find(ActivityGrossanlassProcurementLine::class, (string) $line['id']);
                if (!$lineEntity instanceof ActivityGrossanlassProcurementLine) {
                    return null;
                }
                $inquiryDef = $binding['inquiry'] + [
                    'status' => DepartmentGrossanlassInquiry::STATUS_ZUSAGE,
                    'asked_via' => 'mail',
                    'asked_at' => '2027-03-05 09:00:00',
                    'asked_lines' => [],
                ];
                $inquiry = $this->createInquiry($department, $inquiryDef, [$lineEntity->getId()]);
                $details = array_merge($commitment->getItemDetails(), [
                    'from_line_id' => $lineEntity->getId(),
                    'inbound_mode' => $binding['inbound_mode'],
                    'inbound_status' => 'expected',
                    'absprache' => true,
                ]);
                if (!empty($binding['donation_pledge'])) {
                    $details['donation_pledge'] = true;
                    $details['agreement_kind'] = 'donation';
                }
                $commitment->setInquiry($inquiry);
                $commitment->setProcurementLine($lineEntity);
                $commitment->setItemDetails($details);
                $this->entityManager->flush();

                return $commitment;
            });
        }
    }

    /**
     * Aufbau (Preset 31.05.2027 14:00), additiv zu den übrigen Event-Seeds: Checklisten der freigegebenen Bauaufträge, die bestätigte
     * und freigegebene Verteilfahrt «Kantholz» (Transporter, Chauffeur ga-clw, gepackt) und der aus dem Fahrzeugwunsch überführte, noch
     * nicht disponierte Fahrauftrag «Rampenhölzer». Wie überall im Seed entsteht nichts «erledigt»: Fortschritt, Pack, Abfahrt und
     * Ablieferung führt man nach dem Zeitsprung über die bestehenden Aktionen selbst aus.
     */
    public function syncBuildPhase(SeedContext $context, SyncReport $report, string $version): void
    {
        $department = $context->requireDepartment();
        $actor = $this->userFor('ga-mw');
        if (!$actor instanceof User) {
            return;
        }
        $key = $context->scenarioKey();

        foreach ($this->taskSteps() as $taskKey => $labels) {
            $taskRecord = $context->findRecord($key . ':aufgabe:' . $taskKey);
            $task = $taskRecord !== null ? $this->find(DepartmentGrossanlassTask::class, $taskRecord->getEntityId()) : null;
            if (!$task instanceof DepartmentGrossanlassTask) {
                continue;
            }
            foreach ($labels as $i => $label) {
                $this->once($context, $report, $key . ':aufgabe:' . $taskKey . ':schritt:' . ($i + 1), $version, function () use ($task, $label): DepartmentGrossanlassTaskStep {
                    $step = $this->taskWorkflow->addStep($task, $label);
                    $this->entityManager->flush();

                    return $step;
                });
            }
        }

        $einsatzRecord = $context->findRecord($key . ':einsatz:verteilung-kantholz');
        if ($einsatzRecord !== null) {
            $einsatzId = $einsatzRecord->getEntityId();
            $chauffeur = $this->userFor('ga-clw');
            $this->once($context, $report, $key . ':tour:verteilung-kantholz', $version, function () use ($department, $actor, $chauffeur, $einsatzId): ?DepartmentGrossanlassTour {
                $einsatz = $this->find(DepartmentGrossanlassEinsatz::class, $einsatzId);
                $vehicle = $einsatz instanceof DepartmentGrossanlassEinsatz ? $einsatz->getCommitment() : null;
                if (!$einsatz instanceof DepartmentGrossanlassEinsatz || $vehicle === null || $vehicle->getFamily() !== DepartmentGrossanlassCommitment::FAMILY_VEHICLE || !$chauffeur instanceof User) {
                    return null;
                }
                $payload = $this->dispatch->createTour($department, $actor, [
                    'name' => 'Verteilung Kantholz',
                    'need_ids' => [$einsatzId],
                    'vehicle_id' => $vehicle->getId(),
                    'chauffeur_user_id' => $chauffeur->getId(),
                ]);
                if ($einsatz->getDestinationPlaceId() !== null) {
                    $this->einsaetze->updateEinsatz($department, $actor, $einsatzId, ['packed' => true]);
                    $this->dispatch->confirmTour($department, $actor, (string) $payload['id']);
                    $this->dispatch->releaseNeed($department, $actor, $einsatzId);
                }

                return $this->find(DepartmentGrossanlassTour::class, (string) $payload['id']);
            });
        }

        $needRecord = $context->findRecord($key . ':fahrzeugbedarf:rampe-material-liefern');
        if ($needRecord !== null) {
            $needId = $needRecord->getEntityId();
            $this->once($context, $report, $key . ':einsatz:rampe-material-liefern', $version, function () use ($department, $actor, $needId): ?DepartmentGrossanlassEinsatz {
                $need = $this->find(DepartmentGrossanlassVehicleNeed::class, $needId);
                if (!$need instanceof DepartmentGrossanlassVehicleNeed || $need->getProcurementLineId() === null) {
                    return null;
                }
                $payload = $this->dispatch->convertVehicleNeed($department, $actor, 'vn:' . $needId);

                $row = $this->find(DepartmentGrossanlassEinsatz::class, (string) ($payload['einsatz_id'] ?? ''));

                return $row instanceof DepartmentGrossanlassEinsatz ? $row : null;
            });
        }

        // Durchführung und Abbau: weitere überführte Fahraufträge in drei Ständen
        $this->convertNeed($context, $report, $version, $department, $actor, 'nachschub-getraenke');
        $this->convertNeed($context, $report, $version, $department, $actor, 'tresen-retour');
        $this->convertNeed($context, $report, $version, $department, $actor, 'lader-rueckgabe');
        $this->seedTour($context, $report, $version, $department, $actor, 'nachschub-getraenke', 'Getränkenachschub', 'ga-lw', true);
        $this->seedTour($context, $report, $version, $department, $actor, 'lader-rueckgabe', 'Teleskoplader Rückgabe', 'ga-clw', false);

        // Abschluss: Materialklärung in der Werkstatt (Status ergibt sich aus dem Weg, wie beim Anlegen im Betrieb)
        foreach ([
            'zeltboden' => ['title' => 'Zeltboden beschädigt', 'description' => 'Zwei Bodenplatten des Leihzelts sind gebrochen; Klärung mit dem Verleiher.', 'material_label' => 'Zeltboden-Platten', 'owner_firm_name' => 'Zeltverleih Beispiel', 'origin' => 'loan', 'path' => 'owner'],
            'rampengelaender' => ['title' => 'Rampengeländer angeschlagen', 'description' => 'Ein Geländerpfosten der Rollstuhlrampe muss vor der Wiederverwendung repariert werden.', 'material_label' => 'Geländerhölzer 90×90', 'owner_firm_name' => '', 'origin' => 'own', 'path' => 'repair'],
        ] as $caseKey => $caseData) {
            $this->once($context, $report, $key . ':werkstatt:' . $caseKey, $version, function () use ($department, $actor, $caseData): ?\App\Entity\DepartmentGrossanlassWorkshopCase {
                $payload = $this->workshopCases->create($department, $actor, $caseData);

                return $this->find(\App\Entity\DepartmentGrossanlassWorkshopCase::class, (string) $payload['id']);
            });
        }
        $this->entityManager->flush();
    }

    /** Überführt einen Fahrzeugwunsch des Katalogs in einen Fahrauftrag (Ledger `einsatz:<Wunsch>`). */
    private function convertNeed(SeedContext $context, SyncReport $report, string $version, Department $department, User $actor, string $needKey): void
    {
        $key = $context->scenarioKey();
        $needRecord = $context->findRecord($key . ':fahrzeugbedarf:' . $needKey);
        if ($needRecord === null) {
            return;
        }
        $needId = $needRecord->getEntityId();
        $this->once($context, $report, $key . ':einsatz:' . $needKey, $version, function () use ($department, $actor, $needId): ?DepartmentGrossanlassEinsatz {
            $need = $this->find(DepartmentGrossanlassVehicleNeed::class, $needId);
            if (!$need instanceof DepartmentGrossanlassVehicleNeed || $need->getProcurementLineId() === null) {
                return null;
            }
            $payload = $this->dispatch->convertVehicleNeed($department, $actor, 'vn:' . $needId);
            $row = $this->find(DepartmentGrossanlassEinsatz::class, (string) ($payload['einsatz_id'] ?? ''));

            return $row instanceof DepartmentGrossanlassEinsatz ? $row : null;
        });
    }

    /** Disponiert einen überführten Fahrauftrag: Tour mit Fahrzeug und Chauffeur, bestätigt; optional gepackt und freigegeben. */
    private function seedTour(SeedContext $context, SyncReport $report, string $version, Department $department, User $actor, string $needKey, string $name, string $chauffeurAccount, bool $packAndRelease): void
    {
        $key = $context->scenarioKey();
        $einsatzRecord = $context->findRecord($key . ':einsatz:' . $needKey);
        $chauffeur = $this->userFor($chauffeurAccount);
        if ($einsatzRecord === null || !$chauffeur instanceof User) {
            return;
        }
        $einsatzId = $einsatzRecord->getEntityId();
        $this->once($context, $report, $key . ':tour:' . $needKey, $version, function () use ($department, $actor, $chauffeur, $einsatzId, $name, $packAndRelease): ?DepartmentGrossanlassTour {
            $einsatz = $this->find(DepartmentGrossanlassEinsatz::class, $einsatzId);
            $vehicle = $einsatz instanceof DepartmentGrossanlassEinsatz ? $einsatz->getCommitment() : null;
            if (!$einsatz instanceof DepartmentGrossanlassEinsatz || $vehicle === null || $vehicle->getFamily() !== DepartmentGrossanlassCommitment::FAMILY_VEHICLE) {
                return null;
            }
            $payload = $this->dispatch->createTour($department, $actor, ['name' => $name, 'need_ids' => [$einsatzId], 'vehicle_id' => $vehicle->getId(), 'chauffeur_user_id' => $chauffeur->getId()]);
            $this->dispatch->confirmTour($department, $actor, (string) $payload['id']);
            if ($packAndRelease && $einsatz->getDestinationPlaceId() !== null) {
                $this->einsaetze->updateEinsatz($department, $actor, $einsatzId, ['packed' => true]);
                $this->dispatch->releaseNeed($department, $actor, $einsatzId);
            }

            return $this->find(DepartmentGrossanlassTour::class, (string) $payload['id']);
        });
    }

    /** @return array<string, list<string>> Aufgabenschlüssel (Katalog) → Checklistenpunkte */
    private function taskSteps(): array
    {
        return [
            'ticket-schild' => ['Schilder bereitlegen', 'Schilder montieren', 'Ausrichtung prüfen'],
            'huette-boden' => ['Unterlagsbalken legen', 'Rahmen verschrauben', 'Höhen prüfen'],
            'huette-waende' => ['Wandelemente bereitstellen', 'Elemente aufrichten', 'Winkel montieren'],
            'tresen-rahmen' => ['Paletten ausrichten', 'Paletten verbinden'],
            'betrieb-podest' => ['Verschraubungen prüfen', 'Absperrung prüfen', 'Befund protokollieren'],
            'betrieb-tresen' => ['Kühlung kontrollieren', 'Gebinde tauschen'],
            'betrieb-zelt' => ['Abspannseile prüfen', 'Heringe nachschlagen'],
            'rueck-foh' => ['Seiten demontieren', 'Dach demontieren', 'Elemente bereitstellen'],
            'rueck-tresen' => ['Arbeitsplatte lösen', 'Paletten stapeln', 'Elemente zählen'],
            'rueck-zelt' => ['Zelthülle abspannen', 'Böden aufnehmen', 'Material verladen'],
            'rueck-huette' => ['Hütte leeren', 'Wände lösen', 'Holz sortieren'],
            'rest-leuchten' => ['Leuchten einsammeln', 'Anzahl abgleichen'],
            'rest-begehung' => ['Gelände begehen', 'Befund festhalten'],
        ];
    }

    /** Gepackter, freigegebener Fahrauftrag ohne Tour auf Eigenbestand: bereit für Pack-QR (übernehmen) und Ziel-QR (abliefern). */
    public function syncReadyTransport(SeedContext $context, SyncReport $report, string $version): void
    {
        $department = $context->requireDepartment();
        $actor = $this->userFor('ga-mw');
        $key = $context->scenarioKey();
        if (!$actor instanceof User) {
            return;
        }
        foreach ($this->einsatzDefs() as $def) {
            if (!empty($def['late'])) {
                $this->syncEinsatz($context, $report, $department, $actor, $this->userFor('ga-lw'), $def, $version);
            }
        }
        $einsatzRecord = $context->findRecord($key . ':einsatz:bauzaun-perimeter');
        if ($einsatzRecord === null) {
            return;
        }
        $einsatzId = $einsatzRecord->getEntityId();
        $this->once($context, $report, $key . ':freigabe:bauzaun-perimeter', $version, function () use ($department, $actor, $einsatzId): ?DepartmentGrossanlassEinsatz {
            $einsatz = $this->find(DepartmentGrossanlassEinsatz::class, $einsatzId);
            if (!$einsatz instanceof DepartmentGrossanlassEinsatz || $einsatz->getChauffeurUserId() === null || $einsatz->getDestinationPlaceId() === null) {
                return null;
            }
            $this->einsaetze->updateEinsatz($department, $actor, $einsatzId, ['packed' => true]);
            $this->dispatch->releaseNeed($department, $actor, $einsatzId);

            return $einsatz;
        });
    }

    private function nextUnrecordedPack(Department $department, User $actor, DepartmentGrossanlassEinsatz $einsatz): ?DepartmentGrossanlassPack
    {
        $recorded = $this->recordedPackIds($einsatz);
        foreach ($this->packRows($einsatz) as $pack) {
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
            // Der Ort entsteht mit dem Bauprojekt; wurde er gelöscht und neu angelegt, gilt die Platzierung für den neuen Ort.
            create: static fn (): object => $place,
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
            resettable: true,
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

    private function resolveDestinationPlaceId(Department $department, string $name): ?string
    {
        $place = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findOneBy(['departmentId' => $department->getId(), 'name' => $name]);
        if ($place instanceof DepartmentGrossanlassPlace) {
            return $place->getId();
        }
        if ($name === 'Lagerplatz Gelände') {
            $lagerplatz = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findOneBy(
                ['departmentId' => $department->getId(), 'kind' => GrossanlassPlaceCodes::KIND_LAGERPLATZ],
            );
            if ($lagerplatz instanceof DepartmentGrossanlassPlace) {
                return $lagerplatz->getId();
            }
        }

        return null;
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
                'order' => ['ordered_at' => '2027-04-01 12:00:00', 'cost' => '220.00', 'ref' => 'B-2027-014', 'delivery_at' => '2027-05-19 12:00:00', 'notes' => 'Abholung auf Palette, Lieferschein beilegen.'],
            ],
            [
                'key' => 'lader',
                'earlier_key' => 'teleskoplader',
                'job' => 'Hauptbühnen-Podest',
                'vehicle_need' => 'lader-rueckgabe',
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
                // Echter Transportwunsch: Fahrzeugwunsch «Tresen-Elemente liefern» (purpose transport) mit genau einem zugesagten Lieferwagen.
                // Bewusst noch kein Fahrauftrag: die Logistik überführt den Wunsch in der Disposition.
                'key' => 'lieferwagen',
                'job' => 'Bar-Tresen West',
                'vehicle_need' => ['tresen-liefern', 'nachschub-getraenke', 'tresen-retour'],
                'quote_reference' => 'TV-2027-009',
                'zusage' => true,
                'line' => ['label' => 'Lieferwagen', 'quantity' => 1, 'unit' => 'Stk', 'kind' => 'fahrzeug', 'category' => self::CATEGORY_TRANSPORT, 'cost_kind' => 'rental', 'notes' => 'Für Lieferung und Abholung der Tresen-Elemente.'],
                'inquiry' => [
                    'name' => 'Transport Vermietung Beispiel', 'email' => 'vermietung@transport-beispiel.example', 'place' => 'Sursee', 'phone' => '041 000 00 55', 'contact_name' => 'Vermietung',
                    'offering' => 'Miete von Lieferwagen und Transportern', 'category' => self::CATEGORY_TRANSPORT, 'status' => DepartmentGrossanlassInquiry::STATUS_ANTWORT,
                    'asked_via' => 'mail', 'asked_at' => '2027-03-18 09:00:00', 'asked_lines' => [],
                    'thread' => [
                        ['who' => 'ok', 'via' => 'mail', 'text' => 'Mail: Anfrage Lieferwagen (19.05.–23.06.2027) gesendet.', 'at' => '2027-03-18T09:00:00+00:00'],
                        ['who' => 'firm', 'via' => 'mail', 'text' => 'Zusage: Lieferwagen steht ab 19.05. bereit. Offerte TV-2027-009 im Anhang.', 'at' => '2027-03-19T08:30:00+00:00'],
                    ],
                ],
                'quote' => [
                    'supplier' => 'Transport Vermietung Beispiel', 'amount' => '640.00', 'notes' => 'Offerte TV-2027-009, Miete bis 23.06.', 'date' => '19.03.2027',
                    'delivery_at' => '2027-05-19 08:00:00', 'delivery_label' => 'Bereitstellung am 19.05.2027, 08:00 Uhr', 'lead_days' => 7,
                    'inbound_mode' => 'pickup', 'return_needed' => true, 'return_at' => '2027-06-23 17:00:00', 'pdf' => false, 'selected' => true,
                ],
            ],
            [
                // Zweiter Transportwunsch (Aufbau): unentgeltlich geliehener Transporter für die Materialverteilung (ändert das Logistik-Budget
                // nicht). Der Einsatz «Verteilung Kantholz» und der Fahrzeugwunsch «Rampenhölzer liefern» nutzen dieselbe Zusage zu
                // verschiedenen Zeiten.
                'key' => 'transporter',
                'job' => 'Rollstuhlrampe Haupteingang',
                'vehicle_need' => 'rampe-material-liefern',
                'quote_reference' => 'TV-2027-010',
                'zusage' => true,
                'line' => ['label' => 'Transporter 3,5 t', 'quantity' => 1, 'unit' => 'Stk', 'kind' => 'fahrzeug', 'category' => self::CATEGORY_TRANSPORT, 'cost_kind' => 'loan', 'notes' => 'Materialverteilung auf dem Gelände während des Aufbaus.'],
                'inquiry' => [
                    'name' => 'Pfadi Nachbarabteilung', 'email' => 'material@nachbarabteilung.example', 'place' => 'Sursee', 'phone' => '041 000 00 66', 'contact_name' => 'Materialwart',
                    'offering' => 'Leihe von Fahrzeugen und Anhängern', 'category' => self::CATEGORY_TRANSPORT, 'status' => DepartmentGrossanlassInquiry::STATUS_ANTWORT,
                    'asked_via' => 'mail', 'asked_at' => '2027-03-22 09:00:00', 'asked_lines' => [],
                    'thread' => [
                        ['who' => 'ok', 'via' => 'mail', 'text' => 'Mail: Anfrage Transporter 3,5 t (31.05.–04.06.2027) gesendet.', 'at' => '2027-03-22T09:00:00+00:00'],
                        ['who' => 'firm', 'via' => 'mail', 'text' => 'Zusage: Transporter steht ab 31.05. bereit, unentgeltlich. Bestätigung TV-2027-010 im Anhang.', 'at' => '2027-03-23T08:30:00+00:00'],
                    ],
                ],
                'quote' => [
                    'supplier' => 'Pfadi Nachbarabteilung', 'amount' => '0.00', 'notes' => 'Bestätigung TV-2027-010, Leihe bis 04.06., unentgeltlich.', 'date' => '23.03.2027',
                    'delivery_at' => '2027-05-31 08:00:00', 'delivery_label' => 'Bereitstellung am 31.05.2027, 08:00 Uhr', 'lead_days' => 7,
                    'inbound_mode' => 'pickup', 'return_needed' => true, 'return_at' => '2027-06-04 17:00:00', 'pdf' => false, 'selected' => true,
                ],
            ],
            [
                // Noch nicht überführter Transportwunsch: «Zeltmaterial abholen» (purpose transport) mit genau einem zugesagten Lastwagen
                // (unentgeltliche Leihe, Budget unverändert) und ohne Fahrauftrag. Die Logistik überführt ihn in der Disposition.
                'key' => 'lastwagen',
                'job' => 'Catering-Zelt 20×30',
                'vehicle_need' => 'zelt-abholen',
                'quote_reference' => 'GW-2027-003',
                'zusage' => true,
                'line' => ['label' => '7,5-t-Lastwagen', 'quantity' => 1, 'unit' => 'Stk', 'kind' => 'fahrzeug', 'category' => self::CATEGORY_TRANSPORT, 'cost_kind' => 'loan', 'notes' => 'Abholung des Zeltmaterials beim Zeltverleih.'],
                'inquiry' => [
                    'name' => 'Gemeindewerke Beispiel', 'email' => 'fahrzeuge@gemeindewerke-beispiel.example', 'place' => 'Sursee', 'phone' => '041 000 00 77', 'contact_name' => 'Fahrzeugdisposition',
                    'offering' => 'Leihe von Lastwagen für Vereinsanlässe', 'category' => self::CATEGORY_TRANSPORT, 'status' => DepartmentGrossanlassInquiry::STATUS_ANTWORT,
                    'asked_via' => 'mail', 'asked_at' => '2027-03-24 09:00:00', 'asked_lines' => [],
                    'thread' => [
                        ['who' => 'ok', 'via' => 'mail', 'text' => 'Mail: Anfrage 7,5-t-Lastwagen (01.06.2027, halber Tag) gesendet.', 'at' => '2027-03-24T09:00:00+00:00'],
                        ['who' => 'firm', 'via' => 'mail', 'text' => 'Zusage: Lastwagen steht am 01.06. bereit, unentgeltlich. Bestätigung GW-2027-003 im Anhang.', 'at' => '2027-03-25T08:30:00+00:00'],
                    ],
                ],
                'quote' => [
                    'supplier' => 'Gemeindewerke Beispiel', 'amount' => '0.00', 'notes' => 'Bestätigung GW-2027-003, Leihe 01.06., unentgeltlich.', 'date' => '25.03.2027',
                    'delivery_at' => '2027-06-01 12:30:00', 'delivery_label' => 'Bereitstellung am 01.06.2027, 12:30 Uhr', 'lead_days' => 7,
                    'inbound_mode' => 'pickup', 'return_needed' => true, 'return_at' => '2027-06-02 17:00:00', 'pdf' => false, 'selected' => true,
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
     * Beschaffungsabläufe vom Materialwunsch bis zum Wareneingang. Die Schritte laufen über die Dienste der App
     * (Bündeln, Offerte, Bestellung mit Teilmenge und Absprache, Storno). Wie überall im Seed bucht er keine Warenbewegung: den Wareneingang samt Gegenbuchung bucht man nach dem Zeitsprung selbst. Alle Bestellungen liegen vor dem
     * Ausgangsstand der Demo-Uhr (04.04.2027); die Lieferungen (OSB ab 25.03., Bitumenschindeln am 11.05.) sind offen.
     *
     * @return list<array{key: string, wish: string, steps: list<array<string, mixed>>}>
     */
    private function procurementFlows(): array
    {
        return [
            [
                // Zwei Lieferanten (Offerte und telefonische Absprache ohne Offerte); die Lieferungen sind noch nicht gebucht.
                'key' => 'osb',
                'wish' => 'ticket-haeuschen-eingang:osb-18-mm-platten',
                'steps' => [
                    ['key' => 'buendel', 'type' => 'bundle', 'label' => 'OSB 18 mm Platten'],
                    ['key' => 'offerte', 'type' => 'quote', 'supplier' => 'Holzwerkstoffe Sursee AG', 'amount' => 210, 'delivery_at' => '2027-03-25 08:00:00', 'inbound_mode' => 'delivery'],
                    ['key' => 'bestellung-a', 'type' => 'order', 'quote' => 'offerte', 'quantity' => 14, 'cost' => '210.00', 'ref' => 'B-2027-021', 'delivery_at' => '2027-03-25 08:00:00', 'ordered_at' => '2027-03-15 12:00:00'],
                    ['key' => 'bestellung-b', 'type' => 'order', 'supplier' => 'Zimmerei Muri (telefonisch)', 'quantity' => 10, 'cost' => '140.00', 'ref' => 'Telefon 18.03.', 'delivery_at' => '2027-04-01 08:00:00', 'ordered_at' => '2027-03-18 10:30:00', 'notes' => 'Telefonisch vereinbart, keine Offerte.'],
                ],
            ],
            [
                // Leihe plus Kauf ohne Doppeldeckung; ein Storno bleibt in der Historie; die Ersatzbestellung ist noch nicht geliefert.
                'key' => 'bitumen',
                'wish' => 'ticket-haeuschen-eingang:bitumenschindeln-anthrazit',
                'steps' => [
                    ['key' => 'buendel', 'type' => 'bundle', 'label' => 'Bitumenschindeln anthrazit'],
                    ['key' => 'leihe', 'type' => 'loan', 'source' => 'Dachdecker Meier', 'quantity' => 3],
                    ['key' => 'bestellung-storniert', 'type' => 'order', 'supplier' => 'Baumarkt Beispiel (telefonisch)', 'quantity' => 5, 'cost' => '160.00', 'ref' => 'Telefon 20.03.', 'delivery_at' => '2027-04-20 08:00:00', 'ordered_at' => '2027-03-20 09:00:00'],
                    ['key' => 'storno', 'type' => 'cancel', 'order' => 'bestellung-storniert', 'reason' => 'Lieferant kann den Termin nicht halten'],
                    ['key' => 'bestellung', 'type' => 'order', 'supplier' => 'Dachmaterial Rothenburg (telefonisch)', 'quantity' => 5, 'cost' => '175.00', 'ref' => 'Telefon 24.03.', 'delivery_at' => '2027-05-11 08:00:00', 'ordered_at' => '2027-03-24 11:00:00'],
                ],
            ],
        ];
    }

    private function syncProcurementFlows(SeedContext $context, SyncReport $report, Department $department, User $actor, string $version): void
    {
        foreach ($this->procurementFlows() as $flow) {
            $key = $context->scenarioKey() . ':beschaffung:' . $flow['key'] . ':';
            /** @var array<string, string> $orderIds Schritt-Schlüssel → Bestellungs-ID */
            $orderIds = [];
            $line = null;
            $quote = null;
            foreach ($flow['steps'] as $step) {
                $seedKey = $key . $step['key'];
                $entity = null;
                switch ($step['type']) {
                    case 'bundle':
                        $entity = $this->once($context, $report, $seedKey, $version, function () use ($context, $department, $actor, $flow, $step): ?ActivityGrossanlassProcurementLine {
                            $record = $context->findRecord($context->scenarioKey() . ':wunsch:' . $flow['wish']);
                            $wish = $record !== null ? $this->find(ActivityGrossanlassWishLine::class, $record->getEntityId()) : null;
                            if (!$wish instanceof ActivityGrossanlassWishLine) {
                                return null;
                            }
                            if ($wish->getProcurementResponsibility() === null) {
                                $wish->setProcurementResponsibility(ActivityGrossanlassWishLine::RESPONSIBILITY_CENTRAL);
                                $this->entityManager->flush();
                            }
                            $row = $this->procurement->createLineFromWishes($department, $actor, [$wish->getId()], ['label' => $step['label'], 'category_id' => $this->categoryId($department, self::CATEGORY_BAU), 'cost_kind' => 'purchase']);

                            return $this->find(ActivityGrossanlassProcurementLine::class, (string) $row['id']);
                        });
                        $line = $entity instanceof ActivityGrossanlassProcurementLine ? $entity : null;
                        break;
                    case 'quote':
                        $entity = $line === null ? null : $this->once($context, $report, $seedKey, $version, function () use ($department, $actor, $line, $step): ?ActivityGrossanlassProcurementQuote {
                            $data = $this->procurement->createQuote($department, $actor, $line->getId(), [
                                'supplier' => $step['supplier'],
                                'amount_chf' => $step['amount'],
                                'delivery_at' => $step['delivery_at'],
                                'inbound_mode' => $step['inbound_mode'],
                                'return_needed' => false,
                            ]);

                            return $this->find(ActivityGrossanlassProcurementQuote::class, (string) $data['id']);
                        });
                        $quote = $entity instanceof ActivityGrossanlassProcurementQuote ? $entity : null;
                        break;
                    case 'order':
                        $entity = $line === null ? null : $this->once($context, $report, $seedKey, $version, function () use ($department, $actor, $line, $quote, $step): ?ActivityGrossanlassProcurementOrder {
                            $payload = [
                                'quantity' => $step['quantity'],
                                'cost_chf' => $step['cost'],
                                'order_ref' => $step['ref'],
                                'ordered_at' => $step['ordered_at'],
                                'delivery_at' => $step['delivery_at'],
                                'notes' => $step['notes'] ?? null,
                                'inbound_mode' => 'delivery',
                            ];
                            if (isset($step['quote']) && $quote instanceof ActivityGrossanlassProcurementQuote) {
                                $payload['quote_id'] = $quote->getId();
                            } else {
                                $payload['supplier'] = $step['supplier'];
                            }
                            $before = array_map(static fn (array $o) => $o['id'], $this->procurement->createOrder($department, $actor, $line->getId(), $payload)['orders']);

                            return $this->find(ActivityGrossanlassProcurementOrder::class, (string) end($before));
                        });
                        if ($entity instanceof ActivityGrossanlassProcurementOrder) {
                            $orderIds[$step['key']] = $entity->getId();
                        }
                        break;
                    case 'cancel':
                        $orderId = $orderIds[$step['order']] ?? null;
                        $entity = $line === null || $orderId === null ? null : $this->once($context, $report, $seedKey, $version, function () use ($department, $actor, $line, $orderId, $step): ?ActivityGrossanlassProcurementOrder {
                            $this->procurement->cancelOrder($department, $actor, $line->getId(), $orderId, $step['reason']);

                            return $this->find(ActivityGrossanlassProcurementOrder::class, $orderId);
                        });
                        break;
                    case 'loan':
                        $entity = $line === null ? null : $this->once($context, $report, $seedKey, $version, function () use ($department, $actor, $line, $step): ?DepartmentGrossanlassCommitment {
                            $row = $this->commitments->create($department, $actor, [
                                'name' => $line->getLabel(),
                                'source' => $step['source'],
                                'origin' => DepartmentGrossanlassCommitment::ORIGIN_LOAN,
                                'quantity' => $step['quantity'],
                                'procurement_line_id' => $line->getId(),
                                // Verknüpfung über item_details.from_line_id: die Kostenzeile der Zusage bleibt ohne eigene Position
                                // (sonst zählt die Budgetübersicht die Position doppelt).
                                'item_details' => ['from_line_id' => $line->getId(), 'inbound_mode' => 'pickup', 'inbound_status' => 'expected'],
                                'released' => true,
                            ]);

                            return $this->find(DepartmentGrossanlassCommitment::class, (string) $row['id']);
                        });
                        break;
                }
                if ($entity === null && $step['type'] !== 'bundle') {
                    // Folgeschritte hängen am vorherigen; fehlt dieser (gelöscht), wird nichts nachgezogen.
                    continue;
                }
            }
        }
    }

    /**
     * Einsätze zum Beschaffungsablauf: Abholung der bestellten Ware (Fahrauftrag) und die Zuteilung von Material an ein Bauprojekt.
     *
     * @return list<array<string, mixed>>
     */
    private function einsatzDefs(): array
    {
        return [
            ['key' => 'abholung-bitumen', 'commitment_seed' => 'beschaffung:bitumen:leihe', 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ, 'qty' => 3, 'from' => '2027-05-19 09:00:00', 'to' => '2027-05-19 10:00:00', 'who' => 'Abholung Bitumenschindeln Dachdecker Meier', 'delivery' => 'trip', 'destination' => 'Lagerplatz Gelände', 'packs' => 1],
            ['key' => 'abholung-holz', 'chain' => 'holz', 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ, 'qty' => 30, 'from' => '2027-05-19 12:00:00', 'to' => '2027-05-19 13:00:00', 'who' => 'Abholung Häberli Holz', 'delivery' => 'trip', 'destination' => 'Lagerplatz Gelände', 'packs' => 1],
            // Eigenbestand (Bauzaun): einziger Fahrauftrag, der sich vor dem Wareneingang per QR übernehmen lässt (gepackt, freigegeben, Chauffeur ga-lw)
            ['key' => 'bauzaun-perimeter', 'commitment_seed' => 'charge:bauzaun-eigen', 'late' => true, 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ, 'qty' => 10, 'from' => '2027-05-19 14:00:00', 'to' => '2027-05-19 15:00:00', 'who' => 'Bauzaunelemente zum Perimeter', 'delivery' => 'trip', 'destination' => 'Bauzaun Perimeter', 'packs' => 1],
            ['key' => 'anlieferung-lader', 'chain' => 'lader', 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ, 'qty' => 1, 'from' => '2027-05-31 07:00:00', 'to' => '2027-05-31 08:30:00', 'who' => 'Anlieferung Teleskoplader Maki Landtechnik', 'delivery' => 'trip', 'destination' => 'Hauptbühne', 'packs' => 1],
            ['key' => 'verteilung-kantholz', 'chain' => 'transporter', 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ, 'qty' => 1, 'from' => '2027-05-31 16:00:00', 'to' => '2027-05-31 17:00:00', 'who' => 'Verteilung Kantholz Eingang Nord zur Crew-Hütte', 'delivery' => 'trip', 'destination' => 'Crew-Hütte Backstage', 'chauffeur' => 'ga-clw', 'packs' => 1],
            ['key' => 'zuteilung-staenderholz', 'kind' => DepartmentGrossanlassEinsatz::KIND_ORDER, 'qty' => 40, 'from' => '2027-05-20 08:00:00', 'to' => '2027-06-02 18:00:00', 'who' => 'KVH Ständerholz für das Ticket-Häuschen', 'delivery' => 'pickup', 'job' => 'Ticket-Häuschen Eingang', 'wish' => 'ticket-haeuschen-eingang:kvh-60x80-staenderholz', 'packs' => 1],
            ['key' => 'zuteilung-schalung', 'kind' => DepartmentGrossanlassEinsatz::KIND_ORDER, 'qty' => 80, 'from' => '2027-05-31 15:00:00', 'to' => '2027-06-01 18:00:00', 'who' => 'Holzschalung für die Crew-Hütte', 'delivery' => 'pickup', 'job' => 'Crew-Hütte Backstage', 'wish' => 'crew-huette-backstage:holzschalung-22-mm-nut-feder', 'packs' => 1],
            ['key' => 'nachschub-sperrholz', 'kind' => DepartmentGrossanlassEinsatz::KIND_ORDER, 'qty' => 5, 'from' => '2027-06-05 17:00:00', 'to' => '2027-06-05 19:00:00', 'who' => 'Nachschub Sperrholz für den Bar-Tresen', 'delivery' => 'pickup', 'job' => 'Bar-Tresen West', 'wish' => 'bar-tresen-west:sperrholz-15-mm-250x125', 'packs' => 1],
            ['key' => 'zuteilung-osb', 'kind' => DepartmentGrossanlassEinsatz::KIND_ORDER, 'qty' => 24, 'from' => '2027-05-20 08:00:00', 'to' => '2027-06-02 18:00:00', 'who' => 'OSB-Platten für das Ticket-Häuschen', 'delivery' => 'pickup', 'job' => 'Ticket-Häuschen Eingang', 'wish' => 'ticket-haeuschen-eingang:osb-18-mm-platten', 'packs' => 1],
        ];
    }
}
