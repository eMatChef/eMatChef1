<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Doctrine\DBAL\Connection;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Befristete Übergangsregel für Tokens aus der Zeit vor UserSession.
 *
 * Cutoff = executed_at der Migration, die UserSession einführt. Danach stellt der Code nur noch
 * Tokens mit Sitzung aus. Akzeptiert werden deshalb nur Tokens, die nachweislich vorher entstanden sind:
 * - JWT ohne `sid`: nur mit iat < Cutoff (spätestens nach der JWT-TTL abgelaufen);
 * - Refresh-Token ohne Sitzung: nur mit Erstellung (valid − TTL) < Cutoff (spätestens nach der Refresh-TTL).
 * Ohne Cutoff (Migration nicht protokolliert) wird nichts als Legacy akzeptiert.
 *
 * Entfernen, sobald nach Cutoff + Refresh-TTL keine Legacy-Tokens mehr gültig sein können.
 */
class LegacySessionCutoff
{
    public const MIGRATION_VERSION = 'DoctrineMigrations\\Version20261005120000';

    private ?\DateTimeImmutable $cutoff = null;

    private bool $loaded = false;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%gesdinet_jwt_refresh_token.ttl%')]
        private readonly int $refreshTokenTtl,
    ) {
    }

    public function getCutoff(): ?\DateTimeImmutable
    {
        if (!$this->loaded) {
            $this->loaded = true;
            try {
                $executedAt = $this->connection->fetchOne(
                    'SELECT executed_at FROM doctrine_migration_versions WHERE version = ?',
                    [self::MIGRATION_VERSION]
                );
                $this->cutoff = \is_string($executedAt) && $executedAt !== '' ? new \DateTimeImmutable($executedAt) : null;
            } catch (\Throwable) {
                $this->cutoff = null;
            }
        }

        return $this->cutoff;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function allowsJwtWithoutSid(array $payload): bool
    {
        $cutoff = $this->getCutoff();
        $issuedAt = $payload['iat'] ?? null;

        return $cutoff !== null && is_numeric($issuedAt) && (int) $issuedAt < $cutoff->getTimestamp();
    }

    public function allowsRefreshTokenWithoutSession(RefreshTokenInterface $refreshToken): bool
    {
        $cutoff = $this->getCutoff();
        $validUntil = $refreshToken->getValid();
        if ($cutoff === null || $validUntil === null) {
            return false;
        }

        return $validUntil->getTimestamp() - $this->refreshTokenTtl < $cutoff->getTimestamp();
    }
}
