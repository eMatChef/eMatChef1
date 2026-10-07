<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\AuditEvent;
use App\Entity\User;
use App\Service\Auth\SecurityActivityService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class SecurityActivityServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $parameters = [];

    private ?int $maxResults = null;

    public function testQueriesOnlyOwnAllowlistedEventsNewestFirstWithLimit(): void
    {
        $user = (new User())->setId('user00000001');
        $events = [
            $this->event('login_success', ['auth_method' => ['old' => null, 'new' => 'google'], 'secret' => ['old' => null, 'new' => 'SHOULD-NOT-LEAK']]),
            $this->event('totp_enabled', ['totp' => ['old' => false, 'new' => true]]),
            $this->event('trusted_device_created', ['device' => ['old' => null, 'new' => 'Chrome / Windows']]),
        ];

        $result = $this->service($events)->recent($user, 500);

        self::assertSame('user00000001', $this->parameters['user']);
        self::assertSame(SecurityActivityService::MAX_LIMIT, $this->maxResults);
        self::assertContains('login_success', $this->parameters['actions']);
        self::assertNotContains('profile_updated', $this->parameters['actions']);
        self::assertSame(['login_success', 'totp_enabled', 'trusted_device_created'], array_column($result, 'action'));
        self::assertSame('google', $result[0]['detail']);
        self::assertNull($result[1]['detail']);
        self::assertSame('Chrome / Windows', $result[2]['detail']);
        $json = json_encode($result, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('SHOULD-NOT-LEAK', $json);
        self::assertSame(['action', 'created_at', 'detail'], array_keys($result[0]));
    }

    public function testDefaultAndMinimumLimit(): void
    {
        $user = (new User())->setId('user00000001');

        $this->service([])->recent($user);
        self::assertSame(20, $this->maxResults);

        $this->service([])->recent($user, 0);
        self::assertSame(1, $this->maxResults);
    }

    public function testNoCredentialOrCodeActionsAreEverListed(): void
    {
        $user = (new User())->setId('user00000001');
        $this->service([])->recent($user);

        foreach ($this->parameters['actions'] as $action) {
            self::assertDoesNotMatchRegularExpression('/secret|token|challenge|credential/i', $action);
        }
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function event(string $action, array $changes): AuditEvent
    {
        $event = new AuditEvent();
        $event->setAction($action);
        $event->setChanges($changes);

        return $event;
    }

    /**
     * @param list<AuditEvent> $events
     */
    private function service(array $events): SecurityActivityService
    {
        $qb = $this->createMock(QueryBuilder::class);
        foreach (['select', 'from', 'where', 'andWhere', 'orderBy', 'addOrderBy'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('setParameter')->willReturnCallback(function (string $key, mixed $value) use ($qb): QueryBuilder {
            $this->parameters[$key] = $value;

            return $qb;
        });
        $qb->method('setMaxResults')->willReturnCallback(function (int $max) use ($qb): QueryBuilder {
            $this->maxResults = $max;

            return $qb;
        });
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($events);
        $qb->method('getQuery')->willReturn($query);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new SecurityActivityService($em);
    }
}
