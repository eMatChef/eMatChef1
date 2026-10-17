<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\Activity;
use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementLineWish;
use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Grossanlass\GrossanlassAnswerCollectorService;
use App\Service\Grossanlass\GrossanlassChargeMovementService;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassCostService;
use App\Service\Grossanlass\GrossanlassProcurementCategoryBootstrapService;
use App\Service\Grossanlass\GrossanlassProcurementQuotePdfTextService;
use App\Service\Grossanlass\GrossanlassProcurementQuoteStorageService;
use App\Service\Grossanlass\GrossanlassProcurementService;
use App\Service\Grossanlass\GrossanlassWishService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Batch-Kategorisierung der Bedarfs-Wünsche: Berechtigung, bestätigtes Überschreiben und
 * dass ausschliesslich die Kategorie gesetzt wird.
 */
class GrossanlassProcurementCategorizeTest extends TestCase
{
    private const DEPARTMENT_ID = 'dep000000001';

    private Department $department;
    private User $user;
    /** @var GrossanlassAccessService&MockObject */
    private GrossanlassAccessService $access;
    /** @var array<string, ActivityGrossanlassProcurementCategory> */
    private array $categories = [];
    /** @var array<string, ActivityGrossanlassWishLine> */
    private array $wishes = [];
    private int $transactions = 0;
    private int $overviewCalls = 0;

    protected function setUp(): void
    {
        $this->department = $this->createMock(Department::class);
        $this->department->method('getId')->willReturn(self::DEPARTMENT_ID);
        $this->user = $this->createMock(User::class);
        $this->access = $this->createMock(GrossanlassAccessService::class);
        $this->access->method('canManageProcurement')->willReturn(true);
        $this->categories = [];
        $this->wishes = [];
        $this->transactions = 0;
        $this->overviewCalls = 0;
    }

