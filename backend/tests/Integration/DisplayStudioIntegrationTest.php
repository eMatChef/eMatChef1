<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\DisplayLiveDataService;
use App\Service\Display\Layout\DisplayBlockService;
use App\Service\Display\Layout\DisplayLayoutException;
use App\Service\Display\Layout\DisplayLayoutService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Infoscreen-Studio: eigene Bausteine (Isolation, Validierung), Datenmodus der Veröffentlichung und lesende
 * Live-Daten (Department-Isolation, Leerzustand).
 */
final class DisplayStudioIntegrationTest extends TestCase
{
    private const D = 'deptsa000001';
    private const OTHER = 'deptsa000002';

    /** @param callable(object, EntityManagerInterface): void $test */
    private function withKernel(callable $test): void
    {
        $url = GrossanlassStaffingIntegrationTest::url();
        FreshKernel::run(function ($kernel) use ($test): void {
            $c = $kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $c->get('doctrine')->getManager();
            $conn = $em->getConnection();
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
            try {
                GrossanlassStaffingIntegrationTest::seed($conn);
                $em->clear();
                $test($c, $em);
            } finally {
                $conn->rollBack();
            }
        }, $url, [DisplayBlockService::class, DisplayLiveDataService::class, DisplayLayoutService::class, DepartmentDisplayScreenService::class]);
    }

    /** @return array<string, mixed> */
    private static function widgetBlock(string $widget = 'text'): array
    {
        return ['type' => 'widget', 'widget' => $widget, 'widget_version' => 1, 'config' => []];
    }

