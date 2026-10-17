<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassWishLine;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Grossanlass\GrossanlassAnswerCollectorService;
use App\Service\Grossanlass\GrossanlassChargeMovementService;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassCostService;
use App\Service\Grossanlass\GrossanlassProcurementCategoryBootstrapService;
use App\Service\Grossanlass\GrossanlassProcurementQuotePdfTextService;
use App\Service\Grossanlass\GrossanlassProcurementQuoteStorageService;
use App\Service\Grossanlass\GrossanlassProcurementService;
use App\Service\Grossanlass\GrossanlassQuantityConfirmationRequiredException;
use App\Service\Grossanlass\GrossanlassWishService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Übernahme-Regeln: eindeutige zentrale Zuständigkeit, keine stillschweigend summierten Einheiten,
 * manuell festgelegte Mengen nur nach Bestätigung.
 */
class GrossanlassProcurementTakeoverGuardTest extends TestCase
{
    private function wish(?string $responsibility, int $qty = 5, string $unit = 'Stk', string $label = 'Stehtisch'): ActivityGrossanlassWishLine
    {
        $wish = new ActivityGrossanlassWishLine();
        $wish->setLabel($label)->setQuantity($qty)->setQuantityUnit($unit);
        $wish->setProcurementResponsibility($responsibility);

        return $wish;
    }

    private function line(int $quantity, ?int $asked = null): ActivityGrossanlassProcurementLine
    {
        $line = new ActivityGrossanlassProcurementLine();
        $line->setQuantity($quantity);
        $line->setQuantityAsked($asked);

        return $line;
    }

    private function service(?ActivityGrossanlassProcurementLine $findableLine = null): GrossanlassProcurementService
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([]);
        $repo->method('find')->willReturn($findableLine);
        $em = $this->createMock(EntityManager::class);
        $em->method('getRepository')->willReturn($repo);
        $access = $this->createMock(GrossanlassAccessService::class);
        $access->method('canManageProcurement')->willReturn(true);

        return new GrossanlassProcurementService(
            $em,
            $access,
            $this->createMock(GrossanlassProcurementQuoteStorageService::class),
            $this->createMock(GrossanlassProcurementQuotePdfTextService::class),
            $this->createMock(GrossanlassAnswerCollectorService::class),
            $this->createMock(GrossanlassWishService::class),
            $this->createMock(GrossanlassCostService::class),
            $this->createMock(GrossanlassProcurementCategoryBootstrapService::class),
            $this->createMock(GrossanlassCommitmentService::class),
            $this->createMock(GrossanlassChargeMovementService::class),
        );
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod(GrossanlassProcurementService::class, $method);

