<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use App\Service\Bootstrap\DemoGrossanlassEventJobsSeedService;
use App\Service\Demo\Organisation\DemoOrganisationCatalog;
use App\Service\Demo\Organisation\DemoOrganisationSeeder;
use App\Service\Demo\Organisation\SyncReport;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;

final class GrossanlassEventScenario extends AbstractCatalogScenario
{
    public function __construct(
        DemoOrganisationSeeder $organisationSeeder,
        private GrossanlassClockOriginResolver $grossanlassOrigin,
        private DemoGrossanlassEventJobsSeedService $content,
        private DemoScenarioIdentity $identity,
        private DemoOrganisationCatalog $catalog,
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
        $structure = $this->organisationSeeder->sync($this, $context, $this->content->expectedSeedKeys($this->key()));
        if ($context->isDryRun() || $structure->status !== SeedResult::OK) {
            return $structure;
        }
        $department = $context->department() ?? $this->identity->findDepartment($this->key());
        if ($department === null) {
            return $structure;
        }

        $report = new SyncReport();
        $this->content->syncScenario($context->withDepartment($department), $report, $this->catalog->version());

        return SeedResult::ok(
            $structure->message . ' | Event-Inhalt: ' . $report->summary(),
            $structure->created + $report->created + $report->recreated,
            $structure->existing + $report->unchanged + $report->updated + $report->adopted,
            array_merge($structure->notes, $report->notes()),
        );
    }

    protected function verifyContent(SeedContext $context): array
    {
        return array_merge(parent::verifyContent($context), $this->content->verifyScenario($context));
    }

    /** Bestehender Grossanlass-Ausgangspunkt (Anlassbeginn − 5 Tage, 09:00) wiederverwendet. */
    public function clockOrigin(Department $department): ?\DateTimeInterface
    {
        return $department->getGrossanlassConfig() === null ? null : $this->grossanlassOrigin->originFor($department);
    }
}
