<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassMailAttachmentService;
use PHPUnit\Framework\TestCase;

class GrossanlassMailAttachmentServiceTest extends TestCase
{
    public function testNormalizeKeepsSafeRecords(): void
    {
        $rows = GrossanlassMailAttachmentService::normalizeRecords([
            [
                'id' => 'abc123abc123',
                'filename' => 'stored.pdf',
                'original_filename' => 'Was ist ein PFF.pdf',
                'mime' => 'application/pdf',
                'bytes' => 1200,
            ],
            ['id' => '', 'filename' => 'x.pdf'],
            ['id' => 'x', 'filename' => '../evil.pdf'],
            'nope',
        ]);
        self::assertCount(1, $rows);
        self::assertSame('Was ist ein PFF.pdf', $rows[0]['original_filename']);
        self::assertSame('stored.pdf', $rows[0]['filename']);
    }

    public function testNormalizeKeepsInvalidUtf8OriginalName(): void
    {
        $bad = "PFF\x80.pdf";
        $rows = GrossanlassMailAttachmentService::normalizeRecords([
            [
                'id' => 'abc123abc123',
                'filename' => 'stored.pdf',
                'original_filename' => $bad,
                'mime' => 'application/pdf',
                'bytes' => 12,
            ],
        ]);
        self::assertCount(1, $rows);
        self::assertTrue(mb_check_encoding($rows[0]['original_filename'], 'UTF-8'));
    }

    public function testNormalizeRejectsNonArray(): void
    {
        self::assertSame([], GrossanlassMailAttachmentService::normalizeRecords(null));
        self::assertSame([], GrossanlassMailAttachmentService::normalizeRecords('pdf'));
    }
}
