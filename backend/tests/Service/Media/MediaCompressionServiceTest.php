<?php

declare(strict_types=1);

namespace App\Tests\Service\Media;

use App\Service\Media\MediaCompressionProfile;
use App\Service\Media\MediaCompressionService;
use App\Service\Media\MediaSettingsStore;
use PHPUnit\Framework\TestCase;

class MediaCompressionServiceTest extends TestCase
{
    public function testCatalogScalesTo1600(): void
    {
        $service = $this->createService();
        [$w, $h] = $service->scaleDimensions(3200, 2400, MediaCompressionProfile::CATALOG_MAX_EDGE);

        $this->assertSame(1600, $w);
        $this->assertSame(1200, $h);
    }

    public function testWorkshopKeepsMorePixelsThanCatalog(): void
    {
        $service = $this->createService();
        [$catalogW] = $service->scaleDimensions(4000, 3000, MediaCompressionProfile::CATALOG_MAX_EDGE);
        [$workshopW] = $service->scaleDimensions(4000, 3000, MediaCompressionProfile::WORKSHOP_MAX_EDGE);
        [$defaultW] = $service->scaleDimensions(4000, 3000, MediaCompressionProfile::DEFAULT_MAX_EDGE);

        $this->assertSame(1600, $catalogW);
        $this->assertSame(2560, $workshopW);
        $this->assertSame(1920, $defaultW);
    }

    private function createService(): MediaCompressionService
    {
        $settings = $this->createMock(MediaSettingsStore::class);
        $settings->method('isCompressionEnabled')->willReturn(true);

        return new MediaCompressionService($settings);
    }
}
