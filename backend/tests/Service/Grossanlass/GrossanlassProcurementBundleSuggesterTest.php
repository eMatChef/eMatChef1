<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassProcurementBundleSuggester;
use PHPUnit\Framework\TestCase;

class GrossanlassProcurementBundleSuggesterTest extends TestCase
{
    public function testSuggestsSimilarGermanLabels(): void
    {
        $pool = [
            $this->wish('w1', 'Schrauben', 20),
            $this->wish('w2', 'Schraube', 5),
            $this->wish('w3', 'Maschine', 1),
            $this->wish('w4', 'Maschinen', 2),
            $this->wish('w5', 'Zelt 4x4', 1),
        ];

        $groups = GrossanlassProcurementBundleSuggester::suggest($pool);

        $this->assertCount(2, $groups);
        $this->assertSame(['w1', 'w2'], $groups[0]['wish_ids']);
        $this->assertSame(25, $groups[0]['quantity_sum']);
        $this->assertSame('Schrauben', $groups[0]['suggested_label']);
        $this->assertSame(['w3', 'w4'], $groups[1]['wish_ids']);
        $this->assertSame('Maschinen', $groups[1]['suggested_label']);
    }

    public function testIgnoresSingletonsAndEmptyLabels(): void
    {
        $pool = [
            $this->wish('w1', 'Generator', 1),
            $this->wish('w2', '   ', 3),
            $this->wish('', 'Kabel', 2),
        ];

        $this->assertSame([], GrossanlassProcurementBundleSuggester::suggest($pool));
    }

    public function testNormalizesUmlautsAndPunctuation(): void
    {
        $this->assertSame(
            GrossanlassProcurementBundleSuggester::normalizeKey('Nägel'),
            GrossanlassProcurementBundleSuggester::normalizeKey('Naegel'),
        );
        $this->assertSame(
            GrossanlassProcurementBundleSuggester::normalizeKey('Schrauben!'),
            GrossanlassProcurementBundleSuggester::normalizeKey('schraube'),
        );
    }

    /**
     * @return array{id: string, label: string, quantity: int}
     */
    private function wish(string $id, string $label, int $quantity): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'quantity' => $quantity,
        ];
    }
}