    public function testRejectsUserWithoutProcurementPermission(): void
    {
        $access = $this->createMock(GrossanlassAccessService::class);
        $access->method('canManageProcurement')->willReturn(false);
        $this->access = $access;
        $new = $this->category('cat-new');
        $wish = $this->wish('w1', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Keine Berechtigung für Beschaffung');
        try {
            $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w1'], $new->getId());
        } finally {
            self::assertNull($wish->getCategory());
            self::assertSame(0, $this->transactions);
        }
    }

    public function testRejectsDifferentExistingCategoryWithoutOverwrite(): void
    {
        $old = $this->category('cat-old');
        $new = $this->category('cat-new');
        $free = $this->wish('w-free', null);
        $conflict = $this->wish('w-conflict', $old);

        try {
            $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w-free', 'w-conflict'], $new->getId());
            self::fail('Überschreiben ohne Bestätigung muss abgelehnt werden');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Überschreiben muss bestätigt werden', $e->getMessage());
        }

        // Atomar: auch der unkategorisierte Wunsch bleibt unberührt.
        self::assertNull($free->getCategory());
        self::assertSame($old, $conflict->getCategory());
        self::assertSame(0, $this->transactions);
    }

    public function testOverwriteTrueReplacesCategoryForAllSelectedWishes(): void
    {
        $old = $this->category('cat-old');
        $new = $this->category('cat-new');
        $free = $this->wish('w-free', null);
        $conflict = $this->wish('w-conflict', $old);
        $same = $this->wish('w-same', $new);

        $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w-free', 'w-conflict', 'w-same'], $new->getId(), true);

        self::assertSame($new, $free->getCategory());
        self::assertSame($new, $conflict->getCategory());
        self::assertSame($new, $same->getCategory());
        self::assertSame(1, $this->transactions);
        self::assertSame(1, $this->overviewCalls);
    }

    public function testSameCategoryNeedsNoOverwriteConfirmation(): void
    {
        $new = $this->category('cat-new');
        $same = $this->wish('w-same', $new);
        $free = $this->wish('w-free', null);

        $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w-same', 'w-free'], $new->getId());

        self::assertSame($new, $same->getCategory());
        self::assertSame($new, $free->getCategory());
    }

    public function testOnlyTheCategoryIsWrittenToTheWish(): void
    {
        $new = $this->category('cat-new');
        $wish = $this->createMock(ActivityGrossanlassWishLine::class);
        $this->prepareWish($wish, 'w1', null, self::DEPARTMENT_ID, false);
        // Nur setCategory ist erlaubt: jede andere Schreiboperation (Name, Menge, Beschrieb …) schlägt fehl.
        $wish->expects(self::once())->method('setCategory')->with($new);
        foreach (['setLabel', 'setName', 'setQuantity', 'setDescription', 'setNotes'] as $setter) {
            if (method_exists(ActivityGrossanlassWishLine::class, $setter)) {
                $wish->expects(self::never())->method($setter);
            }
        }
        $this->wishes['w1'] = $wish;

        $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w1'], $new->getId());
    }

    public function testRejectsCategoryOfAnotherDepartment(): void
    {
        $foreign = $this->createMock(ActivityGrossanlassProcurementCategory::class);
        $foreign->method('getId')->willReturn('cat-foreign');
        $foreign->method('getDepartmentId')->willReturn('dep-other');
        $this->categories['cat-foreign'] = $foreign;
        $wish = $this->wish('w1', null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Kategorie nicht gefunden');
        try {
            $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w1'], 'cat-foreign');
        } finally {
            self::assertNull($wish->getCategory());
        }
    }

    public function testRejectsWishOfAnotherDepartmentAndEmptySelection(): void
    {
        $new = $this->category('cat-new');
        $this->wish('w-foreign', null, 'dep-other');

        try {
            $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w-foreign'], $new->getId());
            self::fail('Fremder Wunsch muss abgelehnt werden');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Wunsch nicht gefunden', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Keine Wünsche ausgewählt');
        $this->service()->categorizeBedarfWishes($this->department, $this->user, [], $new->getId());
    }

    public function testRejectsWishThatIsNoLongerInBedarfStatus(): void
    {
        $new = $this->category('cat-new');
        $wish = $this->wish('w1', null);
        $line = $this->createMock(ActivityGrossanlassProcurementLine::class);
        $line->method('getStatus')->willReturn('ordered');
        $link = $this->createMock(ActivityGrossanlassProcurementLineWish::class);
        $link->method('getProcurementLine')->willReturn($line);
        $line->method('getDepartmentId')->willReturn(self::DEPARTMENT_ID);

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->service([$link])->categorizeBedarfWishes($this->department, $this->user, ['w1'], $new->getId());
        } finally {
            self::assertNull($wish->getCategory());
        }
    }

    public function testResponsibilityCanBeSetWithoutTouchingTheCategory(): void
    {
        $old = $this->category('cat-old');
        $wish = $this->wish('w1', $old);

        $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w1'], null, false, ActivityGrossanlassWishLine::RESPONSIBILITY_CENTRAL);

        self::assertSame($old, $wish->getCategory());
        self::assertSame(ActivityGrossanlassWishLine::RESPONSIBILITY_CENTRAL, $wish->getProcurementResponsibility());
    }

    public function testCategorizeAndResponsibilityTogetherCreateNoProcurementLine(): void
    {
        $new = $this->category('cat-new');
        $wish = $this->wish('w1', null);

        $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w1'], $new->getId(), false, ActivityGrossanlassWishLine::RESPONSIBILITY_DEPARTMENT);

        self::assertSame($new, $wish->getCategory());
        self::assertSame(ActivityGrossanlassWishLine::RESPONSIBILITY_DEPARTMENT, $wish->getProcurementResponsibility());
    }

    public function testDifferentExistingResponsibilityNeedsConfirmationAndIsAtomic(): void
    {
        $new = $this->category('cat-new');
        $free = $this->wish('w-free', null);
        $set = $this->wish('w-set', null);
        $set->setProcurementResponsibility(ActivityGrossanlassWishLine::RESPONSIBILITY_CENTRAL);

        try {
            $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w-free', 'w-set'], $new->getId(), false, ActivityGrossanlassWishLine::RESPONSIBILITY_DEPARTMENT);
            self::fail('Überschreiben der Zuständigkeit ohne Bestätigung muss abgelehnt werden');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Überschreiben muss bestätigt werden', $e->getMessage());
        }

        self::assertNull($free->getProcurementResponsibility());
        self::assertNull($free->getCategory());
        self::assertSame(ActivityGrossanlassWishLine::RESPONSIBILITY_CENTRAL, $set->getProcurementResponsibility());
        self::assertSame(0, $this->transactions);
    }

    public function testDepartmentResponsibilityRejectedForWishAlreadyInPosition(): void
    {
        $this->wish('w1', null);
        $line = $this->createMock(ActivityGrossanlassProcurementLine::class);
        $line->method('getStatus')->willReturn(ActivityGrossanlassProcurementLine::STATUS_BEDARF);
        $line->method('getDepartmentId')->willReturn(self::DEPARTMENT_ID);
        $link = $this->createMock(ActivityGrossanlassProcurementLineWish::class);
        $link->method('getProcurementLine')->willReturn($line);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('bereits einer Beschaffungsposition zugeordnet');
        $this->service([$link])->categorizeBedarfWishes($this->department, $this->user, ['w1'], null, false, ActivityGrossanlassWishLine::RESPONSIBILITY_DEPARTMENT);
    }

    public function testRequiresCategoryOrResponsibilityAndRejectsInvalidResponsibility(): void
    {
        $this->wish('w1', null);
        try {
            $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w1'], null);
            self::fail();
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('erforderlich', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Beschaffungszuständigkeit ist ungültig');
        $this->service()->categorizeBedarfWishes($this->department, $this->user, ['w1'], null, false, 'everyone');
    }

    private function category(string $id, string $departmentId = self::DEPARTMENT_ID): ActivityGrossanlassProcurementCategory
    {
        $category = $this->createMock(ActivityGrossanlassProcurementCategory::class);
        $category->method('getId')->willReturn($id);
        $category->method('getDepartmentId')->willReturn($departmentId);

        return $this->categories[$id] = $category;
    }

    private function wish(string $id, ?ActivityGrossanlassProcurementCategory $category, string $departmentId = self::DEPARTMENT_ID): ActivityGrossanlassWishLine
    {
        $wish = $this->createMock(ActivityGrossanlassWishLine::class);
        $this->prepareWish($wish, $id, $category, $departmentId);

        return $this->wishes[$id] = $wish;
    }

    /** Zustandsbehafteter Wunsch: setCategory/getCategory teilen sich eine Variable. */
    private function prepareWish(ActivityGrossanlassWishLine&MockObject $wish, string $id, ?ActivityGrossanlassProcurementCategory $category, string $departmentId = self::DEPARTMENT_ID, bool $stubSetter = true): void
    {
        $activity = $this->createMock(Activity::class);
        $activity->method('getDepartmentId')->willReturn($departmentId);
        $round = $this->createMock(ActivityGrossanlassRound::class);
        $round->method('getActivity')->willReturn($activity);
        $round->method('getFormPurpose')->willReturn(ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH);
        $wish->method('getRound')->willReturn($round);
        $wish->method('getId')->willReturn($id);
        $state = new \stdClass();
        $state->category = $category;
        $wish->method('getCategory')->willReturnCallback(static fn () => $state->category);
        $state->responsibility = null;
        $wish->method('getProcurementResponsibility')->willReturnCallback(static fn () => $state->responsibility);
        $wish->method('setProcurementResponsibility')->willReturnCallback(static function (?string $r) use ($state, $wish) {
            $state->responsibility = $r;

            return $wish;
        });
        if ($stubSetter) {
            $wish->method('setCategory')->willReturnCallback(static function (?ActivityGrossanlassProcurementCategory $c) use ($state, $wish) {
            $state->category = $c;

            return $wish;
            });
        }
    }

    /**
     * @param list<ActivityGrossanlassProcurementLineWish> $links
     */
    private function service(array $links = []): GrossanlassProcurementService
    {
        $categories = $this->categories;
        $wishes = $this->wishes;

        $categoryRepo = $this->createMock(EntityRepository::class);
        $categoryRepo->method('find')->willReturnCallback(static fn (string $id) => $categories[$id] ?? null);
        $wishRepo = $this->createMock(EntityRepository::class);
        $wishRepo->method('find')->willReturnCallback(static fn (string $id) => $wishes[$id] ?? null);
        $linkRepo = $this->createMock(EntityRepository::class);
        $linkRepo->method('findBy')->willReturn($links);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRepository')->willReturnCallback(static fn (string $class) => match ($class) {
            ActivityGrossanlassProcurementCategory::class => $categoryRepo,
            ActivityGrossanlassWishLine::class => $wishRepo,
            ActivityGrossanlassProcurementLineWish::class => $linkRepo,
            default => throw new \LogicException('Unerwartetes Repository '.$class),
        });
        $em->method('wrapInTransaction')->willReturnCallback(function (callable $callback) {
            ++$this->transactions;

            return $callback();
        });

        $service = $this->getMockBuilder(GrossanlassProcurementService::class)
            ->setConstructorArgs([
                $em,
                $this->access,
                $this->createMock(GrossanlassProcurementQuoteStorageService::class),
                $this->createMock(GrossanlassProcurementQuotePdfTextService::class),
                $this->createMock(GrossanlassAnswerCollectorService::class),
                $this->createMock(GrossanlassWishService::class),
                $this->createMock(GrossanlassCostService::class),
                $this->createMock(GrossanlassProcurementCategoryBootstrapService::class),
                $this->createMock(GrossanlassCommitmentService::class),
                $this->createMock(GrossanlassChargeMovementService::class),
            ])
            ->onlyMethods(['getBedarfOverview'])
            ->getMock();
        $service->method('getBedarfOverview')->willReturnCallback(function () {
            ++$this->overviewCalls;

            return ['pool' => [], 'lines' => [], 'categories' => [], 'suggestions' => []];
        });

        return $service;
    }
}