    public function testCustomBlocksAreIsolatedPerDepartmentAndValidated(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            /** @var DisplayBlockService $blocks */
            $blocks = $c->get(DisplayBlockService::class);
            $user = $em->find(User::class, 'ushsa00000mw');

            $group = ['type' => 'group', 'name' => 'Material', 'rows' => 4, 'columns' => 6, 'items' => [
                ['id' => 'i-1', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 4, 'content' => self::widgetBlock('ga_pack_progress')],
                ['id' => 'i-2', 'x' => 3, 'y' => 0, 'w' => 3, 'h' => 4, 'content' => self::widgetBlock('ga_missing_material')],
            ]];
            $created = $blocks->create(self::D, $user, 'Material-Paar', $group, 6, 4);
            self::assertSame('group', $created['kind']);
            self::assertSame(6, $created['content']['columns']);
            self::assertSame('Material', $created['content']['name']);

            // Andere Departments sehen den Baustein weder in der Liste noch per ID.
            self::assertCount(1, $blocks->list(self::D));
            self::assertSame([], $blocks->list(self::OTHER));
            foreach ([fn () => $blocks->rename(self::OTHER, $created['id'], 'Fremd'), fn () => $blocks->delete(self::OTHER, $created['id'])] as $attempt) {
                try {
                    $attempt();
                    self::fail('Fremdzugriff muss scheitern');
                } catch (DisplayLayoutException $e) {
                    self::assertSame(404, $e->httpStatus);
                }
            }

            // Verweise auf Seiten, unbekannte Widgets und leere Gruppen sind nicht speicherbar.
            foreach ([['type' => 'page', 'page_id' => 'p-main'], self::widgetBlock('script'), ['type' => 'group', 'rows' => 2, 'items' => []]] as $bad) {
                try {
                    $blocks->create(self::D, $user, 'Ungültig', $bad, 4, 4);
                    self::fail('Ungültiger Baustein wurde gespeichert');
                } catch (DisplayLayoutException $e) {
                    self::assertSame(422, $e->httpStatus);
                }
            }

            $renamed = $blocks->rename(self::D, $created['id'], 'Neu benannt');
            self::assertSame('Neu benannt', $renamed['name']);
            $blocks->delete(self::D, $created['id']);
            self::assertSame([], $blocks->list(self::D));
        });
    }

    public function testBlocksAreNotAvailableForNormalDepartments(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            $em->getConnection()->executeStatement('UPDATE department SET is_grossanlass = false WHERE id = ?', [self::OTHER]);
            $em->clear();
            $this->expectException(DisplayLayoutException::class);
            $c->get(DisplayBlockService::class)->list(self::OTHER);
        });
    }

    public function testPublishedDataModeDefaultsToLiveAndDemoMustBeExplicit(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            /** @var DepartmentDisplayScreenService $screens */
            $screens = $c->get(DepartmentDisplayScreenService::class);
            /** @var DisplayLayoutService $layouts */
            $layouts = $c->get(DisplayLayoutService::class);
            $screen = $screens->create(self::D, 'Studio', null)['screen'];

            $state = $layouts->initialize($screen, 'ga_materiallager', null, 0);
            self::assertArrayNotHasKey('data_mode', $state['draft']);

            $draft = $state['draft'];
            $draft['data_mode'] = 'demo';
            $state = $layouts->saveDraft($screen, $draft, $state['draft_version']);
            self::assertSame('demo', $state['draft']['data_mode']);
            $state = $layouts->publish($screen, $state['draft_version'], null);
            self::assertSame('demo', $layouts->payload($screen, null)['layout']['data_mode']);

            // Zurück auf Live: das Feld verschwindet, die neue Revision ist ausdrücklich nicht mehr «demo».
            $draft = $state['draft'];
            unset($draft['data_mode']);
            $state = $layouts->saveDraft($screen, $draft, $state['draft_version']);
            $state = $layouts->publish($screen, $state['draft_version'], null);
            self::assertArrayNotHasKey('data_mode', $layouts->payload($screen, null)['layout']);

            // Ungültiger Wert wird abgelehnt.
            $draft['data_mode'] = 'mixed';
            try {
                $layouts->saveDraft($screen, $draft, $state['draft_version']);
                self::fail('Ungültiger Datenmodus akzeptiert');
            } catch (DisplayLayoutException $e) {
                self::assertSame('data_mode_invalid', $e->errors[0]['code']);
            }
        });
    }

    public function testNestedGroupWithOwnGridIsValidatedAndLegacyLayoutsStayValid(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            $screens = $c->get(DepartmentDisplayScreenService::class);
            $layouts = $c->get(DisplayLayoutService::class);
            $screen = $screens->create(self::D, 'Gruppen', null)['screen'];
            $state = $layouts->initialize($screen, 'blank', null, 0);
            $draft = $state['draft'];
            $draft['pages'][0]['items'][] = ['id' => 'i-g', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'content' => [
                'type' => 'group', 'name' => 'Aussen', 'rows' => 6, 'columns' => 24, 'items' => [
                    ['id' => 'i-in', 'x' => 0, 'y' => 0, 'w' => 24, 'h' => 6, 'content' => [
                        'type' => 'group', 'rows' => 6, 'items' => [['id' => 'i-w', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 6, 'content' => self::widgetBlock('clock')]],
                    ]],
                ],
            ]];
            $state = $layouts->saveDraft($screen, $draft, $state['draft_version']);
            self::assertSame(24, $state['draft']['pages'][0]['items'][0]['content']['columns']);
            self::assertArrayNotHasKey('columns', $state['draft']['pages'][0]['items'][0]['content']['items'][0]['content']);

            // Element ausserhalb des feineren Rasters wird abgelehnt (24 Spalten, aber x+w = 25).
            $bad = $state['draft'];
            $bad['pages'][0]['items'][0]['content']['items'][0]['w'] = 25;
            try {
                $layouts->saveDraft($screen, $bad, $state['draft_version']);
                self::fail('Ausserhalb des Rasters akzeptiert');
            } catch (DisplayLayoutException $e) {
                self::assertSame('out_of_bounds', $e->errors[0]['code']);
            }
        });
    }

    public function testLiveDataIsScopedToTheScreenDepartmentAndEmptyStatesAreNotFilledWithDemoData(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            $conn = $em->getConnection();
            $conn->executeStatement("INSERT INTO \"group\" (id,department_id,name,sort_order,created_at,updated_at) VALUES ('grpsa0000002',?,'Fremdprojekt',0,now(),now())", [self::OTHER]);
            $conn->executeStatement("INSERT INTO department_grossanlass_task (id,department_id,group_id,title,sort_order,created_at) VALUES ('gtsa00000002',?,'grpsa0000002','Geheime Aufgabe',0,now())", [self::OTHER]);
            $em->clear();

            $screens = $c->get(DepartmentDisplayScreenService::class);
            /** @var DisplayLiveDataService $live */
            $live = $c->get(DisplayLiveDataService::class);
            $own = $live->forScreen($screens->create(self::D, 'Eigen', null)['screen']);
            $foreign = $live->forScreen($screens->create(self::OTHER, 'Fremd', null)['screen']);

            self::assertSame('ready', $own['state']);
            self::assertSame(['Aufbau'], array_column($own['tasks'], 'title'));
            self::assertStringNotContainsString('Geheime Aufgabe', json_encode($own, JSON_THROW_ON_ERROR));
            self::assertSame(['Geheime Aufgabe'], array_column($foreign['tasks'], 'title'));

            // Ohne Material, Transporte und Ereignisse: leere Listen statt Platzhalter.
            self::assertSame([], $own['material']['groups']);
            self::assertSame([], $own['logistics']['needs']);
            self::assertSame([], $own['logistics']['tours']);
            self::assertSame([], $own['feed']);
            self::assertSame(1, $own['task_counts']['open']);

            // Zuordnung für die Ressort-/Bereichsfilter aus der GA-Hierarchie (hier ohne Art: leer, nie geraten).
            self::assertSame(['Bühne'], array_column($own['groups'], 'name'));
            self::assertSame(['ressort' => '', 'bereich' => ''], ['ressort' => $own['tasks'][0]['ressort'], 'bereich' => $own['tasks'][0]['bereich']]);
            self::assertSame(['need' => 0, 'assigned' => 0], $own['projects'][0]['helpers']);
            self::assertSame(0, $own['projects'][0]['progress']);

            // Kein Grossanlass: keine Live-Daten.
            $conn->executeStatement('UPDATE department SET is_grossanlass = false WHERE id = ?', [self::OTHER]);
            $em->clear();
            self::assertSame(['state' => 'unavailable'], $live->forScreen($screens->create(self::OTHER, 'Normal', null)['screen']));
        });
    }

    /** @return array<string, mixed> */
    private static function rotation(): array
    {
        $page = static fn (string $w): array => ['type' => 'group', 'rows' => 6, 'items' => [['id' => 'i-1', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 6, 'content' => self::widgetBlock($w)]]];

        return ['type' => 'rotation', 'enabled' => true, 'interval_sec' => 10, 'steps' => [
            ['name' => 'Übersicht', 'duration_sec' => 5, 'content' => $page('clock')],
            ['duration_sec' => null, 'content' => self::widgetBlock('text')],
        ]];
    }

    public function testRotationPagesKeepNamesAndLegacyStepsStayValid(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            $screens = $c->get(DepartmentDisplayScreenService::class);
            $layouts = $c->get(DisplayLayoutService::class);
            $screen = $screens->create(self::D, 'Rotation', null)['screen'];
            $draft = $layouts->initialize($screen, 'blank', null, 0);
            $doc = $draft['draft'];
            $doc['pages'][0]['items'][] = ['id' => 'i-r', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 12, 'content' => self::rotation()];
            $state = $layouts->saveDraft($screen, $doc, $draft['draft_version']);
            $steps = $state['draft']['pages'][0]['items'][0]['content']['steps'];
            self::assertSame('Übersicht', $steps[0]['name']);
            self::assertSame('group', $steps[0]['content']['type']);
            // Ältere Schritte ohne Namen bleiben unverändert (kein Feld «name» ergänzt).
            self::assertArrayNotHasKey('name', $steps[1]);
            self::assertSame('widget', $steps[1]['content']['type']);
            $published = $layouts->publish($screen, $state['draft_version'], null);
            self::assertSame('Übersicht', $layouts->payload($screen, null)['layout']['pages'][0]['items'][0]['content']['steps'][0]['name']);
            self::assertSame(1, $published['published']['number']);
        });
    }

    public function testRotationCanBeSavedAsIndependentBlock(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            $blocks = $c->get(DisplayBlockService::class);
            $user = $em->find(User::class, 'ushsa00000mw');
            $created = $blocks->create(self::D, $user, 'Materiallager-Rotation', self::rotation(), 12, 8);
            self::assertSame('rotation', $created['kind']);
            self::assertCount(2, $created['content']['steps']);
            self::assertSame('Übersicht', $created['content']['steps'][0]['name']);
            self::assertSame([], $blocks->list(self::OTHER));
        });
    }

    public function testGroupHierarchyProvidesRessortAndBereichForFilters(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            $conn = $em->getConnection();
            $conn->executeStatement("INSERT INTO \"group\" (id,department_id,name,sort_order,created_at,updated_at,grossanlass_kind,parent_id) VALUES ('grpsa000ress',?,'Technik',0,now(),now(),'ressort',NULL)", [self::D]);
            $conn->executeStatement("INSERT INTO \"group\" (id,department_id,name,sort_order,created_at,updated_at,grossanlass_kind,parent_id) VALUES ('grpsa000bere',?,'Bühnen',0,now(),now(),'bereich','grpsa000ress')", [self::D]);
            $conn->executeStatement("UPDATE \"group\" SET grossanlass_kind = 'auftrag', parent_id = 'grpsa000bere' WHERE id = 'grpsa0000001'");
            $em->clear();
            $screens = $c->get(DepartmentDisplayScreenService::class);
            $data = $c->get(DisplayLiveDataService::class)->forScreen($screens->create(self::D, 'Hierarchie', null)['screen']);
            $task = $data['tasks'][0];
            self::assertSame('Technik', $task['ressort']);
            self::assertSame('Bühnen', $task['bereich']);
            self::assertSame(['Technik', 'Bühnen', 'Bühne'], $task['origin']);
        });
    }

    public function testLiveDataFeedShowsOnlyActualEventsOfTheDepartment(): void
    {
        $this->withKernel(function ($c, EntityManagerInterface $em): void {
            $conn = $em->getConnection();
            $conn->executeStatement("INSERT INTO department_grossanlass_task_event (id,task_id,type,user_id,note,created_at) VALUES ('tesa00000001','gtsa00000001','reported','ushsa00000mw',NULL,now())");
            $em->clear();
            $screens = $c->get(DepartmentDisplayScreenService::class);
            $data = $c->get(DisplayLiveDataService::class)->forScreen($screens->create(self::D, 'Feed', null)['screen']);
            self::assertCount(1, $data['feed']);
            self::assertSame('task_reported', $data['feed'][0]['kind']);
            self::assertSame('Aufbau', $data['feed'][0]['subject']);
            self::assertArrayNotHasKey('actor', $data['feed'][0]);
            unset($user);
        });
    }
}
