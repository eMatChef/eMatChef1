<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ProfileSecurityController;
use App\Entity\Profile;
use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\Repository\UserSessionRepository;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\SecurityActivityService;
use App\Service\Auth\TrustedDeviceService;
use App\Service\Auth\UserSessionManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ProfileSecurityControllerTest extends TestCase
{
    private User $me;

    private User $other;

    private UserSession $current;

    private UserSession $second;

    private UserSession $foreign;

    /** @var list<UserSession> */
    private array $mySessions = [];

    /** @var list<string> */
    private array $calls = [];

    /** @var list<array{string|null, int, string|null}> */
    private array $activityCalls = [];

    /** @var list<array{string|null, string|null, string|null}> */
    private array $activityFilters = [];

    private CurrentAuthSession $currentHolder;

    private \App\Service\Auth\LinkResultStore $linkResults;

    private TrustedDeviceService $trusted;

    protected function setUp(): void
    {
        $this->calls = [];
        $this->activityCalls = [];
        $this->activityFilters = [];
        $this->linkResults = new \App\Service\Auth\LinkResultStore(new \Symfony\Component\Cache\Adapter\ArrayAdapter());
        $this->me = $this->user('me', 'p_me');
        $this->other = $this->user('other', 'p_other');
        $this->current = new UserSession($this->me, AuthMethod::PASSWORD, 'Mozilla/5.0 (Windows NT 10.0) Chrome/120');
        $this->second = new UserSession($this->me, AuthMethod::GOOGLE, 'Mozilla/5.0 (iPhone) Safari/604');
        $this->foreign = new UserSession($this->other, AuthMethod::PASSWORD, null);
        $this->mySessions = [$this->current, $this->second];
        $this->currentHolder = new CurrentAuthSession();
        $this->currentHolder->setAuthenticated($this->current);
    }

    public function testListsOnlyOwnSessionsAndMarksTheCurrentOne(): void
    {
        $response = $this->controller()->sessions('p_me');
        $data = json_decode((string) $response->getContent(), true)['sessions'];

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $data);
        self::assertTrue($data[0]['current']);
        self::assertFalse($data[1]['current']);
        self::assertSame('Chrome', $data[0]['browser']);
        self::assertSame('Windows', $data[0]['os']);
        self::assertSame('password', $data[0]['auth_method']);
        self::assertSame('google', $data[1]['auth_method']);
        self::assertArrayNotHasKey('user_agent', $data[0]);
        self::assertStringNotContainsString('ip', implode(',', array_keys($data[0])));
        self::assertFalse($data[0]['mfa_verified']);
    }

    public function testForeignProfileIsForbidden(): void
    {
        foreach ([
            $this->controller()->sessions('p_other'),
            $this->controller()->revokeOthers('p_other'),
            $this->controller()->revokeSession('p_other', $this->foreign->getId()),
            $this->controller()->trustedDevices('p_other', new Request()),
            $this->controller()->revokeTrustedDevice('p_other', 'd1', new Request()),
            $this->controller()->activity('p_other', new Request()),
        ] as $response) {
            self::assertSame(403, $response->getStatusCode());
        }
        self::assertSame([], $this->calls);
        self::assertSame([], $this->activityCalls);
    }

    public function testActivityIsServedOnlyForTheOwnUserWithLimitCursorAndNoStore(): void
    {
        $response = $this->controller()->activity($this->me->getProfileId(), new Request(['limit' => '5', 'cursor' => 'abc']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([[$this->me->getId(), 5, 'abc']], $this->activityCalls);
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $body = json_decode((string) $response->getContent(), true);
        self::assertSame([], $body['events']);
        self::assertNull($body['next_cursor']);
        self::assertContains('login_success', $body['actions']);
    }

    public function testActivityDefaultsToTwentyEventsAndRejectsInvalidCursor(): void
    {
        $this->controller()->activity($this->me->getProfileId(), new Request());
        self::assertSame([[$this->me->getId(), 20, null]], $this->activityCalls);

        $response = $this->controller()->activity($this->me->getProfileId(), new Request(['cursor' => 'bad']));
        self::assertSame(400, $response->getStatusCode());
    }

    public function testActivityPassesFiltersAndRejectsInvalidOnes(): void
    {
        $ok = $this->controller()->activity($this->me->getProfileId(), new Request(['action' => 'login_success', 'from' => '2026-10-01', 'to' => '2026-10-09']));
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame([['login_success', '2026-10-01', '2026-10-09']], $this->activityFilters);

        self::assertSame(400, $this->controller()->activity($this->me->getProfileId(), new Request(['action' => 'nope']))->getStatusCode());
        self::assertSame(400, $this->controller()->activity($this->me->getProfileId(), new Request(['from' => '2026-13-40']))->getStatusCode());
        self::assertSame(400, $this->controller()->activity($this->me->getProfileId(), new Request(['to' => 'yesterday']))->getStatusCode());
    }

    public function testCurrentSessionCannotBeRevokedThroughTheOtherSessionEndpoint(): void
    {
        $response = $this->controller()->revokeSession('p_me', $this->current->getId());

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('current_session', json_decode((string) $response->getContent(), true)['error']);
        self::assertSame([], $this->calls);
        self::assertFalse($this->current->isRevoked());
    }

    public function testAnotherOwnSessionIsRevoked(): void
    {
        $response = $this->controller()->revokeSession('p_me', $this->second->getId());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['revokeOther:' . $this->second->getId()], $this->calls);
    }

    public function testForeignAndUnknownSessionsAreIndistinguishable404(): void
    {
        self::assertSame(404, $this->controller()->revokeSession('p_me', $this->foreign->getId())->getStatusCode());
        self::assertSame(404, $this->controller()->revokeSession('p_me', '00000000-0000-4000-8000-000000000000')->getStatusCode());
        self::assertSame([], $this->calls);
    }

    public function testRevokeOthersKeepsTheCurrentSession(): void
    {
        $response = $this->controller()->revokeOthers('p_me');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['revokeOthers:' . $this->current->getId()], $this->calls);
        self::assertSame(3, json_decode((string) $response->getContent(), true)['revoked']);
    }

    public function testSessionWithoutSidCannotManageSessions(): void
    {
        $this->currentHolder->setAuthenticated(null);

        self::assertSame(409, $this->controller()->revokeOthers('p_me')->getStatusCode());
        self::assertSame(409, $this->controller()->revokeSession('p_me', $this->second->getId())->getStatusCode());
        self::assertSame([], $this->calls);
    }

    public function testTrustedDevicesAreListedWithoutSecretsAndRevocableOnlyByOwner(): void
    {
        $device = new TrustedDevice('d1', $this->me, str_repeat('b', 64), 'Chrome / Windows', new \DateTime('+30 days'));
        $this->trusted = $this->createMock(TrustedDeviceService::class);
        $this->trusted->method('listActive')->willReturn([$device]);
        $this->trusted->method('effectiveExpiry')->willReturn(new \DateTime('+30 days'));
        $this->trusted->method('isCurrent')->willReturn(true);
        $this->trusted->method('revoke')->willReturnCallback(function (User $user, string $id): bool {
            $this->calls[] = 'revokeDevice:' . $id;

            return $id === 'd1';
        });

        $list = json_decode((string) $this->controller()->trustedDevices('p_me', new Request())->getContent(), true)['trusted_devices'];
        self::assertSame('Chrome / Windows', $list[0]['label']);
        self::assertTrue($list[0]['current']);
        self::assertStringNotContainsString('credential', json_encode($list, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString(str_repeat('b', 64), json_encode($list, JSON_THROW_ON_ERROR));

        self::assertSame(200, $this->controller()->revokeTrustedDevice('p_me', 'd1', new Request())->getStatusCode());
        self::assertSame(404, $this->controller()->revokeTrustedDevice('p_me', 'dX', new Request())->getStatusCode());
    }

    private \Doctrine\ORM\EntityManagerInterface $entityManager;

    private function controller(): ProfileSecurityController
    {
        $manager = $this->createMock(UserSessionManager::class);
        $manager->method('listActiveForUser')->willReturnCallback(fn (User $u): array => array_values(array_filter(
            $this->mySessions,
            static fn (UserSession $s): bool => $s->getUser()->getId() === $u->getId()
        )));
        $manager->method('revokeOtherSessionByUser')->willReturnCallback(function (User $u, UserSession $target): void {
            $this->calls[] = 'revokeOther:' . $target->getId();
        });
        $manager->method('revokeOtherSessionsByUser')->willReturnCallback(function (User $u, UserSession $current): int {
            $this->calls[] = 'revokeOthers:' . $current->getId();

            return 3;
        });

        $sessions = $this->createMock(UserSessionRepository::class);
        $sessions->method('findOneById')->willReturnCallback(function (string $sid): ?UserSession {
            foreach ([$this->current, $this->second, $this->foreign] as $s) {
                if ($s->getId() === $sid) {
                    return $s;
                }
            }

            return null;
        });

        $activity = $this->createMock(SecurityActivityService::class);
        $activity->method('page')->willReturnCallback(function (User $user, int $limit, ?string $cursor, ?string $action, ?\DateTimeInterface $from, ?\DateTimeInterface $to): array {
            $this->activityCalls[] = [$user->getId(), $limit, $cursor];
            $this->activityFilters[] = [$action, $from?->format('Y-m-d'), $to?->format('Y-m-d')];
            if ($action === 'nope') {
                throw new \InvalidArgumentException('invalid_event_type');
            }
            if ($cursor === 'bad') {
                throw new \InvalidArgumentException('invalid_cursor');
            }

            return ['events' => [], 'next_cursor' => null];
        });
        $trusted = $this->trusted ?? $this->createMock(TrustedDeviceService::class);

        $controller = new ProfileSecurityController(
            $manager,
            $sessions,
            $this->currentHolder,
            $trusted,
            $activity,
            $this->identityService(),
            $this->googleClient(true),
            $this->midataClient(false),
            $this->linkResults,
        );
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($this->me, 'api', ['ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $storage);
        $controller->setContainer($container);

        return $controller;
    }

    private function identityService(): \App\Service\Auth\ExternalIdentityService
    {
        $this->entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);

        return new \App\Service\Auth\ExternalIdentityService(
            $this->entityManager,
            $this->createMock(\App\Repository\ExternalIdentityRepository::class),
            $this->createMock(\App\Service\AuditLogger::class),
        );
    }

    private function googleClient(bool $configured): \App\Service\Auth\GoogleOAuthClient
    {
        return new \App\Service\Auth\GoogleOAuthClient(
            new \Symfony\Component\HttpClient\MockHttpClient(),
            'https://app.example.test',
            $configured ? 'id' : '',
            $configured ? 'secret' : '',
        );
    }

    private function midataClient(bool $configured): \App\Service\Auth\HitobitoOAuthClient
    {
        return new \App\Service\Auth\HitobitoOAuthClient(
            new \Symfony\Component\HttpClient\MockHttpClient(),
            'https://app.example.test',
            'https://db.scout.ch',
            $configured ? 'id' : '',
            $configured ? 'secret' : '',
            'https://app.example.test/api/auth/midata/callback',
        );
    }

    private function identity(User $user, string $provider, string $externalId, string $id, ?string $name = null, ?string $email = null): \App\Entity\ExternalIdentity
    {
        $identity = (new \App\Entity\ExternalIdentity())->setId($id)->setProvider($provider)->setExternalUserId($externalId)->setDisplayName($name)->setEmail($email);
        $user->addExternalIdentity($identity);

        return $identity;
    }

    public function testListsOnlyActuallyLinkedIdentitiesIndividuallyIncludingSeveralOfOneProvider(): void
    {
        $this->me->setEmailVerified(true);
        $this->identity($this->me, 'google', 'g-sub-111111', 'idn000000001', 'Anna A', 'anna@gmail.test');
        $this->identity($this->me, 'google', 'g-sub-222222', 'idn000000002', null, 'anna.work@gmail.test');
        $this->identity($this->other, 'midata', 'm-other', 'idn000000009');

        $body = json_decode((string) $this->controller()->externalIdentities($this->me->getProfileId())->getContent(), true);

        self::assertSame(['idn000000001', 'idn000000002'], array_column($body['identities'], 'id'));
        self::assertSame(['google', 'google'], array_column($body['identities'], 'provider'));
        self::assertSame('Anna A', $body['identities'][0]['display_name']);
        self::assertSame('anna.work@gmail.test', $body['identities'][1]['email']);
        // Nie die volle externe Kennung oder Tokens
        self::assertSame('…1111', $body['identities'][0]['external_id_hint']);
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('g-sub-111111', $json);
        self::assertStringNotContainsString('m-other', $json);
        self::assertDoesNotMatchRegularExpression('/token|secret|password/i', $json);
        self::assertSame(
            [['provider' => 'google', 'label' => 'Google', 'configured' => true], ['provider' => 'midata', 'label' => 'MiData / db.scout.ch', 'configured' => false]],
            $body['providers'],
        );
    }

    public function testLinkResultIsDeliveredOnceAndOnlyForTheCurrentSession(): void
    {
        $controller = $this->controller();
        self::assertNull(json_decode((string) $controller->linkResult($this->me->getProfileId())->getContent(), true)['result'], 'ohne Ergebnis (präparierte URL) nichts');

        $this->linkResults->put($this->current->getId(), 'google', 'error', 'link_conflict');
        $this->linkResults->put($this->second->getId(), 'midata', 'linked', null);

        $first = json_decode((string) $controller->linkResult($this->me->getProfileId())->getContent(), true)['result'];
        self::assertSame(['provider' => 'google', 'status' => 'error', 'reason' => 'link_conflict'], $first);
        self::assertNull(json_decode((string) $controller->linkResult($this->me->getProfileId())->getContent(), true)['result'], 'einmalig');
        self::assertSame(403, $controller->linkResult($this->other->getProfileId())->getStatusCode());
        // Das Ergebnis einer anderen Sitzung desselben Users bleibt für diese unsichtbar
        self::assertSame('linked', $this->linkResults->take($this->second->getId())['status']);
    }

    public function testEmptyListHasNoPlaceholderRowsForUnlinkedProviders(): void
    {
        $body = json_decode((string) $this->controller()->externalIdentities($this->me->getProfileId())->getContent(), true);

        self::assertSame([], $body['identities']);
    }

    public function testLastLoginMethodCannotBeDisconnected(): void
    {
        $this->me->setEmailVerified(false);
        $this->identity($this->me, 'midata', 'm1', 'idn000000001');

        $response = $this->controller()->disconnectExternalIdentity($this->me->getProfileId(), 'idn000000001');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('last_login_method', json_decode((string) $response->getContent(), true)['error']);
        self::assertCount(1, $this->me->getExternalIdentities());
    }

    public function testDisconnectRemovesExactlyTheChosenIdentityOfSeveralOfOneProvider(): void
    {
        $this->me->setEmailVerified(true);
        $first = $this->identity($this->me, 'google', 'g1', 'idn000000001');
        $second = $this->identity($this->me, 'google', 'g2', 'idn000000002');
        $controller = $this->controller();
        $removed = [];
        $this->entityManager->method('remove')->willReturnCallback(function (object $o) use (&$removed): void {
            $removed[] = $o;
        });

        $response = $controller->disconnectExternalIdentity($this->me->getProfileId(), 'idn000000001');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([$first], $removed);
        self::assertSame([$second], array_values($this->me->getExternalIdentities()->toArray()));
    }

    public function testForeignOrUnknownIdentityIsNotFoundAndForeignProfileForbidden(): void
    {
        $this->me->setEmailVerified(true);
        $this->identity($this->other, 'midata', 'm-other', 'idn000000009');
        $controller = $this->controller();

        self::assertSame(404, $controller->disconnectExternalIdentity($this->me->getProfileId(), 'idn000000009')->getStatusCode());
        self::assertSame(404, $controller->disconnectExternalIdentity($this->me->getProfileId(), 'nope')->getStatusCode());
        self::assertSame(403, $controller->disconnectExternalIdentity($this->other->getProfileId(), 'idn000000009')->getStatusCode());
        self::assertSame(403, $controller->externalIdentities($this->other->getProfileId())->getStatusCode());
        self::assertCount(1, $this->other->getExternalIdentities());
    }

    private function user(string $id, string $profileId): User
    {
        $profile = new Profile();
        $profile->setId($profileId);
        $profile->setEmail($id . '@x.test');
        $user = new User();
        $user->setId($id);
        $user->setProfileId($profileId);
        $user->setProfile($profile);

        return $user;
    }
}
