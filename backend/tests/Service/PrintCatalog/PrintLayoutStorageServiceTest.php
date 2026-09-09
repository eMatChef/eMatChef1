<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintCatalog;

use App\Service\PrintCatalog\PrintLayoutStorageService;
use PHPUnit\Framework\TestCase;

class PrintLayoutStorageServiceTest extends TestCase
{
    public function testCentralPathRejectsInvalidHash(): void
    {
        $storage = new PrintLayoutStorageService(sys_get_temp_dir());
        $this->expectException(\InvalidArgumentException::class);
        $storage->centralPath('../secret');
    }

    public function testHashFileIsStable(): void
    {
        $dir = sys_get_temp_dir() . '/print-tpl-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir . '/a.pdf';
        file_put_contents($path, "%PDF-1.4\n%test\n");
        $storage = new PrintLayoutStorageService($dir);
        $hash = $storage->hashFile($path);
        $this->assertSame(64, strlen($hash));
        $this->assertSame($hash, $storage->hashFile($path));
        $this->assertStringEndsWith($hash . '.pdf', $storage->centralPath($hash));
        unlink($path);
        rmdir($dir);
    }
}
