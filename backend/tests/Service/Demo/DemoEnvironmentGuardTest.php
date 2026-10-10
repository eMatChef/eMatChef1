<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo;

use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\DevEnvironmentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class DemoEnvironmentGuardTest extends TestCase
{
    private function guard(string $kernelEnv, bool $devTools, string $name, bool $destructive): DemoEnvironmentGuard
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($kernelEnv);
        $dev = $this->createMock(DevEnvironmentService::class);
        $dev->method('isDevToolsEnabled')->willReturn($devTools);

        return new DemoEnvironmentGuard($kernel, $dev, $name, $destructive);
    }

    /** @return iterable<string, array{string, bool, string, bool, bool, bool}> kernel, devTools, name, destructiveFlag, additiveAllowed, destructiveAllowed */
    public static function matrix(): iterable
    {
        yield 'local dev kernel, no name' => ['dev', true, '', false, true, true];
        yield 'local explicit' => ['dev', true, 'local', false, true, true];
        yield 'develop prod kernel, no destructive flag' => ['prod', true, 'develop', false, true, false];
        yield 'develop prod kernel, destructive flag' => ['prod', true, 'develop', true, true, true];
        yield 'staging never destructive' => ['prod', true, 'staging', true, true, false];
        yield 'production never' => ['prod', true, 'production', true, false, false];
        yield 'production name on dev kernel' => ['dev', true, 'production', true, false, false];
        yield 'prod kernel dev tools only (the old gate)' => ['prod', true, '', true, false, false];
        yield 'prod kernel typo name' => ['prod', true, 'develope', true, false, false];
        yield 'local name on prod kernel' => ['prod', true, 'local', true, false, false];
        yield 'dev tools off on prod kernel' => ['prod', false, 'develop', true, false, false];
        yield 'staging name on dev kernel, destructive denied' => ['dev', true, 'staging', true, true, false];
        yield 'name is case/space tolerant' => ['prod', true, ' Develop ', true, true, true];
    }

    #[DataProvider('matrix')]
    public function testMatrix(string $kernel, bool $devTools, string $name, bool $flag, bool $additive, bool $destructive): void
    {
        $guard = $this->guard($kernel, $devTools, $name, $flag);

        self::assertSame($additive, $guard->additiveDenial() === null, 'additive');
        self::assertSame($destructive, $guard->destructiveDenial() === null, 'destructive');
    }
}
