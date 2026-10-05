<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\RefreshToken;
use App\Service\Auth\LegacySessionCutoff;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class LegacySessionCutoffTest extends TestCase
{
    private const CUTOFF = '2026-10-05 10:00:00';

    private const TTL = 2592000;

    public function testCutoffIsExecutedAtOfSessionMigration(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(self::stringContains('doctrine_migration_versions'), [LegacySessionCutoff::MIGRATION_VERSION])
            ->willReturn(self::CUTOFF);
        $cutoff = new LegacySessionCutoff($connection, self::TTL);

        self::assertSame(self::CUTOFF, $cutoff->getCutoff()?->format('Y-m-d H:i:s'));
        $cutoff->getCutoff();
    }

    public function testJwtWithoutSidOnlyIfIssuedBeforeCutoff(): void
    {
        $cutoff = $this->cutoff(self::CUTOFF);
        $ts = (new \DateTimeImmutable(self::CUTOFF))->getTimestamp();

        self::assertTrue($cutoff->allowsJwtWithoutSid(['iat' => $ts - 1]));
        self::assertFalse($cutoff->allowsJwtWithoutSid(['iat' => $ts]));
        self::assertFalse($cutoff->allowsJwtWithoutSid(['iat' => $ts + 3600]));
        self::assertFalse($cutoff->allowsJwtWithoutSid([]));
    }

    public function testRefreshTokenWithoutSessionOnlyIfCreatedBeforeCutoff(): void
    {
        $cutoff = $this->cutoff(self::CUTOFF);
        $ts = (new \DateTimeImmutable(self::CUTOFF))->getTimestamp();

        self::assertTrue($cutoff->allowsRefreshTokenWithoutSession($this->refreshToken($ts - 60 + self::TTL)));
        self::assertFalse($cutoff->allowsRefreshTokenWithoutSession($this->refreshToken($ts + 60 + self::TTL)));
    }

    public function testWithoutRecordedMigrationNothingIsLegacy(): void
    {
        $cutoff = $this->cutoff(false);

        self::assertNull($cutoff->getCutoff());
        self::assertFalse($cutoff->allowsJwtWithoutSid(['iat' => 1]));
        self::assertFalse($cutoff->allowsRefreshTokenWithoutSession($this->refreshToken(time())));
    }

    public function testDatabaseErrorMeansNoLegacy(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException(new \RuntimeException('db down'));

        self::assertFalse((new LegacySessionCutoff($connection, self::TTL))->allowsJwtWithoutSid(['iat' => 1]));
    }

    private function cutoff(string|false $executedAt): LegacySessionCutoff
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($executedAt);

        return new LegacySessionCutoff($connection, self::TTL);
    }

    private function refreshToken(int $validUntil): RefreshToken
    {
        $token = new RefreshToken();
        $token->setValid((new \DateTime())->setTimestamp($validUntil));

        return $token;
    }
}
