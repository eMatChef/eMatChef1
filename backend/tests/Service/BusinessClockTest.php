<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassConfig;
use App\Service\Clock\BusinessClock;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

final class BusinessClockTest extends TestCase
{
    public function testNoContextIsRealTime(): void
    {
        $clock = $this->clock('prod');

        self::assertEqualsWithDelta(time(), $clock->now()->getTimestamp(), 2);
        self::assertEqualsWithDelta(time(), $clock->now(null)->getTimestamp(), 2);
    }

    public function testNormalDepartmentInProdIsRealTimeAndCannotTravel(): void
    {
        $clock = $this->clock('prod');
        $department = $this->department(false, 86400);

        self::assertSame(0, $clock->offsetSeconds($department));
        self::assertEqualsWithDelta(time(), $clock->now($department)->getTimestamp(), 2);
        self::assertFalse($clock->supportsTravel($department));
        self::assertSame(BusinessClock::MODE_REAL, $clock->mode($department));
        $this->expectException(\LogicException::class);
        $clock->travelTo($department, new \DateTimeImmutable('+1 day'));
    }

    public function testDemoDepartmentWithoutGrossanlassCanUseTheClock(): void
    {
        $clock = $this->clock('prod');
        $department = $this->department(true, null);
        self::assertFalse($department->isGrossanlass());

        $target = new \DateTimeImmutable('+10 days');
        $clock->travelTo($department, $target);

        self::assertSame(BusinessClock::MODE_DEMO, $clock->mode($department));
        self::assertTrue($clock->supportsTravel($department));
        self::assertEqualsWithDelta($target->getTimestamp(), $clock->now($department)->getTimestamp(), 2);
    }

    public function testDemoTimeKeepsRunningAfterJump(): void
    {
        $clock = $this->clock('prod');
        $department = $this->department(true, null);
        $target = new \DateTimeImmutable('2030-11-04 10:00:00');

        $clock->travelTo($department, $target);
        $first = $clock->now($department)->getTimestamp();
        self::assertEqualsWithDelta($target->getTimestamp(), $first, 2);

        $offset = $department->getDemoClockOffsetSeconds();
        sleep(1);
        self::assertSame($offset, $department->getDemoClockOffsetSeconds());
        self::assertGreaterThanOrEqual($first + 1, $clock->now($department)->getTimestamp());
    }

    public function testOffsetIsIsolatedBetweenDepartments(): void
    {
        $clock = $this->clock('prod');
        $demoA = $this->department(true, null);
        $demoB = $this->department(true, 5 * 86400);
        $normal = $this->department(false, null);

        $clock->travelTo($demoA, new \DateTimeImmutable('+30 days'));

        self::assertSame(5 * 86400, $demoB->getDemoClockOffsetSeconds());
        self::assertSame(0, $clock->offsetSeconds($normal));
        self::assertEqualsWithDelta(time() + 5 * 86400, $clock->now($demoB)->getTimestamp(), 2);
    }

    public function testGrossanlassResetUsesEventOrigin(): void
    {
        $clock = $this->clock('prod');
        $department = $this->department(true, 12345, grossanlassEventStart: '2031-06-15 00:00:00');

        $clock->reset($department);

        $expected = (new \DateTime('2031-06-10 09:00:00'))->getTimestamp();
        self::assertEqualsWithDelta($expected, $clock->now($department)->getTimestamp(), 2);
    }

    public function testDemoDepartmentWithoutOriginResolverResetsToRealTime(): void
    {
        $clock = $this->clock('prod');
        $department = $this->department(true, 99999);

        $clock->reset($department);

        self::assertEqualsWithDelta(time(), $clock->now($department)->getTimestamp(), 2);
    }

    public function testDevKernelAllowsTravelOnNormalDepartment(): void
    {
        $clock = $this->clock('dev');
        $department = $this->department(false, null);

        self::assertSame(BusinessClock::MODE_DEV, $clock->mode($department));
        self::assertTrue($clock->supportsTravel($department));
        $clock->travelTo($department, new \DateTimeImmutable('+2 days'));
        self::assertEqualsWithDelta(time() + 2 * 86400, $clock->now($department)->getTimestamp(), 2);

        $clock->reset($department);
        self::assertNull($department->getDemoClockOffsetSeconds());
    }

    public function testResolvesContextFromId(): void
    {
        $department = $this->department(true, 86400);
        $department->setId('dep000000001');
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturnCallback(static fn (string $id) => $id === 'dep000000001' ? $department : null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $clock = new BusinessClock($em, $this->kernel('prod'));

        self::assertSame(86400, $clock->offsetSeconds('dep000000001'));
        self::assertSame(0, $clock->offsetSeconds('unknown00000'));
    }

    public function testSecurityAndTechnicalCodeDoesNotUseBusinessClock(): void
    {
        $srcDir = dirname(__DIR__, 2) . '/src';
        $finder = (new Finder())->files()->name('*.php')->in($srcDir)->path([
            'Security', 'EventSubscriber', 'EventListener', 'Service/Auth', 'Repository',
        ]);
        $finder->append((new Finder())->files()->in($srcDir . '/Service/Grossanlass')->name('GmailOAuth*.php'));

        $offenders = [];
        foreach ($finder as $file) {
            if (str_contains($file->getContents(), 'BusinessClock')) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $offenders, 'Security-/Token-Zeit muss reale Systemzeit bleiben.');
    }

    private function clock(string $env): BusinessClock
    {
        return new BusinessClock(
            $this->createMock(EntityManagerInterface::class),
            $this->kernel($env),
            [new GrossanlassClockOriginResolver()],
        );
    }

    private function kernel(string $env): KernelInterface
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($env);

        return $kernel;
    }

    private function department(bool $demo, ?int $offset, ?string $grossanlassEventStart = null): Department
    {
        $department = new Department();
        $department->setDemoMode($demo);
        $department->setDemoClockOffsetSeconds($offset);
        if ($grossanlassEventStart !== null) {
            $config = new DepartmentGrossanlassConfig();
            $config->setPlannedEventStart(new \DateTime($grossanlassEventStart));
            $department->setIsGrossanlass(true);
            $department->setGrossanlassConfig($config);
        }

        return $department;
    }
}
