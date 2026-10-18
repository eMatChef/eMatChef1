<?php

declare(strict_types=1);

namespace App\Tests\Service\Display;

use App\Entity\Department;
use App\Service\Clock\BusinessClock;
use App\Service\Display\DisplayClockPayload;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class DisplayClockPayloadTest extends TestCase
{
    private function payload(string $env = 'dev'): DisplayClockPayload
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($env);

        return new DisplayClockPayload(new BusinessClock($this->createMock(EntityManagerInterface::class), $kernel));
    }

    private function department(bool $demo, ?int $offset): Department
    {
        return (new Department())->setId('d1')->setDemoMode($demo)->setDemoClockOffsetSeconds($offset);
    }

    public function testDemoDepartmentProvidesItsBusinessTimeAsWallClock(): void
    {
        $target = new \DateTimeImmutable('2027-05-19 13:00:00');
        $department = $this->department(true, $target->getTimestamp() - time());

        $clock = $this->payload()->for($department);

        self::assertSame('demo', $clock['mode']);
        self::assertEqualsWithDelta($target->getTimestamp(), (new \DateTimeImmutable($clock['now']))->getTimestamp(), 3);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $clock['now'], 'naive Wandzeit ohne Zeitzone');
        self::assertSame($department->getDemoClockOffsetSeconds(), $clock['offset_seconds']);
    }

    public function testNormalDepartmentKeepsTheRealTimeEvenWithAStoredOffsetInDev(): void
    {
        // Entwickler-Zeitreise (Kernel ≠ prod) gilt für das GA-Control, nicht für den öffentlichen Infoscreen.
        self::assertSame(['mode' => 'real'], $this->payload('dev')->for($this->department(false, 86400)));
        self::assertSame(['mode' => 'real'], $this->payload('prod')->for($this->department(false, 86400)));
    }

    public function testMissingDepartmentIsRealTime(): void
    {
        self::assertSame(['mode' => 'real'], $this->payload()->for(null));
    }
}
