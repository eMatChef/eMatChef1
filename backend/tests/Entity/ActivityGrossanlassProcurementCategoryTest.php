<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ActivityGrossanlassProcurementCategory;
use PHPUnit\Framework\TestCase;

final class ActivityGrossanlassProcurementCategoryTest extends TestCase
{
    public function testJsNameAliases(): void
    {
        self::assertTrue(ActivityGrossanlassProcurementCategory::isJsNameAlias('J+S'));
        self::assertTrue(ActivityGrossanlassProcurementCategory::isJsNameAlias('j & s'));
        self::assertTrue(ActivityGrossanlassProcurementCategory::isJsNameAlias('J und S'));
        self::assertFalse(ActivityGrossanlassProcurementCategory::isJsNameAlias('JS'));
        self::assertFalse(ActivityGrossanlassProcurementCategory::isJsNameAlias('Maschinen'));
    }
}
