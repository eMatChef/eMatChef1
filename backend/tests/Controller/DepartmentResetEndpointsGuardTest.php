<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DepartmentController;
use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\DepartmentResetService;
use App\Service\DevEnvironmentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

final class DepartmentResetEndpointsGuardTest extends TestCase
{
    /** @return iterable<string, array{string, bool, string, bool, bool}> kernel, devTools, name, destructiveFlag, allowed */
    public static function environments(): iterable
    {
        yield 'local' => ['dev', true, 'local', false, true];
        yield 'local, empty name' => ['dev', true, '', false, true];
        yield 'develop with flag' => ['prod', true, 'develop', true, true];
        yield 'develop without flag' => ['prod', true, 'develop', false, false];
        yield 'staging, dev tools and flag' => ['prod', true, 'staging', true, false];
        yield 'production, dev tools and flag' => ['prod', true, 'production', true, false];
        yield 'production name on dev kernel' => ['dev', true, 'production', true, false];
        yield 'prod kernel, dev tools only' => ['prod', true, '', true, false];
        yield 'dev tools off' => ['prod', false, 'develop', true, false];
    }

    #[DataProvider('environments')]
    public function testResetEndpoints(string $kernelEnv, bool $devTools, string $name, bool $flag, bool $allowed): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($kernelEnv);
        $dev = $this->createMock(DevEnvironmentService::class);
        $dev->method('isDevToolsEnabled')->willReturn($devTools);
        $reset = $this->createMock(DepartmentResetService::class);
        $reset->expects(self::never())->method('resetDepartment');
        $reset->expects(self::never())->method('resetActivities');

        $controller = (new \ReflectionClass(DepartmentController::class))->newInstanceWithoutConstructor();
        foreach (['demoEnvironmentGuard' => new DemoEnvironmentGuard($kernel, $dev, $name, $flag), 'departmentResetService' => $reset] as $prop => $value) {
            (new \ReflectionProperty(DepartmentController::class, $prop))->setValue($controller, $value);
        }
        $container = new Container();
        $container->set('security.token_storage', new TokenStorage());
        $controller->setContainer($container);

        foreach (['resetDb', 'resetActivities'] as $action) {
            $response = $controller->$action('dept1');
            $body = json_decode((string) $response->getContent(), true);

            self::assertSame(403, $response->getStatusCode(), $action);
            // Erlaubte Umgebung: weiter zur bestehenden Authentifizierung (hier: kein User). Gesperrt: Umgebungs-Sperre.
            self::assertSame($allowed ? 'Unauthorized' : 'Nur in Dev/Test verfügbar', $body['error'], $action);
        }
    }
}
