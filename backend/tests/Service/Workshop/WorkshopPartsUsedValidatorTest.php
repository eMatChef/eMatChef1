<?php

declare(strict_types=1);

namespace App\Tests\Service\Workshop;

use App\Entity\MaterialItem;
use App\Service\Workshop\WorkshopPartsUsedValidator;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class WorkshopPartsUsedValidatorTest extends TestCase
{
    public function testValidateAcceptsNull(): void
    {
        $validator = $this->createValidator();

        $this->assertSame([], $validator->validate(null, 'dept_test01'));
    }

    public function testValidateRejectsNonArray(): void
    {
        $validator = $this->createValidator();

        $errors = $validator->validate('invalid', 'dept_test01');

        $this->assertNotEmpty($errors);
    }

    public function testValidateRejectsInvalidLine(): void
    {
        $validator = $this->createValidator();

        $errors = $validator->validate([
            ['material_item_id' => 'bad', 'quantity' => 0, 'source' => 'x', 'status' => 'x'],
        ], 'dept_test01');

        $this->assertNotEmpty($errors);
    }

    public function testValidateAcceptsValidLine(): void
    {
        $material = $this->createMock(MaterialItem::class);
        $material->method('getDepartmentId')->willReturn('dept_test01');
        $material->method('getCategoryId')->willReturn('ca1234567890');

        $materialRepo = $this->createMock(EntityRepository::class);
        $materialRepo->method('find')->with('ma1234567890')->willReturn($material);

        $bootstrap = $this->createMock(WorkshopSparePartsCategoryBootstrapService::class);
        $bootstrap->method('ensure')->with('dept_test01')->willReturn('ca1234567890');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(MaterialItem::class)->willReturn($materialRepo);

        $validator = new WorkshopPartsUsedValidator($em, $bootstrap);

        $errors = $validator->validate([
            [
                'id' => 'line1',
                'material_item_id' => 'ma1234567890',
                'material_name' => 'Schraube M6',
                'quantity' => 2,
                'source' => 'stock',
                'status' => 'planned',
                'unit_cost' => '1.50',
            ],
        ], 'dept_test01');

        $this->assertSame([], $errors);
    }

    public function testNormalizeStripsInvalidLines(): void
    {
        $validator = $this->createValidator();

        $normalized = $validator->normalize([
            ['material_item_id' => '', 'quantity' => 1],
            [
                'id' => 'line2',
                'material_item_id' => 'ma1234567890',
                'quantity' => 3,
                'source' => 'purchase',
                'status' => 'ordered',
                'unit_cost' => '4.2',
            ],
        ]);

        $this->assertCount(1, $normalized);
        $this->assertSame('ma1234567890', $normalized[0]['material_item_id']);
        $this->assertSame('4.20', $normalized[0]['unit_cost']);
        $this->assertSame('purchase', $normalized[0]['source']);
        $this->assertSame('ordered', $normalized[0]['status']);
    }

    private function createValidator(): WorkshopPartsUsedValidator
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $bootstrap = $this->createMock(WorkshopSparePartsCategoryBootstrapService::class);
        $bootstrap->method('ensure')->willReturn('ca1234567890');

        return new WorkshopPartsUsedValidator($em, $bootstrap);
    }
}
