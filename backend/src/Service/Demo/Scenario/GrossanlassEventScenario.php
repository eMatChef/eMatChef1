<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use App\Service\Bootstrap\DemoGrossanlassEventJobsSeedService;
use App\Service\Bootstrap\DemoGrossanlassEventLogisticsSeedService;
use App\Service\Bootstrap\DemoGrossanlassEventProcessSeedService;
use App\Service\Clock\BusinessClock;
use App\Service\Demo\Organisation\DemoOrganisationCatalog;
use App\Service\Demo\Organisation\DemoOrganisationSeeder;
use App\Service\Demo\Organisation\ManagedSeedApplier;
use App\Service\Demo\Organisation\SyncReport;
use App\Service\Demo\Reset\DemoEventResetter;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;

final class GrossanlassEventScenario extends AbstractCatalogScenario
{
    public function __construct(
        DemoOrganisationSeeder $organisationSeeder,
        private GrossanlassClockOriginResolver $grossanlassOrigin,
        private DemoGrossanlassEventJobsSeedService $content,
        private DemoScenarioIdentity $identity,
        private DemoOrganisationCatalog $catalog,
        private BusinessClock $clock,
        private DemoGrossanlassEventProcessSeedService $process,
        private DemoGrossanlassEventLogisticsSeedService $logistics,
        private DemoEventResetter $resetter,
    ) {
        parent::__construct($organisationSeeder);
    }

    public function key(): string
    {
        return DemoScenarioKey::GROSSANLASS_EVENT;
    }

    public function label(): string
    {
        return 'Grossanlass Event';
    }

    /**
     * Sicherer Reset ohne Löschung (nur über den Runner, per Konsole `app:demo:reset`): verwaltete Seed-Werte, gelöschte
     * Seed-Datensätze mit gleichem QR-Code und die Uhr. Die Oberfläche bietet ihn nicht an ({@see DemoResetService::SUPPORTED}).
     */
    public function supportsReset(): bool
    {
        return true;
    }

    public function reset(SeedContext $context): SeedResult
    {
        return $this->resetter->apply($context, $this);
    }

    public function expectsGrossanlass(): bool
    {
        return true;
    }

    /**
     * Organisationsstruktur aus dem Katalog, danach der Event-Inhalt (Gewerke, Bauprojekte, Materialwünsche, Orte, Aufgaben,
     * Fahrzeugbedarfe, Beschaffung und Kosten) über den bestehenden Event-Jobs-Dienst, ebenfalls ledger-geführt.
     */
    public function sync(SeedContext $context): SeedResult
    {
        $structure = $this->organisationSeeder->sync($this, $context, array_merge($this->content->expectedSeedKeys($this->key()), $this->process->expectedSeedKeys($this->key(), $this->mapDef()), $this->process->earlierSeedKeys($context), $this->logistics->expectedSeedKeys($this->key(), $this->logisticsDef()), [$this->key() . ':clock']));
        if ($context->isDryRun() || $structure->status !== SeedResult::OK) {
            return $structure;
        }
        $department = $context->department() ?? $this->identity->findDepartment($this->key());
        if ($department === null) {
            return $structure;
        }

        $report = new SyncReport();
        $context = $context->withDepartment($department);
        $this->content->syncScenario($context, $report, $this->catalog->version());
        $this->process->syncScenario($context, $report, $this->mapDef(), $this->catalog->version());
        $this->logistics->syncScenario($context, $report, $this->logisticsDef(), $this->catalog->version());
        $this->initialiseClock($context, $department, $report);

        return SeedResult::ok(
            $structure->message . ' | Event-Inhalt: ' . $report->summary(),
            $structure->created + $report->created + $report->recreated,
            $structure->existing + $report->unchanged + $report->updated + $report->adopted,
            array_merge($structure->notes, $report->notes()),
        );
    }

    protected function verifyContent(SeedContext $context): array
    {
        return array_merge(parent::verifyContent($context), $this->content->verifyScenario($context), $this->process->verifyScenario($context, $this->mapDef()), $this->logistics->verifyScenario($context, $this->logisticsDef()));
    }

    /**
     * Ausgangspunkt der Demo-Uhr: fester Zeitpunkt aus dem Katalog (`grossanlass.clockOrigin`, Wandzeit), sonst der
     * bisherige Grossanlass-Ausgangspunkt (Anlassbeginn − 5 Tage, 09:00). Er dient dem Zurücksetzen der Uhr im Header.
     */
    public function clockOrigin(Department $department): ?\DateTimeInterface
    {
        if ($department->getGrossanlassConfig() === null) {
            return null;
        }

        return $this->catalogOrigin() ?? $this->grossanlassOrigin->originFor($department);
    }

    /** @return array<string, mixed> Katalog `grossanlass.logistics` (Budget-Limits, Lager, Fahrzeuge, Fahrerkarten, Chargen) */
    private function logisticsDef(): array
    {
        $def = $this->catalog->scenario($this->key())['grossanlass']['logistics'] ?? [];

        return \is_array($def) ? $def : [];
    }

    /** @return array<string, mixed> Katalog `grossanlass.map` (Geländekarte samt Platzierung) */
    private function mapDef(): array
    {
        $map = $this->catalog->scenario($this->key())['grossanlass']['map'] ?? [];

        return \is_array($map) ? $map : [];
    }

    private function catalogOrigin(): ?\DateTimeImmutable
    {
        $raw = $this->catalog->scenario($this->key())['grossanlass']['clockOrigin'] ?? null;

        return \is_string($raw) ? (\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $raw, new \DateTimeZone('UTC')) ?: null) : null;
    }

    /**
     * Erstinitialisierung der Demo-Uhr auf den festen Ausgangspunkt. Genau einmal: der Ledger-Eintrag `:clock` hält fest,
     * dass das geschehen ist; ein späterer Sync setzt eine vom Benutzer verstellte Uhr nie zurück (die Uhr läuft nach dem
     * Zeitsprung normal weiter). Die Anlassdaten selbst werden nie verändert.
     */
    private function initialiseClock(SeedContext $context, Department $department, SyncReport $report): void
    {
        $origin = $this->catalogOrigin();
        $seedKey = $this->key() . ':clock';
        if ($origin === null) {
            return;
        }
        if ($context->findRecord($seedKey) !== null) {
            ++$report->unchanged;

            return;
        }
        $this->clock->travelTo($department, $origin);
        $context->record($seedKey, $department, (string) $department->getId(), ManagedSeedApplier::hash(['origin' => $origin->format('Y-m-d H:i:s')]), $this->catalog->version());
        ++$report->created;
    }
}