        return $ref->invoke($this->service(), ...$args);
    }

    public function testCentralWishPasses(): void
    {
        $this->call('assertCentralResponsibility', [$this->wish(ActivityGrossanlassWishLine::RESPONSIBILITY_CENTRAL)]);
        $this->addToAssertionCount(1);
    }

    public function testUndecidedWishIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('noch nicht festgelegt');
        $this->call('assertCentralResponsibility', [$this->wish(null)]);
    }

    public function testDepartmentWishIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('vom Ressort selbst beschafft');
        $this->call('assertCentralResponsibility', [$this->wish(ActivityGrossanlassWishLine::RESPONSIBILITY_DEPARTMENT)]);
    }

    public function testLegacySelfOrganizedWishCountsAsDepartment(): void
    {
        $wish = new ActivityGrossanlassWishLine();
        $wish->setSelfOrganized(true);
        self::assertSame(ActivityGrossanlassWishLine::RESPONSIBILITY_DEPARTMENT, $wish->getProcurementResponsibility());
        $wish->setSelfOrganized(false);
        self::assertNull($wish->getProcurementResponsibility());
    }

    public function testIncompatibleUnitsAreNotSummed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Einheiten sind nicht kompatibel');
        $this->call('assertCompatibleUnits', [$this->wish('central', 5, 'Stk'), $this->wish('central', 3, 'm')]);
    }

    public function testSameUnitIgnoringCaseIsCompatible(): void
    {
        $this->call('assertCompatibleUnits', [$this->wish('central', 5, 'Stk'), $this->wish('central', 3, 'stk')]);
        $this->addToAssertionCount(1);
    }

    public function testDerivedQuantityNeedsNoConfirmation(): void
    {
        $data = $this->call('guardManualQuantity', $this->line(5), [$this->wish('central', 5)], [$this->wish('central', 2)], 0, []);
        self::assertSame([], $data);
    }

    public function testManualQuantityRequiresConfirmation(): void
    {
        try {
            $this->call('guardManualQuantity', $this->line(12), [$this->wish('central', 10)], [$this->wish('central', 2)], 0, []);
            self::fail('Manuelle Menge darf nicht ohne Bestätigung verändert werden');
        } catch (GrossanlassQuantityConfirmationRequiredException $e) {
            self::assertSame(12, $e->currentQuantity);
            self::assertSame(10, $e->sourceQuantity);
            self::assertSame(2, $e->addedQuantity);
            self::assertFalse($e->frozen);
            self::assertSame('quantity_confirmation_required', $e->toPayload()['code']);
        }
    }

    public function testConfirmedManualQuantityAddsOnTopOfManualTotal(): void
    {
        $data = $this->call('guardManualQuantity', $this->line(12), [$this->wish('central', 10)], [$this->wish('central', 2)], 0, ['confirm_quantity_change' => true]);
        self::assertSame(14, $data['quantity']);
    }

    public function testFrozenQuantityRequiresConfirmationAndStaysFrozen(): void
    {
        $line = $this->line(10, 10);
        try {
            $this->call('guardManualQuantity', $line, [$this->wish('central', 10)], [$this->wish('central', 2)], 0, []);
            self::fail();
        } catch (GrossanlassQuantityConfirmationRequiredException $e) {
            self::assertTrue($e->frozen);
        }
        $data = $this->call('guardManualQuantity', $line, [$this->wish('central', 10)], [$this->wish('central', 2)], 0, ['confirm_quantity_change' => true]);
        self::assertArrayNotHasKey('quantity', $data);
    }

    public function testPayloadProposesNewTotalForManualQuantity(): void
    {
        try {
            $this->call('guardManualQuantity', $this->line(12), [$this->wish('central', 10)], [$this->wish('central', 2)], 0, []);
            self::fail();
        } catch (GrossanlassQuantityConfirmationRequiredException $e) {
            self::assertSame(14, $e->toPayload()['proposed_quantity']);
        }
    }

    public function testFrozenPayloadHasNoProposal(): void
    {
        try {
            $this->call('guardManualQuantity', $this->line(10, 10), [$this->wish('central', 10)], [$this->wish('central', 2)], 0, []);
            self::fail();
        } catch (GrossanlassQuantityConfirmationRequiredException $e) {
            self::assertNull($e->toPayload()['proposed_quantity']);
        }
    }

    public function testConfirmedManualQuantityHonoursDeliberateTotal(): void
    {
        $data = $this->call('guardManualQuantity', $this->line(12), [$this->wish('central', 10)], [$this->wish('central', 2)], 0, ['confirm_quantity_change' => true, 'quantity' => 20]);
        self::assertSame(20, $data['quantity']);
    }

    public function testDeliberateTotalBelowOneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->call('guardManualQuantity', $this->line(12), [$this->wish('central', 10)], [$this->wish('central', 2)], 0, ['confirm_quantity_change' => true, 'quantity' => 0]);
    }

    public function testFrozenQuantityCannotBeOverriddenEvenWhenConfirmed(): void
    {
        $data = $this->call('guardManualQuantity', $this->line(10, 10), [$this->wish('central', 10)], [$this->wish('central', 2)], 0, ['confirm_quantity_change' => true, 'quantity' => 99]);
        self::assertArrayNotHasKey('quantity', $data);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function lockedStatuses(): array
    {
        return [
            'bestellt' => [ActivityGrossanlassProcurementLine::STATUS_BESTELLT],
            'teilweise erhalten' => [ActivityGrossanlassProcurementLine::STATUS_TEILWEISE],
            'erhalten' => [ActivityGrossanlassProcurementLine::STATUS_ERHALTEN],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lockedStatuses')]
    public function testOrderedOrCompletedLinesTakeNoWishes(string $status): void
    {
        $department = $this->createMock(\App\Entity\Department::class);
        $department->method('getId')->willReturn('dep000000001');
        $line = $this->createMock(ActivityGrossanlassProcurementLine::class);
        $line->method('getDepartmentId')->willReturn('dep000000001');
        $line->method('getStatus')->willReturn($status);
        $line->expects(self::never())->method('setQuantity');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('keinen Wunsch mehr aufnehmen');
        $this->service($line)->addWishesToLine($department, $this->createMock(\App\Entity\User::class), 'gp0000000001', ['w1'], ['confirm_quantity_change' => true, 'quantity' => 5]);
    }

    public function testResponsibilityAndSelfOrganizedStayConsistent(): void
    {
        $wish = new ActivityGrossanlassWishLine();
        $wish->setProcurementResponsibility(ActivityGrossanlassWishLine::RESPONSIBILITY_DEPARTMENT);
        self::assertTrue($wish->isSelfOrganized());
        $wish->setProcurementResponsibility(ActivityGrossanlassWishLine::RESPONSIBILITY_CENTRAL);
        self::assertFalse($wish->isSelfOrganized());
        $wish->setProcurementResponsibility(null);
        self::assertFalse($wish->isSelfOrganized());
        self::assertNull($wish->getProcurementResponsibility());
    }
}
