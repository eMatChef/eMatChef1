<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\MaterialItem;
use App\Service\JsDotationRulesService;
use PHPUnit\Framework\TestCase;

final class JsDotationRulesServiceTest extends TestCase
{
    private JsDotationRulesService $service;

    protected function setUp(): void
    {
        $this->service = new JsDotationRulesService();
    }

    public function testZelttuchRoundsUpToTenForThirtyThreeParticipants(): void
    {
        $material = $this->material('Zelttuch olivgrün neu J+S');

        self::assertSame(40, $this->service->suggestQuantityForMaterial($material, 33));
    }

    public function testWolldeckeRoundsUpToFiveForThirtyThreeParticipants(): void
    {
        $material = $this->material('Wolldecke');

        self::assertSame(70, $this->service->suggestQuantityForMaterial($material, 33));
    }

    public function testBindestrickCapsAtFifty(): void
    {
        $material = $this->material('Bindestrick Hanf blau/grau');

        self::assertSame(50, $this->service->suggestQuantityForMaterial($material, 60));
    }

    public function testSchneeschaufelHasCourseMaxFifteenInWinter(): void
    {
        $material = $this->material('Schneeschaufel');
        $limits = $this->service->limitsForMaterial($material);

        self::assertSame(15, $limits['max']);
    }

    public function testSpielsetGroupValidation(): void
    {
        $errors = $this->service->validateOrderItems(30, [
            ['material_name' => 'Badmintonschläger', 'quantity_ordered' => 2],
            ['material_name' => 'Ballset (6 Spielbälle)', 'quantity_ordered' => 2],
        ]);

        self::assertContains('Gruppe spielset: max. 3 gesamt (bestellt: 4)', $errors);
    }

    public function testRettungswesteOverTwentyDoesNotBlockValidation(): void
    {
        $errors = $this->service->validateOrderItems(40, [
            ['material_name' => 'Rettungsweste M (60-70 Kg)', 'quantity_ordered' => 12],
            ['material_name' => 'Rettungsweste L (70-90 Kg)', 'quantity_ordered' => 10],
        ]);

        self::assertSame([], $errors);
    }

    public function testRettungswesteOverTwentyProducesWarningForLager(): void
    {
        $warnings = $this->service->collectOrderWarnings(40, [
            ['material_name' => 'Rettungsweste M (60-70 Kg)', 'quantity_ordered' => 12],
            ['material_name' => 'Rettungsweste L (70-90 Kg)', 'quantity_ordered' => 10],
        ], 'lager');

        self::assertNotEmpty($warnings);
        self::assertStringContainsString('22 bestellt', $warnings[0]);
    }

    public function testRettungswesteNotAutoSuggested(): void
    {
        $material = $this->material('Rettungsweste M (60-70 Kg)');

        self::assertNull($this->service->suggestQuantityForMaterial($material, 33));
    }

    public function testRettungswesteHintDependsOnCourseType(): void
    {
        $material = $this->material('Rettungsweste M (60-70 Kg)');

        self::assertStringContainsString('Grössen manuell', $this->service->dotationHintForMaterial($material, 'lager') ?? '');
        self::assertStringContainsString('max. 20', $this->service->dotationHintForMaterial($material, 'kaderbildung') ?? '');
    }

    public function testBuildDotationIncludesBindestrickOnce(): void
    {
        $materials = [
            $this->material('Bindestrick Hanf blau/grau', 'js1'),
            $this->material('Bindestrick Nylon grün', 'js2'),
        ];

        $suggestions = $this->service->buildDotationSuggestions($materials, 33);
        $ids = array_column($suggestions, 'material_item_id');

        self::assertCount(1, array_intersect(['js1', 'js2'], $ids));
    }

    public function testBuildDotationIncludesZelttuchOnce(): void
    {
        $materials = [
            $this->material('Zelttuch 64 einseitig getarnt', 'z1'),
            $this->material('Zelttuch olivgrün neu J+S', 'z2'),
            $this->material('Ausschusszelttuch', 'z3'),
        ];

        $suggestions = $this->service->buildDotationSuggestions($materials, 33);
        $ids = array_column($suggestions, 'material_item_id');

        self::assertContains('z1', $ids);
        self::assertNotContains('z2', $ids);
        self::assertContains('z3', $ids);
    }

    private function material(string $name, string $id = 'mat1'): MaterialItem
    {
        $material = new MaterialItem();
        $material->setName($name);
        $reflection = new \ReflectionClass($material);
        $prop = $reflection->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($material, $id);

        return $material;
    }
}
