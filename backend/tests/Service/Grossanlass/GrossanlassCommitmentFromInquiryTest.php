<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Grossanlass\GrossanlassAvailabilityService;
use App\Service\Grossanlass\GrossanlassChargeMovementService;
use App\Service\Grossanlass\GrossanlassCommitmentService;
use App\Service\Grossanlass\GrossanlassCostService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Zusage aus Anfrage: Artikel stammt aus der Bedarfszeile, die Firma bleibt Quelle (nie Artikelname).
 */
final class GrossanlassCommitmentFromInquiryTest extends TestCase
{
    private const DEPARTMENT_ID = 'dep000000001';

    /** @var array<string, ActivityGrossanlassProcurementLine> */
    private array $lines = [];
    private ?DepartmentGrossanlassCommitment $existing = null;
    /** @var list<DepartmentGrossanlassCommitment> */
    private array $covering = [];
    /** @var list<object> */
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->lines = [];
        $this->existing = null;
        $this->covering = [];
        $this->persisted = [];
    }

    public function testSingleLineGivesArticleNameQuantityAndFirmAsSource(): void
    {
        $this->addLine('gp0000000001', 'Schalungsplatten', 'material', 12, 'gcMat');
        $row = $this->ensure($this->inquiry(['gp0000000001']));

        self::assertSame('Schalungsplatten', $row->getName());
        self::assertSame('Maki Landtechnik', $row->getSource());
        self::assertSame(DepartmentGrossanlassCommitment::FAMILY_MATERIAL, $row->getFamily());
        self::assertSame(12, $row->getQuantity());
        self::assertSame('gcMat', $row->getCategoryId());
    }

    public function testVehicleLineStaysVehicleDespiteCategoryHebezeug(): void
    {
        $this->addLine('gp0000000002', 'Teleskoplader', 'fahrzeug', 1, 'gcHebezeug');
        $row = $this->ensure($this->inquiry(['gp0000000002'], ['gcOther', 'gcHebezeug']));

        self::assertSame('Teleskoplader', $row->getName());
        self::assertSame(DepartmentGrossanlassCommitment::FAMILY_VEHICLE, $row->getFamily());
        self::assertSame('gcHebezeug', $row->getCategoryId());
        self::assertSame('Maki Landtechnik', $row->getSource());
    }

    public function testSeveralLinesAreNotGuessedAndFallBackToOffering(): void
    {
        $this->addLine('gp0000000003', 'Teleskoplader', 'fahrzeug', 1, 'gcA');
        $this->addLine('gp0000000004', 'Traktor', 'fahrzeug', 1, 'gcA');
        $row = $this->ensure($this->inquiry(['gp0000000003', 'gp0000000004']));

        self::assertSame('Miete von Teleskopladern und Traktoren', $row->getName());
        self::assertNotSame('Maki Landtechnik', $row->getName());
        self::assertSame(1, $row->getQuantity());
    }

    public function testNoLineAndNoOfferingIsRejectedAndLeavesInquiryUntouched(): void
    {
        $inquiry = $this->inquiry([], [], '');
        $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ANTWORT);
        try {
            $this->service($inquiry)->ensureFromInquiry($this->department(), $this->createMock(User::class), 'iq0000000001');
            self::fail('Erwartet: InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            self::assertSame(DepartmentGrossanlassInquiry::STATUS_ANTWORT, $inquiry->getStatus());
            self::assertSame([], $this->persisted);
        }
    }

    public function testSingleLineIsLinkedToTheCommitment(): void
    {
        $this->addLine('gp0000000002', 'Teleskoplader', 'fahrzeug', 1, 'gcA');
        $row = $this->ensure($this->inquiry(['gp0000000002']));

        self::assertSame('gp0000000002', $row->getProcurementLineId());
        self::assertSame('gp0000000002', $row->getItemDetails()['from_line_id'] ?? null);
    }

    public function testAlreadyCoveredLineIsNotOvercoveredAndStaysUnlinked(): void
    {
        $this->addLine('gp0000000002', 'Teleskoplader', 'fahrzeug', 1, 'gcA');
        $other = new DepartmentGrossanlassCommitment();
        $other->setOrigin(DepartmentGrossanlassCommitment::ORIGIN_LOAN);
        $other->setQuantity(1);
        $this->covering = [$other];
        $row = $this->ensure($this->inquiry(['gp0000000002']));

        self::assertNull($row->getProcurementLineId());
        self::assertSame('Teleskoplader', $row->getName());
    }

    public function testUnknownLineIdsAreIgnored(): void
    {
        $row = $this->ensure($this->inquiry(['gp9999999999']));

        self::assertSame('Miete von Teleskopladern und Traktoren', $row->getName());
    }

    public function testExistingCommitmentIsReturnedWithoutDuplicate(): void
    {
        $this->addLine('gp0000000002', 'Teleskoplader', 'fahrzeug', 1, 'gcA');
        $inquiry = $this->inquiry(['gp0000000002']);
        $existing = new DepartmentGrossanlassCommitment();
        $existing->setId('zs0000000001');
        $existing->setName('Teleskoplader');
        $existing->setSource('Maki Landtechnik');
        $this->existing = $existing;

        $result = $this->service($inquiry)->ensureFromInquiry($this->department(), $this->createMock(User::class), 'iq0000000001');

        self::assertSame('zs0000000001', $result['id']);
        self::assertSame([], $this->persisted);
    }

    private function ensure(DepartmentGrossanlassInquiry $inquiry): DepartmentGrossanlassCommitment
    {
        $this->service($inquiry)->ensureFromInquiry($this->department(), $this->createMock(User::class), 'iq0000000001');
        self::assertCount(1, $this->persisted);
        self::assertInstanceOf(DepartmentGrossanlassCommitment::class, $this->persisted[0]);

        return $this->persisted[0];
    }

    private function department(): Department
    {
        $department = $this->createMock(Department::class);
        $department->method('getId')->willReturn(self::DEPARTMENT_ID);

        return $department;
    }

    private function addLine(string $id, string $label, string $kind, int $qty, string $categoryId): void
    {
        $line = new ActivityGrossanlassProcurementLine();
        $line->setId($id);
        $ref = new \ReflectionClass($line);
        foreach (['departmentId' => self::DEPARTMENT_ID, 'label' => $label, 'wishKind' => $kind, 'quantity' => $qty, 'categoryId' => $categoryId] as $prop => $value) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setValue($line, $value);
            }
        }
        $this->lines[$id] = $line;
    }

    /**
     * @param list<string> $lineIds
     * @param list<string> $categoryIds
     */
    private function inquiry(array $lineIds, array $categoryIds = [], string $offering = 'Miete von Teleskopladern und Traktoren'): DepartmentGrossanlassInquiry
    {
        $inquiry = new DepartmentGrossanlassInquiry();
        $inquiry->setId('iq0000000001');
        $inquiry->setName('Maki Landtechnik');
        $inquiry->setLineIds($lineIds);
        $inquiry->setCategoryIds($categoryIds);
        $inquiry->setOffering($offering);
        $ref = new \ReflectionProperty($inquiry, 'departmentId');
        $ref->setValue($inquiry, self::DEPARTMENT_ID);

        return $inquiry;
    }

    private function service(DepartmentGrossanlassInquiry $inquiry): GrossanlassCommitmentService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($inquiry) {
            $repo = $this->createMock(EntityRepository::class);
            $repo->method('find')->willReturnCallback(fn ($id) => match ($class) {
                DepartmentGrossanlassInquiry::class => $inquiry,
                ActivityGrossanlassProcurementLine::class => $this->lines[$id] ?? null,
                default => null,
            });
            $repo->method('findOneBy')->willReturnCallback(fn () => $class === DepartmentGrossanlassCommitment::class ? $this->existing : null);
            $repo->method('findBy')->willReturnCallback(fn () => $class === DepartmentGrossanlassCommitment::class ? $this->covering : []);
            $repo->method('count')->willReturn(0);

            return $repo;
        });
        $access = $this->createMock(GrossanlassAccessService::class);
        $access->method('canTakeInquiry')->willReturn(true);

        $movements = $this->createMock(GrossanlassChargeMovementService::class);
        $movements->method('inboundSummaryFor')->willReturn(['state' => 'none', 'received' => 0, 'missing' => 0]);

        return new GrossanlassCommitmentService(
            $em,
            $access,
            $this->createMock(GrossanlassCostService::class),
            $movements,
            $this->createMock(GrossanlassAvailabilityService::class),
        );
    }
}
