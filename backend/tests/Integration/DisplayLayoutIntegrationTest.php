<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\DepartmentDisplayDevice;
use App\Entity\DepartmentDisplayScreen;
use App\Service\Display\DepartmentDisplayDeviceService;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\DisplayAccessCodeGenerator;
use App\Service\Display\Layout\DisplayDeviceProfileService;
use App\Service\Display\Layout\DisplayLayoutException;
use App\Service\Display\Layout\DisplayLayoutService;
use App\Service\Display\Layout\DisplayLayoutTemplateCatalog;
use App\Service\Display\Layout\DisplayLayoutValidator;
use App\Service\Display\Layout\DisplayWidgetRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Entwurf, Veröffentlichung, Revisionen und Auslieferung der Infoscreen-Layouts gegen eine isolierte Test-Datenbank.
 * GA_DEPT ist ein Grossanlass, DEPT ein normales Department.
 */
final class DisplayLayoutIntegrationTest extends TestCase
{
    use IsolatedDatabase;

    private const DEPT = 'b57aa6184ef5';
    private const GA_DEPT = 'dccffd078d5c';
    private const OTHER_GA = 'a1b2c3d4e5f6';

    private EntityManagerInterface $em;
    private DepartmentDisplayScreenService $screens;
    private DepartmentDisplayDeviceService $devices;
    private DisplayLayoutService $layouts;

    protected function setUp(): void
    {
        $this->em = $this->isolatedEntityManager([self::DEPT, self::GA_DEPT, self::OTHER_GA]);
        $this->em->getConnection()->executeStatement('UPDATE department SET is_grossanlass = true WHERE id IN (?, ?)', [self::GA_DEPT, self::OTHER_GA]);
        $this->screens = new DepartmentDisplayScreenService($this->em, new DisplayAccessCodeGenerator(), 'https://app.ematchef.test');
        $this->devices = new DepartmentDisplayDeviceService($this->em, $this->screens, true);
        $registry = new DisplayWidgetRegistry();
        $this->layouts = new DisplayLayoutService(
            $this->em,
            new DisplayLayoutValidator($registry),
            new DisplayLayoutTemplateCatalog(),
            $registry,
            new DisplayDeviceProfileService(),
            $this->screens,
        );
    }

    protected function tearDown(): void
    {
        $this->releaseIsolatedDatabase($this->em ?? null);
    }

    private function screen(string $department = self::GA_DEPT, string $name = 'Screen'): DepartmentDisplayScreen
    {
        return $this->screens->create($department, $name, null)['screen'];
    }

    private function device(DepartmentDisplayScreen $screen, ?array $profile = null): DepartmentDisplayDevice
    {
        ['device' => $device] = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING);
        if ($profile !== null) {
            $device->setDisplayProfile($profile);
            $this->em->flush();
        }

