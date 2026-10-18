<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassPlaceGroup;
use App\Entity\Group;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\DisplayAccessCodeGenerator;
use App\Service\Display\DisplayMapDataService;
use App\Service\Display\Layout\DisplayWidgetRegistry;
use App\Service\Media\MediaCompressionService;
use App\Service\Media\MediaSettingsStore;
use App\Service\Media\MediaStorageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Kartendaten des Infoscreen-Widgets «ga_site_map»: dieselben Orte/Pläne wie die GA-Planung, nur auf Anzeige
 * reduziert und strikt auf das Department des Screens begrenzt.
 */
final class DisplayMapDataIntegrationTest extends TestCase
{
    use IsolatedDatabase;

    private const GA_DEPT = 'dccffd078d5c';
    private const OTHER_GA = 'a1b2c3d4e5f6';
    private const DEPT = 'b57aa6184ef5';

    private EntityManagerInterface $em;
    private DepartmentDisplayScreenService $screens;
    private DisplayMapDataService $service;
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->em = $this->isolatedEntityManager([self::DEPT, self::GA_DEPT, self::OTHER_GA]);
        $this->em->getConnection()->executeStatement('UPDATE department SET is_grossanlass = true WHERE id IN (?, ?)', [self::GA_DEPT, self::OTHER_GA]);
        $this->screens = new DepartmentDisplayScreenService($this->em, new DisplayAccessCodeGenerator(), 'https://app.ematchef.test');
        $this->uploadDir = sys_get_temp_dir() . '/ematchef_map_' . bin2hex(random_bytes(4));
        $settings = $this->createMock(MediaSettingsStore::class);
        $storage = new MediaStorageService($this->uploadDir, new MediaCompressionService($settings));
        $this->service = new DisplayMapDataService($this->em, $storage);
    }

    protected function tearDown(): void
    {
        $this->releaseIsolatedDatabase($this->em ?? null);
        if (isset($this->uploadDir) && is_dir($this->uploadDir)) {
            exec('rm -rf ' . escapeshellarg($this->uploadDir));
        }
    }

    private function screen(string $department): DepartmentDisplayScreen
    {
        return $this->screens->create($department, 'Karte', null)['screen'];
    }

    private function group(string $department, string $id, string $name, ?Group $parent = null): Group
    {
        $group = (new Group())->setId($id)->setDepartment($this->em->getRepository(Department::class)->find($department))->setName($name);
        if ($parent !== null) {
            $group->setParent($parent);
        }
        $this->em->persist($group);
        $this->em->flush();

        return $group;
    }

    /** @param list<array{lat: float, lng: float}>|null $polygon */
    private function place(string $department, string $id, string $name, string $kind, ?float $lat, ?float $lng, ?array $polygon = null, ?Group $group = null): DepartmentGrossanlassPlace
    {
        $place = (new DepartmentGrossanlassPlace())
            ->setId($id)
            ->setDepartment($this->em->getRepository(Department::class)->find($department))
            ->setName($name)
            ->setKind($kind)
            ->setLatitude($lat)
            ->setLongitude($lng)
            ->setPolygon($polygon)
            ->setPublicCode('pub-' . $id);
        $this->em->persist($place);
        if ($group !== null) {
            $this->em->persist(new DepartmentGrossanlassPlaceGroup($place, $group));
        }
        $this->em->flush();

        return $place;
    }

    private function plan(string $department, string $id, string $name, bool $withImage = true): DepartmentGrossanlassMap
    {
        $map = (new DepartmentGrossanlassMap())->setId($id)->setDepartment($this->em->getRepository(Department::class)->find($department))->setName($name);
        if ($withImage) {
            $map->setImageFilename('plan.png');
            $map->setBounds(47.1, 47.0, 8.1, 8.0);
        }
        $this->em->persist($map);
        $this->em->flush();
        if ($withImage) {
            // Pfad der Plan-Datei über den Service auflösen (Ordnerstruktur gehört ihm)
            $storage = new MediaStorageService($this->uploadDir, new MediaCompressionService($this->createMock(MediaSettingsStore::class)));
            $target = $storage->resolveContextDir(MediaStorageService::CONTEXT_GROSSANLASS_MAP, $department, $id);
            mkdir($target, 0777, true);
            file_put_contents($target . '/plan.png', 'png');
        }

        return $map;
    }

    public function testPlacesAndPlanKeepTheirCoordinatesAndExposeNoManagementFields(): void
    {
        $screen = $this->screen(self::GA_DEPT);
        $polygon = [['lat' => 47.001, 'lng' => 8.001], ['lat' => 47.002, 'lng' => 8.003], ['lat' => 47.0005, 'lng' => 8.004]];
        $this->place(self::GA_DEPT, 'plpoint00000', 'Eingang Nord', 'poi', 47.0123, 8.0456);
        $this->place(self::GA_DEPT, 'plarea000000', 'Festgelände', 'area', 47.0012, 8.0027, $polygon);
        $this->plan(self::GA_DEPT, 'gmplan000000', 'Geländeplan');

        $data = $this->service->forScreen($screen, true);

        self::assertSame('ready', $data['state']);
        $byId = array_column($data['places'], null, 'id');
        self::assertSame(47.0123, $byId['plpoint00000']['latitude']);
        self::assertSame(8.0456, $byId['plpoint00000']['longitude']);
        self::assertSame('area', $byId['plarea000000']['kind']);
        self::assertSame($polygon, $byId['plarea000000']['polygon']);
        foreach ($data['places'] as $row) {
            self::assertEqualsCanonicalizing(['id', 'name', 'kind', 'latitude', 'longitude', 'polygon', 'starred', 'group_ids'], array_keys($row));
        }
        self::assertSame(47.1, $data['plan']['bounds_north']);
        self::assertSame(8.0, $data['plan']['bounds_west']);
        // Gerät: eigene Geräte-Route, nie die Medienroute mit User-Login
        self::assertStringStartsWith('/api/public/display-device/map-image/gmplan000000', $data['plan']['image_url']);
        self::assertStringStartsWith('/media/', $this->service->forScreen($screen, false)['plan']['image_url']);
    }

    public function testEmptyGrossanlassYieldsNoInventedData(): void
    {
        $data = $this->service->forScreen($this->screen(self::GA_DEPT), true);

        self::assertSame(['state' => 'ready', 'plan' => null, 'places' => []], $data);
    }

    public function testPlacesWithoutGeometryAndPlansWithoutBoundsAreOmitted(): void
    {
        $screen = $this->screen(self::GA_DEPT);
        $this->place(self::GA_DEPT, 'plnogeo00001', 'Ohne Koordinaten', 'poi', null, null);
        $this->place(self::GA_DEPT, 'plbadarea001', 'Zu wenige Punkte', 'area', null, null, [['lat' => 47.0, 'lng' => 8.0]]);
        $this->plan(self::GA_DEPT, 'gmnobounds00', 'Ohne Lage', false);

        $data = $this->service->forScreen($screen, true);

        self::assertSame([], $data['places']);
        self::assertNull($data['plan']);
    }

    public function testDepartmentIsolation(): void
    {
        $this->place(self::OTHER_GA, 'plforeign000', 'Fremder Ort', 'poi', 46.0, 7.0);
        $this->plan(self::OTHER_GA, 'gmforeign000', 'Fremder Plan');
        $this->place(self::GA_DEPT, 'plown0000000', 'Eigener Ort', 'poi', 47.0, 8.0);
        $screen = $this->screen(self::GA_DEPT);

        $data = $this->service->forScreen($screen, true);

        self::assertSame(['plown0000000'], array_column($data['places'], 'id'));
        self::assertNull($data['plan']);
        // fremder Plan ist über die Geräte-Route des eigenen Screens nicht abrufbar
        self::assertNull($this->service->mapImagePath($screen, 'gmforeign000'));
        self::assertNotNull($this->service->mapImagePath($this->screen(self::OTHER_GA), 'gmforeign000'));
    }

    public function testNormalDepartmentGetsNoMapData(): void
    {
        $this->place(self::DEPT, 'pldept000000', 'Ort', 'poi', 47.0, 8.0);

        self::assertSame(['state' => 'unavailable', 'plan' => null, 'places' => []], $this->service->forScreen($this->screen(self::DEPT), true));
    }

    public function testGroupIdsIncludeAncestorsSoWidgetsCanFilterByProjectOrArea(): void
    {
        $ressort = $this->group(self::GA_DEPT, 'grpressort00', 'Bau');
        $bereich = $this->group(self::GA_DEPT, 'grpbereich00', 'Holzbau', $ressort);
        $projekt = $this->group(self::GA_DEPT, 'grpprojekt00', 'Eingang', $bereich);
        $this->place(self::GA_DEPT, 'plproject000', 'Baustelle Eingang', 'bauprojekt', 47.0, 8.0, null, $projekt);
        $this->place(self::GA_DEPT, 'plfree000000', 'Frei', 'poi', 47.1, 8.1);

        $byId = array_column($this->service->forScreen($this->screen(self::GA_DEPT), true)['places'], null, 'id');

        self::assertEqualsCanonicalizing(['grpressort00', 'grpbereich00', 'grpprojekt00'], $byId['plproject000']['group_ids']);
        self::assertSame([], $byId['plfree000000']['group_ids']);
    }

    public function testRegistryDefinesMapWidgetConfigAndRejectsInvalidValues(): void
    {
        $registry = new DisplayWidgetRegistry();
        self::assertSame('ga_live', $registry->get('ga_site_map')['data_source']);

        // Bestehende Layouts (nur Titel/Filter) bleiben gültig und erhalten Standardwerte.
        $errors = [];
        $legacy = $registry->normalizeConfig('ga_site_map', ['title' => 'Lage', 'filter_ressort' => 'Bau'], 'c', $errors);
        self::assertSame([], $errors);
        self::assertSame('osm', $legacy['base_layer']);
        self::assertTrue($legacy['show_plans']);
        self::assertTrue($legacy['show_labels']);
        self::assertSame('auto', $legacy['view']);
        self::assertSame(['bauprojekt', 'anfahrt', 'lagerplatz', 'unterlager', 'poi', 'area'], $legacy['kinds']);

        $errors = [];
        $ok = $registry->normalizeConfig('ga_site_map', ['view' => 'fixed', 'center_lat' => '47.25', 'center_lng' => 8.5, 'zoom' => 17, 'kinds' => ['area', 'bauprojekt'], 'group_id' => 'grpprojekt00'], 'c', $errors);
        self::assertSame([], $errors);
        self::assertSame(47.25, $ok['center_lat']);
        self::assertSame(['bauprojekt', 'area'], $ok['kinds']);

        foreach ([['center_lat' => 91], ['kinds' => ['wald']], ['base_layer' => 'google'], ['zoom' => 40], ['plan_ids' => ['x']]] as $bad) {
            $errors = [];
            $registry->normalizeConfig('ga_site_map', $bad, 'c', $errors);
            self::assertNotSame([], $errors, json_encode($bad));
        }
    }
}
