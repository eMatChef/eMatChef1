<?php

declare(strict_types=1);

namespace App\Tests\Service\Display\Layout;

use App\Service\Display\Layout\DisplayDeviceProfileService;
use PHPUnit\Framework\TestCase;

final class DisplayDeviceProfileServiceTest extends TestCase
{
    private DisplayDeviceProfileService $service;

    protected function setUp(): void
    {
        $this->service = new DisplayDeviceProfileService();
    }

    /** @return array<string, mixed> */
    private function layout(): array
    {
        return [
            'default_variant_id' => 'std',
            'variants' => [
                ['id' => 'std', 'aspect_ratio' => '16:9'],
                ['id' => 'led', 'aspect_ratio' => '3:2'],
                ['id' => 'compact', 'aspect_ratio' => '16:9'],
            ],
        ];
    }

    public function testNoProfileFallsBackToDefaultVariant(): void
    {
        self::assertSame('std', $this->service->selectVariant($this->layout(), null));
        self::assertSame('std', $this->service->selectVariant($this->layout(), $this->service->normalize([])));
    }

    public function testVariantIsChosenByAspectRatioAndTiesGoToTheDefault(): void
    {
        $led = $this->service->normalize(['pixel_width' => 3000, 'pixel_height' => 2000]);
        self::assertSame('led', $this->service->selectVariant($this->layout(), $led));
        $tv = $this->service->normalize(['pixel_width' => 1920, 'pixel_height' => 1080]);
        self::assertSame('std', $this->service->selectVariant($this->layout(), $tv));
    }

    public function testUnsuitableAspectFallsBackToDefault(): void
    {
        $portrait = $this->service->normalize(['aspect_ratio' => '9:16']);
        self::assertSame('std', $this->service->selectVariant($this->layout(), $portrait));
    }

    public function testExplicitVariantWinsAndUnknownExplicitVariantFallsBack(): void
    {
        self::assertSame('compact', $this->service->selectVariant($this->layout(), $this->service->normalize(['variant_id' => 'compact'])));
        self::assertSame('std', $this->service->selectVariant($this->layout(), $this->service->normalize(['variant_id' => 'removed'])));
    }

    public function testPixelsAreNotDerivedFromPhysicalSize(): void
    {
        $profile = $this->service->normalize(['width_cm' => 300, 'height_cm' => 200]);
        self::assertNull($profile['pixel_width']);
        self::assertNull($profile['pixel_height']);
        self::assertEqualsWithDelta(1.5, $this->service->aspectOf($profile), 0.001);
    }

    public function testFontScaleFromProfileOrViewingDistance(): void
    {
        self::assertSame(1.25, $this->service->fontScale($this->service->normalize(['font_profile' => 'large'])));
        self::assertSame(1.0, $this->service->fontScale(null));
        self::assertSame(1.6, $this->service->fontScale($this->service->normalize(['viewing_distance_m' => 20])));
    }

    public function testInvalidProfilesAreRejected(): void
    {
        foreach ([['pixel_width' => 10], ['aspect_ratio' => 'wide'], ['font_profile' => 'huge'], ['variant_id' => '../x'], ['evil' => 1], 'text'] as $bad) {
            try {
                $this->service->normalize($bad);
                self::fail('Profil hätte abgelehnt werden müssen: ' . json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertNull($this->service->normalize(null));
    }
}
