<?php

declare(strict_types=1);

namespace App\Tests\Service\Media;

use App\Service\Media\MediaCompressionService;
use App\Service\Media\MediaSettingsStore;
use App\Service\Media\MediaStorageService;
use PHPUnit\Framework\TestCase;

class MediaStorageServiceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/ematchef_media_test_' . bin2hex(random_bytes(4));
        mkdir($this->projectDir . '/var/uploads', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function testAssertSafePathSegmentRejectsTraversal(): void
    {
        $service = $this->createService();

        $this->expectException(\InvalidArgumentException::class);
        $service->assertSafePathSegment('../etc');
    }

    public function testAssertSafeFilenameRejectsSlashes(): void
    {
        $service = $this->createService();

        $this->expectException(\InvalidArgumentException::class);
        $service->assertSafeFilename('evil/file.jpg');
    }

    public function testResolveContextDir(): void
    {
        $service = $this->createService();

        $dir = $service->resolveContextDir(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            'dept_abc',
            'wt_xyz',
        );

        $this->assertStringEndsWith('/var/uploads/dept_abc/photos/workshop/wt_xyz', $dir);
    }

    public function testSanitizeOriginalFilenameStripsPathAndLimitsLength(): void
    {
        $service = $this->createService();

        $this->assertSame('Rechnung Mai.pdf', $service->sanitizeOriginalFilename('Rechnung Mai.pdf'));
        $this->assertSame('file.jpg', $service->sanitizeOriginalFilename('../evil/file.jpg'));
        $this->assertSame('Rechnung_Mai.pdf', $service->sanitizeOriginalFilename('Rechnung:Mai.pdf'));
        $this->assertSame(200, mb_strlen($service->sanitizeOriginalFilename(str_repeat('ä', 250))));
    }

    public function testBuildPublicMediaUrl(): void
    {
        $service = $this->createService();

        $url = $service->buildPublicMediaUrl(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            'dept_abc',
            'mat_xyz',
            'photo.webp',
        );

        $this->assertSame('/media/dept_abc/photos/material/mat_xyz/photo.webp', $url);
    }

    public function testContextFromKindAndFolder(): void
    {
        $service = $this->createService();

        $this->assertSame(
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
            $service->contextFromKindAndFolder('documents', 'accounting'),
        );
    }

    public function testResolveWorkshopTicketFilePathUsesLegacyFallback(): void
    {
        $service = $this->createService();
        $legacyDir = $this->projectDir . '/var/uploads/workshop/supplier/co_1/wt_1';
        mkdir($legacyDir, 0775, true);
        file_put_contents($legacyDir . '/photo.jpg', 'test');

        $path = $service->resolveWorkshopTicketFilePath('dept_1', 'wt_1', 'photo.jpg', 'co_1');

        $this->assertSame($legacyDir . '/photo.jpg', $path);
    }

    private function createService(): MediaStorageService
    {
        $settings = $this->createMock(MediaSettingsStore::class);
        $settings->method('isCompressionEnabled')->willReturn(false);

        $compression = new MediaCompressionService($settings);

        return new MediaStorageService($this->projectDir, $compression);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
