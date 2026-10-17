<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassProcurementLineMatcher as Matcher;
use PHPUnit\Framework\TestCase;

class GrossanlassProcurementLineMatcherTest extends TestCase
{
    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function line(string $id, string $label, ?string $category = 'cat1', string $unit = 'Stk', array $extra = []): array
    {
        return $extra + ['id' => $id, 'label' => $label, 'category_id' => $category, 'quantity_unit' => $unit, 'source_wishes' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function wish(string $label, ?string $category = 'cat1', string $unit = 'Stk'): array
    {
        return ['label' => $label, 'category_id' => $category, 'quantity_unit' => $unit, 'notes' => null];
    }

    public function testIdenticalNameIsExact(): void
    {
        $hits = Matcher::match($this->wish('Stehtisch Holz 110 cm'), [$this->line('l1', 'stehtisch  holz 110 cm')]);

        self::assertSame(Matcher::TIER_EXACT, $hits[0]['tier']);
    }

    public function testPluralAndDimensionSpellingIsSimilar(): void
    {
        $hits = Matcher::match($this->wish('Stehtisch Holz 110 cm'), [$this->line('l1', 'Stehtische Holz 110cm')]);

        self::assertSame(Matcher::TIER_SIMILAR, $hits[0]['tier']);
        self::assertContains('same_category', $hits[0]['reasons']);
    }

    public function testDimensionNotationVariants(): void
    {
        $hits = Matcher::match($this->wish('Dachlatten 30x50'), [$this->line('l1', 'Dachlatten 30 × 50 mm')]);

        self::assertSame(Matcher::TIER_SIMILAR, $hits[0]['tier']);
    }

    public function testDifferentDimensionsAreDifferentArticles(): void
    {
        $hits = Matcher::match($this->wish('Dachlatten 30x50'), [$this->line('l1', 'Dachlatten 40x60')]);

        self::assertSame([], $hits);
    }

    public function testDifferentNameSameDimensionAndCategoryIsPossibleOnly(): void
    {
        $hits = Matcher::match($this->wish('Festzelt 4x4'), [$this->line('l1', 'Wetterdach 4 × 4 m')]);

        self::assertCount(1, $hits);
        self::assertSame(Matcher::TIER_POSSIBLE, $hits[0]['tier']);
    }

    public function testCategoryAloneNeverIdentifiesAnArticle(): void
    {
        self::assertSame([], Matcher::match($this->wish('Festzelt'), [$this->line('l1', 'Biertisch')]));
    }

    public function testUnitMismatchIsFlaggedAndRankedLower(): void
    {
        $hits = Matcher::match($this->wish('Dachlatten 30x50', 'cat1', 'Stk'), [
            $this->line('m', 'Dachlatten 30x50', 'cat1', 'm'),
            $this->line('s', 'Dachlatten 30x50', 'cat1', 'Stk'),
        ]);

        self::assertSame('s', $hits[0]['line_id']);
        self::assertTrue($hits[0]['unit_compatible']);
        self::assertSame('m', $hits[1]['line_id']);
        self::assertFalse($hits[1]['unit_compatible']);
        self::assertContains('unit_mismatch', $hits[1]['reasons']);
    }

    public function testMatchesViaOriginalWishLabel(): void
    {
        $line = $this->line('l1', 'Sitzgelegenheit Garten', 'cat1', 'Stk', ['source_wishes' => [['label' => 'Gartenbank Holz']]]);
        $hits = Matcher::match($this->wish('Gartenbank Holz'), [$line]);

        self::assertSame(Matcher::TIER_EXACT, $hits[0]['tier']);
        self::assertContains('original_wish', $hits[0]['reasons']);
    }
}
