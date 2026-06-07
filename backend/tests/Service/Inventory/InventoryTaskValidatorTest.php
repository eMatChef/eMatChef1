<?php

declare(strict_types=1);

namespace App\Tests\Service\Inventory;

use App\Service\Inventory\InventoryTaskValidator;
use PHPUnit\Framework\TestCase;

class InventoryTaskValidatorTest extends TestCase
{
    public function testNormalizeLinesJsonAcceptsValidLines(): void
    {
        $validator = new InventoryTaskValidator();

        $result = $validator->normalizeLinesJson([
            'lines' => [
                [
                    'material_item_id' => 'ma1234567890',
                    'material_name' => 'Zeltstange',
                    'expected_qty' => 5,
                    'counted_qty' => 4,
                    'notes' => '1 fehlt',
                ],
            ],
        ]);

        $this->assertCount(1, $result['lines']);
        $this->assertSame('ma1234567890', $result['lines'][0]['material_item_id']);
        $this->assertSame(5.0, $result['lines'][0]['expected_qty']);
        $this->assertSame(4.0, $result['lines'][0]['counted_qty']);
    }

    public function testValidateTitleRejectsEmpty(): void
    {
        $validator = new InventoryTaskValidator();

        $this->expectException(\InvalidArgumentException::class);
        $validator->validateTitle('   ');
    }
}
