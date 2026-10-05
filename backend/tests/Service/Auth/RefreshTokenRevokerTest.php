<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Service\Auth\RefreshTokenRevoker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;

final class RefreshTokenRevokerTest extends TestCase
{
    /** @var list<string> */
    private array $dql = [];

    /** @var array<string, mixed> */
    private array $parameters = [];

    private int $executions = 0;

    public function testRevokesAllRefreshTokensOfUser(): void
    {
        $revoked = $this->revoker(3)->revokeAllForUser($this->user('active'));

        self::assertSame(3, $revoked);
        self::assertSame(['DELETE FROM ' . RefreshToken::class . ' r WHERE r.username = :username'], $this->dql);
        self::assertSame(['username' => 'profile_1'], $this->parameters);
        self::assertSame(1, $this->executions);
    }

    public function testKeepsCurrentRefreshToken(): void
    {
        $this->revoker(2)->revokeAllForUser($this->user('active'), 'current-token');

        self::assertSame(
            ['DELETE FROM ' . RefreshToken::class . ' r WHERE r.username = :username AND r.refreshToken <> :keepToken'],
            $this->dql
        );
        self::assertSame(['username' => 'profile_1', 'keepToken' => 'current-token'], $this->parameters);
    }

    public function testEmptyKeepTokenRevokesAll(): void
    {
        $this->revoker(1)->revokeAllForUser($this->user('active'), '');

        self::assertStringNotContainsString('keepToken', $this->dql[0]);
        self::assertSame(['username' => 'profile_1'], $this->parameters);
    }

    public function testDeactivationToInactiveRevokesTokens(): void
    {
        self::assertTrue($this->revoker(4)->revokeIfDeactivated($this->user('inactive'), 'active'));
        self::assertSame(1, $this->executions);
    }

    public function testDeactivationToDisabledRevokesTokens(): void
    {
        self::assertTrue($this->revoker(4)->revokeIfDeactivated($this->user('disabled'), 'active'));
        self::assertSame(1, $this->executions);
    }

    public function testUnchangedActiveStateDoesNotRevoke(): void
    {
        self::assertFalse($this->revoker(0)->revokeIfDeactivated($this->user('active'), 'active'));
        self::assertSame(0, $this->executions);
    }

    public function testReactivationDoesNotRevoke(): void
    {
        self::assertFalse($this->revoker(0)->revokeIfDeactivated($this->user('active'), 'disabled'));
        self::assertSame(0, $this->executions);
    }

    public function testUnchangedInactiveStateDoesNotRevokeAgain(): void
    {
        self::assertFalse($this->revoker(0)->revokeIfDeactivated($this->user('inactive'), 'inactive'));
        self::assertSame(0, $this->executions);
    }

    private function revoker(int $deletedRows): RefreshTokenRevoker
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnCallback(function (string $key, mixed $value) use ($query): Query {
            $this->parameters[$key] = $value;

            return $query;
        });
        $query->method('execute')->willReturnCallback(function () use ($deletedRows): int {
            ++$this->executions;

            return $deletedRows;
        });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQuery')->willReturnCallback(function (string $dql) use ($query): Query {
            $this->dql[] = $dql;

            return $query;
        });

        return new RefreshTokenRevoker($entityManager);
    }

    private function user(string $state): User
    {
        $user = new User();
        $user->setId('user_1');
        $user->setProfileId('profile_1');
        $user->setState($state);

        return $user;
    }
}
