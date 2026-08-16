<?php

declare(strict_types=1);

namespace App\Tests\Service\Media;

use App\Service\Media\DepartmentMediaBrowserService;
use App\Service\Media\MediaPhotoNormalizer;
use App\Service\Media\MediaStorageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DepartmentMediaBrowserServiceTest extends TestCase
{
    public function testMapStoredFileRebuildsMediaUrlAndClassifiesPdf(): void
    {
        $service = $this->createService();

        $item = $service->mapStoredFile(
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
            'dept_abc',
            'bk_xyz',
            'Beleg Mai',
            '/accounting/bookings',
            [
                'id' => 'rec1',
                'filename' => 'beleg.pdf',
                'original_filename' => 'Rechnung.pdf',
                'mime' => 'application/pdf',
                'bytes' => 12000,
                'uploaded_at' => '2026-08-01T10:00:00+02:00',
                'uploaded_by_name' => 'Max',
            ],
        );

        $this->assertNotNull($item);
        $this->assertSame('documents', $item['kind']);
        $this->assertSame('/media/dept_abc/documents/accounting/bk_xyz/beleg.pdf', $item['url']);
        $this->assertSame('Rechnung.pdf', $item['original_filename']);
        $this->assertTrue($item['can_replace']);
        $this->assertTrue($item['can_rename']);
        $this->assertSame('accounting', $item['links'][0]['kind']);
    }

    public function testMapStoredFileDisablesReplaceAndRenameForJsOrderPdf(): void
    {
        $service = $this->createService();

        $item = $service->mapStoredFile(
            MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER,
            'dept_abc',
            'act_xyz',
            'Lager',
            '/activities/act_xyz',
            [
                'filename' => 'js-order.pdf',
                'original_filename' => 'Bestellung.pdf',
                'mime' => 'application/pdf',
            ],
        );

        $this->assertNotNull($item);
        $this->assertFalse($item['can_replace']);
        $this->assertFalse($item['can_rename']);
    }

    public function testMapStoredFileClassifiesImageFromLegacyApiUrl(): void
    {
        $service = $this->createService();

        $item = $service->mapStoredFile(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            'dept_abc',
            'mat_xyz',
            'Zelt',
            '/materials/mat_xyz',
            [
                'url' => '/api/materials/mat_xyz/photos/zelt.webp',
                'mime' => 'image/webp',
            ],
        );

        $this->assertNotNull($item);
        $this->assertSame('photos', $item['kind']);
        $this->assertSame('zelt.webp', $item['filename']);
        $this->assertSame('Zelt', $item['original_filename']);
        $this->assertSame('/media/dept_abc/photos/material/mat_xyz/zelt.webp', $item['url']);
    }

    public function testMapStoredFileUsesMaterialNameForCameraUploads(): void
    {
        $service = $this->createService();

        $camera = $service->mapStoredFile(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            'dept_abc',
            'mat_xyz',
            'Zelt 4x6',
            '/materials/mat_xyz',
            [
                'filename' => 'abc123.webp',
                'original_filename' => 'IMG_1234.jpg',
                'mime' => 'image/webp',
            ],
        );
        $custom = $service->mapStoredFile(
            MediaStorageService::CONTEXT_MATERIAL_ITEM,
            'dept_abc',
            'mat_xyz',
            'Zelt 4x6',
            '/materials/mat_xyz',
            [
                'filename' => 'abc123.webp',
                'original_filename' => 'Zelt rot vorne',
                'mime' => 'image/webp',
            ],
        );

        $this->assertNotNull($camera);
        $this->assertSame('Zelt 4x6', $camera['original_filename']);
        $this->assertNotNull($custom);
        $this->assertSame('Zelt rot vorne', $custom['original_filename']);
    }

    private function createService(): DepartmentMediaBrowserService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $storage = new MediaStorageService(
            sys_get_temp_dir() . '/ematchef_media_browser_' . bin2hex(random_bytes(4)),
            $this->createMock(\App\Service\Media\MediaCompressionService::class),
        );

        return new DepartmentMediaBrowserService($em, $storage, new MediaPhotoNormalizer());
    }
}
