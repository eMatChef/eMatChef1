<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\AuditEvent;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Auth\SecurityActivityService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class SecurityActivityServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $parameters = [];

    private ?int $maxResults = null;

    /** @var list<string> */
    private array $where = [];

    public function testQueriesOnlyOwnAllowlistedEventsNewestFirstWithLimit(): void
    {
        $user = (new User())->setId('user00000001');
        $events = [
            $this->event('login_success', ['auth_method' => ['old' => null, 'new' => 'google'], 'secret' => ['old' => null, 'new' => 'SHOULD-NOT-LEAK']]),
            $this->event('totp_enabled', ['totp' => ['old' => false, 'new' => true]]),
            $this->event('trusted_device_created', ['device' => ['old' => null, 'new' => 'Chrome / Windows']]),
        ];

        $page = $this->service($events)->page($user, 500);

        self::assertSame('user00000001', $this->parameters['user']);
        self::assertSame(SecurityActivityService::MAX_LIMIT + 1, $this->maxResults);
        self::assertContains('login_success', $this->parameters['actions']);
        self::assertContains('profile_updated', $this->parameters['actions']);
        self::assertNotContains('user_created_self', $this->parameters['actions']);
        self::assertSame(['login_success', 'totp_enabled', 'trusted_device_created'], array_column($page['events'], 'action'));
        self::assertSame('google', $page['events'][0]['auth_method']);
        self::assertNull($page['events'][1]['detail']);
        self::assertSame('Chrome / Windows', $page['events'][2]['detail']);
        self::assertStringNotContainsString('SHOULD-NOT-LEAK', json_encode($page, JSON_THROW_ON_ERROR));
        self::assertNull($page['next_cursor']);
    }

    public function testDefaultAndMinimumLimit(): void
    {
        $user = (new User())->setId('user00000001');

        $this->service([])->page($user);
        self::assertSame(SecurityActivityService::DEFAULT_LIMIT + 1, $this->maxResults);
        self::assertSame(20, SecurityActivityService::DEFAULT_LIMIT);

        $this->service([])->page($user, 0);
        self::assertSame(2, $this->maxResults);
    }

    public function testNoCredentialOrCodeActionsAreEverListed(): void
    {
        $this->service([])->page((new User())->setId('user00000001'));

        foreach ($this->parameters['actions'] as $action) {
            self::assertDoesNotMatchRegularExpression('/secret|token|challenge|credential/i', $action);
        }
    }

    public function testLoginExposesAuthMethodMfaSourceBrowserOsAndServerSideIp(): void
    {
        $event = $this->event('login_success', [
            'auth_method' => ['old' => null, 'new' => 'midata'],
            'mfa_source' => ['old' => null, 'new' => 'recovery_code'],
        ], ip: '203.0.113.9', userAgent: 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0 Safari/537.36');

        $item = $this->service([$event])->page((new User())->setId('user00000001'))['events'][0];

        self::assertSame('midata', $item['auth_method']);
        self::assertSame('recovery_code', $item['mfa_source']);
        self::assertSame('Chrome', $item['browser']);
        self::assertSame('Windows', $item['os']);
        self::assertSame('203.0.113.9', $item['ip_address']);
    }

    public function testForgedAuthMethodAndMfaSourceAreDropped(): void
    {
        $event = $this->event('login_success', [
            'auth_method' => ['old' => null, 'new' => '<script>'],
            'mfa_source' => ['old' => null, 'new' => 'whatever'],
        ]);

        $item = $this->service([$event])->page((new User())->setId('user00000001'))['events'][0];

        self::assertNull($item['auth_method']);
        self::assertNull($item['mfa_source']);
    }

    public function testHistoricEventsWithoutContextStillShowWithoutInventedValues(): void
    {
        $event = $this->event('login_success', ['auth_method' => ['old' => null, 'new' => 'password']]);

        $item = $this->service([$event])->page((new User())->setId('user00000001'))['events'][0];

        self::assertNull($item['ip_address']);
        self::assertNull($item['browser']);
        self::assertNull($item['os']);
        self::assertNull($item['mfa_source']);
        self::assertSame('password', $item['auth_method']);
    }

    public function testMfaSourceFollowsFromRecoveryCodeAndTrustedDeviceEvents(): void
    {
        $items = $this->service([
            $this->event('recovery_code_used', ['recovery_codes' => ['old' => null, 'new' => 'used']]),
            $this->event('trusted_device_used', ['device' => ['old' => null, 'new' => 'Chrome / Windows']]),
            $this->event('totp_enabled', []),
        ])->page((new User())->setId('user00000001'))['events'];

        self::assertSame(['recovery_code', 'trusted_device', null], array_column($items, 'mfa_source'));
    }

    public function testProfileChangesListFieldNamesNeverValues(): void
    {
        $event = $this->event('profile_updated', [
            'first_name' => ['changed' => true],
            'last_name' => ['old' => 'Muster-Alt', 'new' => 'Muster-Neu'],
            'bad key!' => ['changed' => true],
        ]);

        $item = $this->service([$event])->page((new User())->setId('user00000001'))['events'][0];

        self::assertSame(['first_name', 'last_name'], $item['changed_fields']);
        self::assertNull($item['detail']);
        $json = json_encode($item, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Muster', $json);
    }

    public function testActorIsSelfOtherWithNameOrUnknown(): void
    {
        $self = $this->event('user_password_changed', [], actor: 'user00000001');
        $admin = $this->event('membership_role_changed', ['role' => ['old' => 'member', 'new' => 'mw']], actor: 'admin0000001');
        $system = $this->event('user_password_reset', []);

        $items = $this->service([$self, $admin, $system], admins: ['admin0000001' => ['Anna', 'Admin']])
            ->page((new User())->setId('user00000001'))['events'];

        self::assertSame(['type' => 'self', 'name' => null], $items[0]['actor']);
        self::assertSame(['type' => 'other', 'name' => 'Anna Admin'], $items[1]['actor']);
        self::assertSame('mw', $items[1]['detail']);
        self::assertNull($items[2]['actor']);
    }

    public function testIpAndBrowserOfAnotherActingUserAreNeverDisclosedToTheAffectedUser(): void
    {
        $byAdmin = $this->event('membership_role_changed', ['role' => ['old' => 'member', 'new' => 'mw']], ip: '198.51.100.99', userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Safari/605.1.15', actor: 'admin0000001');
        $byMe = $this->event('user_password_changed', [], ip: '203.0.113.9', userAgent: 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36', actor: 'user00000001');

        $items = $this->service([$byAdmin, $byMe], admins: ['admin0000001' => ['Anna', 'Admin']])
            ->page((new User())->setId('user00000001'))['events'];

        self::assertNull($items[0]['ip_address']);
        self::assertNull($items[0]['browser']);
        self::assertNull($items[0]['os']);
        self::assertSame('Anna Admin', $items[0]['actor']['name']);
        self::assertStringNotContainsString('198.51.100.99', json_encode($items, JSON_THROW_ON_ERROR));
        self::assertSame('203.0.113.9', $items[1]['ip_address']);
        self::assertSame('Chrome', $items[1]['browser']);
    }

    public function testPaginationReturnsCursorAndAppliesItToTheNextQuery(): void
    {
        $user = (new User())->setId('user00000001');
        $events = [];
        for ($i = 0; $i < 3; ++$i) {
            $events[] = $this->event('login_success', [], id: 'ae0000000000' . $i);
        }

        $page = $this->service($events)->page($user, 2);

        self::assertCount(2, $page['events']);
        self::assertNotNull($page['next_cursor']);
        self::assertCount(2, $this->where);

        $this->service([])->page($user, 2, $page['next_cursor']);
        self::assertCount(3, $this->where);
        self::assertSame('ae00000000001', $this->parameters['cursorId']);
        self::assertInstanceOf(\DateTime::class, $this->parameters['cursorAt']);
    }

    public function testFiltersByAllowlistedActionAndInclusiveDateRange(): void
    {
        $user = (new User())->setId('user00000001');

        $this->service([])->page($user, 40, null, 'login_success', new \DateTimeImmutable('2026-10-01 15:00'), new \DateTimeImmutable('2026-10-09 08:00'));

        self::assertSame('login_success', $this->parameters['action']);
        self::assertSame('2026-10-01 00:00:00', $this->parameters['from']->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-10 00:00:00', $this->parameters['toExclusive']->format('Y-m-d H:i:s'));
        self::assertSame(41, $this->maxResults);
    }

    public function testNonAllowlistedActionFilterIsRejectedSoOtherAuditEventsStayHidden(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service([])->page((new User())->setId('user00000001'), 40, null, 'material_deleted');
    }

    public function testAuditIndexesCoverTargetAndActorTimeQueriesWithoutDuplicates(): void
    {
        $indexes = [];
        foreach ((new \ReflectionClass(AuditEvent::class))->getAttributes(\Doctrine\ORM\Mapping\Index::class) as $attribute) {
            $indexes[] = $attribute->newInstance()->columns;
        }

        // Die Seitenabfrage filtert target_user_id und sortiert nach created_at (id nur als Tiebreak): vorhandener Index genügt.
        self::assertContains(['target_user_id', 'created_at'], $indexes);
        self::assertContains(['actor_user_id', 'created_at'], $indexes);
        self::assertSame($indexes, array_values(array_unique($indexes, SORT_REGULAR)));
    }

    public function testInvalidCursorIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service([])->page((new User())->setId('user00000001'), 20, 'not-a-cursor');
    }

    public function testPurgeClearsOnlyContextOfSecurityEventsOlderThanRetention(): void
    {
        $now = new \DateTimeImmutable('2026-10-09 12:00:00');
        $service = $this->purgeService($captured);

        $count = $service->purgeExpiredContext($now);

        self::assertSame(7, $count);
        self::assertSame(90, SecurityActivityService::CONTEXT_RETENTION_DAYS);
        self::assertSame('2026-07-11 12:00:00', $captured['cutoff']->format('Y-m-d H:i:s'));
        self::assertContains('login_success', $captured['actions']);
        self::assertSame(['e.ipAddress' => 'NULL', 'e.userAgent' => 'NULL'], $captured['set']);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function event(string $action, array $changes, ?string $ip = null, ?string $userAgent = null, ?string $actor = null, string $id = 'ae00000000000'): AuditEvent
    {
        $event = new AuditEvent();
        $event->setId($id);
        $event->setAction($action);
        $event->setChanges($changes);
        $event->setIpAddress($ip);
        $event->setUserAgent($userAgent);
        $event->setActorUserId($actor);

        return $event;
    }

    /**
     * @param list<AuditEvent>                      $events
     * @param array<string, array{string, string}>  $admins actorId => [first, last]
     */
    private function service(array $events, array $admins = []): SecurityActivityService
    {
        $this->parameters = [];
        $this->where = [];
        $qb = $this->createMock(QueryBuilder::class);
        foreach (['select', 'from', 'orderBy', 'addOrderBy'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('where')->willReturnCallback(function (string $w) use ($qb): QueryBuilder {
            $this->where[] = $w;

            return $qb;
        });
        $qb->method('andWhere')->willReturnCallback(function (string $w) use ($qb): QueryBuilder {
            $this->where[] = $w;

            return $qb;
        });
        $qb->method('setParameter')->willReturnCallback(function (string $key, mixed $value) use ($qb): QueryBuilder {
            $this->parameters[$key] = $value;

            return $qb;
        });
        $qb->method('setMaxResults')->willReturnCallback(function (int $max) use ($qb, $events): QueryBuilder {
            $this->maxResults = $max;

            return $qb;
        });
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturnCallback(function () use ($events): array {
            return \array_slice($events, 0, (int) $this->maxResults);
        });
        $qb->method('getQuery')->willReturn($query);

        $actors = [];
        foreach ($admins as $id => [$first, $last]) {
            $profile = (new Profile())->setId('p_' . $id)->setFirstName($first)->setLastName($last);
            $actors[] = (new User())->setId($id)->setProfile($profile);
        }
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($actors);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->method('getRepository')->willReturn($repo);

        return new SecurityActivityService($em);
    }

    /**
     * @param array<string, mixed>|null $captured
     */
    private function purgeService(?array &$captured): SecurityActivityService
    {
        $captured = ['set' => []];
        $qb = $this->createMock(QueryBuilder::class);
        foreach (['update', 'where', 'andWhere'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('set')->willReturnCallback(function (string $field, string $value) use ($qb, &$captured): QueryBuilder {
            $captured['set'][$field] = $value;

            return $qb;
        });
        $qb->method('setParameter')->willReturnCallback(function (string $key, mixed $value) use ($qb, &$captured): QueryBuilder {
            $captured[$key] = $value;

            return $qb;
        });
        $query = $this->createMock(Query::class);
        $query->method('execute')->willReturn(7);
        $qb->method('getQuery')->willReturn($query);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new SecurityActivityService($em);
    }
}
