<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DepartmentClockController;
use App\Entity\Department;
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

    private function controller(Department $department, string $env, bool $member): DepartmentClockController
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($department);
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
