<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PurgeSecurityActivityContextCommand;
use App\Service\Auth\SecurityActivityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeSecurityActivityContextCommandTest extends TestCase
{
    public function testDryRunOnlyCountsAndNeverPurges(): void
    {
        $service = $this->createMock(SecurityActivityService::class);
        $service->method('countExpiredContext')->willReturn(4);
        $service->expects(self::never())->method('purgeExpiredContext');

        $tester = new CommandTester(new PurgeSecurityActivityContextCommand($service));
        $tester->execute(['--dry-run' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('4 Sicherheitsereignis', $tester->getDisplay());
    }

    public function testRunClearsContextOfExpiredEventsOnly(): void
    {
        $service = $this->createMock(SecurityActivityService::class);
        $service->expects(self::once())->method('purgeExpiredContext')->willReturn(2);

        $tester = new CommandTester(new PurgeSecurityActivityContextCommand($service));
        $tester->execute([]);

        self::assertStringContainsString('2 Sicherheitsereignis', $tester->getDisplay());
    }
}
