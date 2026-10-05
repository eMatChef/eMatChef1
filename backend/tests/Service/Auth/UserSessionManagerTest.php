<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\Service\Auth\LegacySessionCutoff;
use App\Service\Auth\RefreshTokenRevoker;
use App\Service\Auth\UserSessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class UserSessionManagerTest extends TestCase
{
    /** @var list<string> */
    private array $dql = [];

    /** @var list<array<string, mixed>> */
    private array $parameters = [];

    /** @var list<object> */
    private array $persisted = [];

    private int $flushes = 0;

    /** @var list<array{string, mixed...}> */
    private array $revokerCalls = [];

    private bool $legacyAllowed = false;

    public function testStartSessionPersistsOneSessionWithUserAgent(): void
    {
        $user = $this->user('u1');

        $session = $this->manager('Mozilla/5.0 Test')->startSession($user, AuthMethod::PASSWORD);

        self::assertSame([$session], $this->persisted);
        self::assertSame(1, $this->flushes);
        self::assertSame($user, $session->getUser());
        self::assertSame(AuthMethod::PASSWORD, $session->getAuthMethod());
        self::assertSame('Mozilla/5.0 Test', $session->getUserAgent());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $session->getId());
        self::assertNull($session->getMfaVerifiedAt());
        self::assertNull($session->getStepUpAt());
        self::assertFalse($session->isRevoked());
    }

    public function testRefreshKeepsExistingSession(): void
    {
        $user = $this->user('u1');
        $session = new UserSession($user, AuthMethod::GOOGLE);
        $token = (new RefreshToken())->setSession($session);

        self::assertSame($session, $this->manager()->resolveForRefresh($token, $user));
        self::assertSame([], $this->persisted);
        self::assertStringContainsString('SET s.lastSeenAt = :now WHERE s.id = :id', $this->dql[0]);
    }

    public function testRefreshOfRevokedSessionIsRejected(): void
    {
        $user = $this->user('u1');
        $session = (new UserSession($user, AuthMethod::PASSWORD))->revoke('logout');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->manager()->resolveForRefresh((new RefreshToken())->setSession($session), $user);
    }

    public function testRefreshOfForeignSessionIsRejected(): void
    {
        $session = new UserSession($this->user('other'), AuthMethod::PASSWORD);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->manager()->resolveForRefresh((new RefreshToken())->setSession($session), $this->user('u1'));
    }

    public function testLegacyRefreshTokenWithinTransitionCreatesLegacySession(): void
    {
        $this->legacyAllowed = true;
        $user = $this->user('u1');

        $session = $this->manager()->resolveForRefresh(new RefreshToken(), $user);

        self::assertSame(AuthMethod::LEGACY, $session->getAuthMethod());
        self::assertSame([$session], $this->persisted);
    }

    public function testLegacyRefreshTokenOutsideTransitionIsRejected(): void
    {
        $this->legacyAllowed = false;

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->manager()->resolveForRefresh(new RefreshToken(), $this->user('u1'));
    }

    public function testRevokeSessionMarksSessionAndDeletesItsTokens(): void
    {
        $session = new UserSession($this->user('u1'), AuthMethod::PASSWORD);

        $this->manager()->revokeSession($session, UserSessionManager::REASON_LOGOUT);

        self::assertTrue($session->isRevoked());
        self::assertSame('logout', $session->getRevokedReason());
        self::assertSame(1, $this->flushes);
        self::assertSame([['revokeForSession', $session->getId()]], $this->revokerCalls);
    }

    public function testRevokeAllForUserKeepsCurrentSession(): void
    {
        $user = $this->user('u1');
        $keep = new UserSession($user, AuthMethod::PASSWORD);

        self::assertSame(3, $this->manager()->revokeAllForUser($user, 'password_change', $keep, 'legacy-token'));

        self::assertStringContainsString('WHERE s.user = :user AND s.revokedAt IS NULL AND s.id <> :keepId', $this->dql[0]);
        self::assertSame('password_change', $this->parameters[0]['reason']);
        self::assertSame('u1', $this->parameters[0]['user']);
        self::assertSame($keep->getId(), $this->parameters[0]['keepId']);
        self::assertSame([['revokeAllForUser', $user, 'legacy-token', $keep->getId()]], $this->revokerCalls);
    }

    public function testRevokeAllForUserWithoutKeep(): void
    {
        $user = $this->user('u1');

        $this->manager()->revokeAllForUser($user, 'password_reset');

        self::assertStringNotContainsString('keepId', $this->dql[0]);
        self::assertSame([['revokeAllForUser', $user, null, null]], $this->revokerCalls);
    }

    public function testDeactivationToInactiveRevokesEverything(): void
    {
        $user = $this->user('u1', 'inactive');

        self::assertTrue($this->manager()->revokeAllIfDeactivated($user, 'active'));
        self::assertSame('account_disabled', $this->parameters[0]['reason']);
        self::assertCount(1, $this->revokerCalls);
    }

    public function testDeactivationToDisabledRevokesEverything(): void
    {
        self::assertTrue($this->manager()->revokeAllIfDeactivated($this->user('u1', 'disabled'), 'active'));
        self::assertCount(1, $this->revokerCalls);
    }

    public function testUnchangedActiveStateDoesNotRevoke(): void
    {
        self::assertFalse($this->manager()->revokeAllIfDeactivated($this->user('u1', 'active'), 'active'));
        self::assertSame([], $this->dql);
        self::assertSame([], $this->revokerCalls);
    }

    public function testReactivationDoesNotRevoke(): void
    {
        self::assertFalse($this->manager()->revokeAllIfDeactivated($this->user('u1', 'active'), 'disabled'));
        self::assertSame([], $this->revokerCalls);
    }

    public function testUnchangedInactiveStateDoesNotRevokeAgain(): void
    {
        self::assertFalse($this->manager()->revokeAllIfDeactivated($this->user('u1', 'inactive'), 'inactive'));
        self::assertSame([], $this->revokerCalls);
    }

    public function testRecentLastSeenIsNotWritten(): void
    {
        $session = new UserSession($this->user('u1'), AuthMethod::PASSWORD);

        $this->manager()->touchLastSeen($session);

        self::assertSame([], $this->dql);
    }

    public function testStaleLastSeenIsWrittenConditionally(): void
    {
        $session = new UserSession($this->user('u1'), AuthMethod::PASSWORD);
        $session->setLastSeenAt(new \DateTime('-10 minutes'));

        $this->manager()->touchLastSeen($session);

        self::assertSame(
            'UPDATE ' . UserSession::class . ' s SET s.lastSeenAt = :now WHERE s.id = :id AND s.lastSeenAt < :threshold',
            $this->dql[0]
        );
        self::assertGreaterThan(new \DateTime('-1 minute'), $session->getLastSeenAt());
    }

    private function manager(?string $userAgent = null): UserSessionManager
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushes;
        });
        $entityManager->method('createQuery')->willReturnCallback(function (string $dql): Query {
            $this->dql[] = $dql;
            $index = \count($this->dql) - 1;
            $this->parameters[$index] = [];
            $query = $this->createMock(Query::class);
            $query->method('setParameter')->willReturnCallback(function (string $key, mixed $value) use ($query, $index): Query {
                $this->parameters[$index][$key] = $value;

                return $query;
            });
            $query->method('execute')->willReturn(3);

            return $query;
        });

        $revoker = $this->createMock(RefreshTokenRevoker::class);
        $revoker->method('revokeForSession')->willReturnCallback(function (string $sid): int {
            $this->revokerCalls[] = ['revokeForSession', $sid];

            return 1;
        });
        $revoker->method('revokeAllForUser')->willReturnCallback(function (User $user, ?string $keepToken = null, ?string $keepSessionId = null): int {
            $this->revokerCalls[] = ['revokeAllForUser', $user, $keepToken, $keepSessionId];

            return 1;
        });

        $cutoff = $this->createMock(LegacySessionCutoff::class);
        $cutoff->method('allowsRefreshTokenWithoutSession')->willReturnCallback(fn (): bool => $this->legacyAllowed);

        $requestStack = new RequestStack();
        $request = new Request();
        if ($userAgent !== null) {
            $request->headers->set('User-Agent', $userAgent);
        }
        $requestStack->push($request);

        return new UserSessionManager($entityManager, $revoker, $cutoff, $requestStack);
    }

    private function user(string $id, string $state = 'active'): User
    {
        $user = new User();
        $user->setId($id);
        $user->setProfileId('p_' . $id);
        $user->setState($state);

        return $user;
    }
}