        return $device;
    }

    /** @return array<string, mixed> */
    private function templateLayout(string $id = 'ga_materiallager'): array
    {
        return (new DisplayLayoutTemplateCatalog())->find($id)['layout'];
    }

    public function testNormalDepartmentKeepsCheckboxSystemAndHasNoLayouts(): void
    {
        $screen = $this->screen(self::DEPT);
        self::assertNull($this->layouts->payload($screen, null));
        self::assertFalse($this->layouts->catalog(self::DEPT)['enabled']);
        self::assertSame([], $this->layouts->catalog(self::DEPT)['templates']);

        $this->expectException(DisplayLayoutException::class);
        $this->layouts->state($screen);
    }

    public function testCatalogOnlyOffersGrossanlassWidgetsAndTemplatesToGrossanlass(): void
    {
        $catalog = $this->layouts->catalog(self::GA_DEPT);
        $types = array_column($catalog['widgets'], 'type');
        self::assertContains('ga_projects', $types);
        self::assertContains('text', $types);
        self::assertContains('ga_materiallager', array_column($catalog['templates'], 'id'));
    }

    public function testScreenWithoutPublishedRevisionIsPassiveAndLeaksNothing(): void
    {
        $screen = $this->screen();
        $device = $this->device($screen);
        self::assertSame(['state' => 'unpublished', 'source' => 'published'], $this->layouts->payload($screen, $device));

        $this->layouts->initialize($screen, 'ga_materiallager', null, 0);
        // Entwurf vorhanden, aber nicht veröffentlicht: das Gerät sieht weiterhin nichts davon.
        $payload = $this->layouts->payload($screen, $device);
        self::assertSame('unpublished', $payload['state']);
        self::assertArrayNotHasKey('layout', $payload);
        self::assertSame('ready', $this->layouts->payload($screen, null, 'draft')['state']);
    }

    public function testDraftChangesAreInvisibleToDevicesUntilPublished(): void
    {
        $screen = $this->screen();
        $device = $this->device($screen);
        $state = $this->layouts->initialize($screen, 'ga_baubuero', null, 0);
        $state = $this->layouts->publish($screen, $state['draft_version'], null);
        self::assertSame(1, $state['published']['number']);
        $first = $this->layouts->payload($screen, $device);
        self::assertSame('ready', $first['state']);
        self::assertSame(1, $first['revision']);

        $draft = $state['draft'];
        $draft['variants'][0]['name'] = 'Geändert';
        $state = $this->layouts->saveDraft($screen, $draft, $state['draft_version']);
        self::assertTrue($state['has_unpublished_changes']);
        self::assertSame('Standard 16:9', $this->layouts->payload($screen, $device)['layout']['variants'][0]['name']);

        $state = $this->layouts->publish($screen, $state['draft_version'], null);
        self::assertSame(2, $state['published']['number']);
        self::assertFalse($state['has_unpublished_changes']);
        // Das Gerät übernimmt die neue Revision beim nächsten regulären Abruf, ohne erneute Kopplung.
        $second = $this->layouts->payload($screen, $device);
        self::assertSame(2, $second['revision']);
        self::assertSame('Geändert', $second['layout']['variants'][0]['name']);
    }

    public function testPublishedRevisionsAreImmutableAndCanBeRestored(): void
    {
        $screen = $this->screen();
        $state = $this->layouts->initialize($screen, 'ga_projekt', null, 0);
        $state = $this->layouts->publish($screen, $state['draft_version'], null);
        $draft = $state['draft'];
        $draft['variants'][0]['name'] = 'Zweite Fassung';
        $state = $this->layouts->saveDraft($screen, $draft, $state['draft_version']);
        $state = $this->layouts->publish($screen, $state['draft_version'], null);

        self::assertCount(2, $this->layouts->listRevisions($screen));
        self::assertSame('Standard 16:9', $this->layouts->revisionDetail($screen, 1)['layout']['variants'][0]['name']);

        // Wiederherstellen: Entwurf = Revision 1; veröffentlicht entsteht Revision 3, die Historie bleibt.
        $state = $this->layouts->restore($screen, 1, $state['draft_version'], true, null);
        self::assertSame(3, $state['published']['number']);
        self::assertSame(1, $state['published']['restored_from_number']);
        self::assertSame('Standard 16:9', $this->layouts->payload($screen, null)['layout']['variants'][0]['name']);
        self::assertSame('Zweite Fassung', $this->layouts->revisionDetail($screen, 2)['layout']['variants'][0]['name']);
        self::assertCount(3, $this->layouts->listRevisions($screen));
    }

    public function testRestoreWithoutPublishOnlyReplacesTheDraft(): void
    {
        $screen = $this->screen();
        $state = $this->layouts->initialize($screen, 'ga_projekt', null, 0);
        $state = $this->layouts->publish($screen, $state['draft_version'], null);
        $draft = $state['draft'];
        $draft['variants'][0]['name'] = 'Neu';
        $state = $this->layouts->saveDraft($screen, $draft, $state['draft_version']);
        $state = $this->layouts->restore($screen, 1, $state['draft_version'], false, null);

        self::assertSame('Standard 16:9', $state['draft']['variants'][0]['name']);
        self::assertSame(1, $state['published']['number']);
        self::assertFalse($state['has_unpublished_changes']);
    }

    public function testOptimisticVersionCheckPreventsSilentOverwrite(): void
    {
        $screen = $this->screen();
        $state = $this->layouts->initialize($screen, 'ga_projekt', null, 0);
        $version = $state['draft_version'];
        $this->layouts->saveDraft($screen, $state['draft'], $version);

        try {
            $this->layouts->saveDraft($screen, $state['draft'], $version);
            self::fail('Konflikt erwartet');
        } catch (DisplayLayoutException $e) {
            self::assertSame(409, $e->httpStatus);
            self::assertSame($version + 1, $e->extra['current_version']);
        }
        try {
            $this->layouts->publish($screen, $version, null);
            self::fail('Konflikt erwartet');
        } catch (DisplayLayoutException $e) {
            self::assertSame('version_conflict', $e->errorCode);
        }
    }

    public function testInvalidDraftsAreRejectedAndNotStored(): void
    {
        $screen = $this->screen();
        $state = $this->layouts->initialize($screen, null, null, 0);
        $bad = $state['draft'];
        $bad['pages'][0]['items'][] = ['id' => 'x', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'content' => ['type' => 'widget', 'widget' => 'text', 'widget_version' => 1, 'config' => ['onclick' => 'alert(1)']]];
        try {
            $this->layouts->saveDraft($screen, $bad, $state['draft_version']);
            self::fail('Validierungsfehler erwartet');
        } catch (DisplayLayoutException $e) {
            self::assertSame(422, $e->httpStatus);
        }
        $this->em->refresh($screen);
        self::assertSame([], $screen->getLayoutDraft()['pages'][0]['items']);
        self::assertSame($state['draft_version'], $screen->getLayoutDraftVersion());
    }

    public function testBlankDraftCannotBePublished(): void
    {
        $screen = $this->screen();
        $state = $this->layouts->initialize($screen, null, null, 0);
        $this->expectException(DisplayLayoutException::class);
        $this->layouts->publish($screen, $state['draft_version'], null);
    }

    public function testTemplateCopiesAreIndependentOfEachOtherAndOfTheCatalog(): void
    {
        $a = $this->screen(self::GA_DEPT, 'A');
        $b = $this->screen(self::GA_DEPT, 'B');
        $stateA = $this->layouts->initialize($a, 'ga_disposition', null, 0);
        $stateB = $this->layouts->initialize($b, 'ga_disposition', null, 0);
        $draft = $stateA['draft'];
        $draft['pages'][0]['items'] = [];
        $this->layouts->saveDraft($a, $draft, $stateA['draft_version']);

        $this->em->refresh($b);
        self::assertNotSame([], $b->getLayoutDraft()['pages'][0]['items']);
        self::assertSame(['template_id' => 'ga_disposition', 'template_version' => 2], $stateB['origin']);
        self::assertNotSame([], $this->templateLayout('ga_disposition')['pages'][0]['items']);
    }

    public function testDuplicateFromAnotherScreenOnlyWithinTheSameDepartment(): void
    {
        $source = $this->screen(self::GA_DEPT, 'Quelle');
        $this->layouts->initialize($source, 'ga_materiallager', null, 0);
        $copy = $this->screen(self::GA_DEPT, 'Kopie');
        $state = $this->layouts->initialize($copy, null, $source->getId(), 0);
        self::assertSame(['screen_id' => $source->getId()], $state['origin']);
        self::assertSame($this->layouts->state($source)['draft'], $state['draft']);

        $foreign = $this->screen(self::OTHER_GA, 'Fremd');
        $this->layouts->initialize($foreign, 'ga_materiallager', null, 0);
        try {
            $this->layouts->initialize($copy, null, $foreign->getId(), $state['draft_version']);
            self::fail('Fremdes Department darf nicht kopierbar sein');
        } catch (DisplayLayoutException $e) {
            self::assertSame(404, $e->httpStatus);
        }
    }

    public function testProfileSelectsVariantAndFallsBackToDefault(): void
    {
        $screen = $this->screen();
        $state = $this->layouts->initialize($screen, 'ga_materiallager', null, 0);
        $draft = $state['draft'];
        $draft['variants'][] = ['id' => 'v-led', 'name' => 'LED-Wall 3:2', 'aspect_ratio' => '3:2', 'root' => ['type' => 'page', 'page_id' => 'p-compact']];
        $state = $this->layouts->saveDraft($screen, $draft, $state['draft_version']);
        $this->layouts->publish($screen, $state['draft_version'], null);

        $tv = $this->device($screen, ['pixel_width' => 1920, 'pixel_height' => 1080, 'aspect_ratio' => null, 'variant_id' => null, 'font_profile' => 'auto', 'viewing_distance_m' => null, 'width_cm' => null, 'height_cm' => null]);
        $led = $this->device($screen, ['pixel_width' => 3000, 'pixel_height' => 2000, 'aspect_ratio' => null, 'variant_id' => null, 'font_profile' => 'large', 'viewing_distance_m' => null, 'width_cm' => 300, 'height_cm' => 200]);
        $removed = $this->device($screen, ['variant_id' => 'gibt-es-nicht']);

        self::assertSame('v-std', $this->layouts->payload($screen, $tv)['variant_id']);
        $ledPayload = $this->layouts->payload($screen, $led);
        self::assertSame('v-led', $ledPayload['variant_id']);
        self::assertSame(1.25, $ledPayload['font_scale']);
        self::assertSame('v-std', $this->layouts->payload($screen, $removed)['variant_id']);
        self::assertSame('v-std', $this->layouts->payload($screen, null)['variant_id']);
    }

    public function testDeletingAScreenRemovesItsRevisions(): void
    {
        $screen = $this->screen();
        $state = $this->layouts->initialize($screen, 'ga_projekt', null, 0);
        $this->layouts->publish($screen, $state['draft_version'], null);
        $this->screens->revoke($screen);
        $this->screens->deletePermanently($screen, null);

        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM department_display_layout_revision WHERE screen_id = ?', [$screen->getId()]));
    }
}
