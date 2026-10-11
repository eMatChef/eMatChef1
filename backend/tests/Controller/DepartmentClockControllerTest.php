<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DepartmentClockController;
use App\Entity\Department;
use App\Entity\DepartmentCalendarPeriod;
use App\Entity\DepartmentSetting;
use App\Entity\DepartmentGrossanlassConfig;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class DepartmentClockControllerTest extends TestCase
{
    public function testNormalProdDepartmentCannotTravel(): void
    {
        $department = $this->department(false);
        $controller = $this->controller($department, 'prod', true);

        $get = json_decode((string) $controller->get('dep000000001')->getContent(), true);
        self::assertFalse($get['can_travel']);
        self::assertSame('real', $get['mode']);

        $put = $controller->put('dep000000001', $this->putRequest('+1 day'));
        self::assertSame(403, $put->getStatusCode());
        self::assertSame(403, $controller->delete('dep000000001')->getStatusCode());
        self::assertNull($department->getDemoClockOffsetSeconds());
    }

    public function testDemoGrossanlassDepartmentAllowsTravelAndEventReset(): void
    {
        $department = $this->department(true, true);
        $department->getGrossanlassConfig()->setPlannedEventStart(new \DateTime('2031-06-15 00:00:00'));
        $controller = $this->controller($department, 'prod', true);

        $get = json_decode((string) $controller->get('dep000000001')->getContent(), true);
        self::assertTrue($get['can_travel']);
        self::assertSame('demo', $get['mode']);

        $put = $controller->put('dep000000001', $this->putRequest('2030-11-04T10:00:00+00:00'));
        self::assertSame(200, $put->getStatusCode());
        $body = json_decode((string) $put->getContent(), true);
        self::assertEqualsWithDelta(strtotime('2030-11-04T10:00:00+00:00'), strtotime($body['now']), 2);

        $reset = json_decode((string) $controller->delete('dep000000001')->getContent(), true);
        self::assertStringStartsWith('2031-06-10T09:00', $reset['now']);
    }

    public function testDemoDepartmentWithoutGrossanlassCanTravelAndResetToRealTime(): void
    {
        $controller = $this->controller($this->department(true), 'prod', true);

        self::assertSame(200, $controller->put('dep000000001', $this->putRequest('+3 days'))->getStatusCode());
        $reset = json_decode((string) $controller->delete('dep000000001')->getContent(), true);
        self::assertEqualsWithDelta(time(), strtotime($reset['now']), 2);
        self::assertSame('demo', $reset['mode']);
    }

    public function testNowIsNaiveWallClockWithoutTimezone(): void
    {
        $controller = $this->controller($this->department(true), 'prod', true);

        $put = json_decode((string) $controller->put('dep000000001', $this->putRequest('2030-11-04T10:00:00'))->getContent(), true);

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $put['now']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $put['real_now']);
        self::assertSame('2030-11-04T10:00', substr($put['now'], 0, 16));
    }

    public function testInvalidTargetIsRejected(): void
    {
        $controller = $this->controller($this->department(true), 'prod', true);

        self::assertSame(400, $controller->put('dep000000001', $this->putRequest('not a date'))->getStatusCode());
        self::assertSame(400, $controller->put('dep000000001', $this->putRequest('+50 years'))->getStatusCode());
    }

    public function testNonMemberIsForbiddenEvenForDemo(): void
    {
        $controller = $this->controller($this->department(true), 'prod', false);

        self::assertSame(403, $controller->get('dep000000001')->getStatusCode());
        self::assertSame(403, $controller->put('dep000000001', $this->putRequest('+1 day'))->getStatusCode());
    }

    public function testDevKernelAllowsTravelOnNormalDepartment(): void
    {
        $controller = $this->controller($this->department(false), 'dev', true);

        $get = json_decode((string) $controller->get('dep000000001')->getContent(), true);
        self::assertSame('dev', $get['mode']);
        self::assertTrue($get['can_travel']);
        self::assertSame(200, $controller->put('dep000000001', $this->putRequest('+1 day'))->getStatusCode());
    }

    public function testQuickSelectionIsDerivedFromTheFixedEventPhases(): void
    {
        $department = $this->department(true, true);
        $config = $department->getGrossanlassConfig();
        $config->setPlannedEventStart(new \DateTime('2027-06-04 16:00:00'));
        $config->setPlannedEventEnd(new \DateTime('2027-06-06 16:00:00'));
        $period = static function (string $label, string $start, string $end): DepartmentCalendarPeriod {
            $p = new DepartmentCalendarPeriod();
            $p->setLabel($label);
            $p->setStartDate(new \DateTime(substr($start, 0, 10)))->setStartTime(new \DateTime(substr($start, 11)));
            $p->setEndDate(new \DateTime(substr($end, 0, 10)))->setEndTime(new \DateTime(substr($end, 11)));

            return $p;
        };
        $periods = [
            DepartmentCalendarPeriod::LABEL_AUFBAU => $period('aufbau', '2027-05-19 13:00:00', '2027-06-04 16:00:00'),
            DepartmentCalendarPeriod::LABEL_ABBAU => $period('abbau', '2027-06-06 17:00:00', '2027-06-23 12:00:00'),
        ];
        $controller = $this->controller($department, 'prod', true, $periods);

        $presets = json_decode((string) $controller->get('dep000000001')->getContent(), true)['presets'];

        self::assertSame([
            'months_before_5' => '2027-01-04T16:00:00',
            'months_before_2' => '2027-04-04T16:00:00',
            'build' => '2027-05-19T13:00:00',
            'during_event' => '2027-06-05T12:00:00',
            'teardown' => '2027-06-07T09:00:00',
            'months_after_2' => '2027-08-06T16:00:00',
        ], array_column($presets, 'at', 'key'));
        // Wandzeit ohne Zeitzone: gleiche Werte unabhängig von Sommerzeit (Winter im Januar, Sommer im August)
        foreach ($presets as $preset) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $preset['at']);
        }
    }

    public function testConfiguredQuickSelectionJumpsIntoThePhasesAndInvalidEntriesAreIgnored(): void
    {
        $department = $this->department(true, true);
        $department->getGrossanlassConfig()->setPlannedEventStart(new \DateTime('2027-06-04 16:00:00'));
        $department->getGrossanlassConfig()->setPlannedEventEnd(new \DateTime('2027-06-06 16:00:00'));
        $setting = (new DepartmentSetting())->setSettingKey(DepartmentSetting::KEY_DEMO_CLOCK_PRESETS)->setSettingValue((string) json_encode([
            ['key' => 'months_before_5', 'at' => '2027-01-04T16:00:00'],
            ['key' => 'months_before_2', 'at' => '2027-04-04T16:00:00'],
            ['key' => 'build', 'at' => '2027-05-31T14:00:00'],
            ['key' => 'during_event', 'at' => '2027-06-05T17:00:00'],
            ['key' => 'teardown', 'at' => '2027-06-08T08:00:00'],
            ['key' => 'months_after_2', 'at' => '2027-08-06T16:00:00'],
            ['key' => 'unknown', 'at' => '2027-01-01T00:00:00'],
            ['key' => 'build', 'at' => 'not-a-time'],
        ]));
        $controller = $this->controller($department, 'prod', true, [DepartmentSetting::class => $setting]);

        $presets = json_decode((string) $controller->get('dep000000001')->getContent(), true)['presets'];

        $byKey = array_column($presets, 'at', 'key');
        self::assertSame(['2027-01-04T16:00:00', '2027-04-04T16:00:00', '2027-05-31T14:00:00', '2027-06-05T17:00:00', '2027-06-08T08:00:00', '2027-08-06T16:00:00'], array_values($byKey));
        self::assertCount(6, $presets);
        // Aufbau, Event und Abbau liegen mitten in der jeweiligen Phase (Aufbau 19.05.–04.06., Event 04.–06.06., Abbau 06.–23.06.)
        self::assertGreaterThan('2027-05-19T13:00:00', $byKey['build']);
        self::assertLessThan('2027-06-04T16:00:00', $byKey['build']);
        self::assertGreaterThan('2027-06-04T16:00:00', $byKey['during_event']);
        self::assertLessThan('2027-06-06T16:00:00', $byKey['during_event']);
        self::assertGreaterThan('2027-06-06T17:00:00', $byKey['teardown']);
        self::assertLessThan('2027-06-23T12:00:00', $byKey['teardown']);
        // Die Schnellwahl liest nur: das Lesen verändert weder Uhr noch Anlassdaten
        self::assertNull($department->getDemoClockOffsetSeconds());
        self::assertSame('2027-06-04 16:00:00', $department->getGrossanlassConfig()->getPlannedEventStart()->format('Y-m-d H:i:s'));
    }

    public function testDepartmentWithoutGrossanlassHasNoQuickSelection(): void
    {
        $controller = $this->controller($this->department(true), 'prod', true);

        self::assertSame([], json_decode((string) $controller->get('dep000000001')->getContent(), true)['presets']);
    }

    /** @return iterable<string, array{string}> */
    public static function wallClockTargets(): iterable
    {
        yield 'winter' => ['2027-01-04T16:00:00'];
        yield 'before spring change' => ['2027-03-28T01:59:00'];
        yield 'after spring change' => ['2027-03-28T03:00:00'];
        yield 'event day' => ['2027-06-05T12:00:00'];
        yield 'before autumn change' => ['2027-10-31T02:30:00'];
        yield 'after autumn change' => ['2027-10-31T03:00:00'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wallClockTargets')]
    public function testTravelTargetsKeepTheirWallClockAcrossDaylightSavingChanges(string $target): void
    {
        $controller = $this->controller($this->department(true, true), 'prod', true);

        $body = json_decode((string) $controller->put('dep000000001', $this->putRequest($target))->getContent(), true);

        // Die Fachzeit ist naive Wandzeit: das gesetzte Datum erscheint unverändert (kein Zeitzonen- oder Sommerzeitversatz)
        self::assertEqualsWithDelta(strtotime($target . 'Z'), strtotime($body['now'] . 'Z'), 3);
        self::assertSame(substr($target, 0, 16), substr($body['now'], 0, 16));
    }

    private function putRequest(string $now): Request
    {
        return Request::create('/x', 'PUT', content: (string) json_encode(['now' => $now]));
    }

    private function department(bool $demo, bool $grossanlass = false): Department
    {
        $department = new Department();
        $department->setId('dep000000001');
        $department->setDemoMode($demo);
        if ($grossanlass) {
            $config = new DepartmentGrossanlassConfig();
            $config->setPlannedEventStart(new \DateTime('today'));
            $department->setIsGrossanlass(true);
            $department->setGrossanlassConfig($config);
        }

        return $department;
    }

    /** @param array<string, object> $periods Kalenderperioden nach Label, optional `DepartmentSetting::class` für die Schnellwahl-Einstellung */
    private function controller(Department $department, string $env, bool $member, array $periods = []): DepartmentClockController
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($department);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?object => isset($criteria['settingKey']) ? ($periods[DepartmentSetting::class] ?? null) : ($periods[$criteria['label'] ?? ''] ?? null));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($env);
        $access = $this->createMock(GroupAccessService::class);
        $access->method('userHasDepartmentMembership')->willReturn($member);

        $controller = new DepartmentClockController($em, new BusinessClock($em, $kernel, [new GrossanlassClockOriginResolver()]), $access);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken((new User())->setId('user00000001'), 'api', ['ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        return $controller;
    }
}
