<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Widerruft Gesdinet-Refresh-Tokens eines Users (Spalte username = User-Identifier = profileId).
 *
 * Bereits ausgestellte JWTs bleiben bis zu ihrer TTL gültig, sofern der UserChecker sie nicht
 * ablehnt (Account-State). Session-genauer Widerruf folgt mit UserSession/sid.
 */
final class RefreshTokenRevoker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int Anzahl gelöschter Refresh-Tokens
     */
    public function revokeAllForUser(User $user, ?string $keepToken = null): int
    {
        $username = $user->getUserIdentifier();
        if ($username === '') {
            return 0;
        }

        $dql = 'DELETE FROM ' . RefreshToken::class . ' r WHERE r.username = :username';
        if ($keepToken !== null && $keepToken !== '') {
            $dql .= ' AND r.refreshToken <> :keepToken';
        }

        $query = $this->entityManager->createQuery($dql)->setParameter('username', $username);
        if ($keepToken !== null && $keepToken !== '') {
            $query->setParameter('keepToken', $keepToken);
        }

        return (int) $query->execute();
    }

    /**
     * Nach einem State-Wechsel weg von `active`: alle Refresh-Tokens widerrufen.
     *
     * @return bool true, wenn widerrufen wurde
     */
    public function revokeIfDeactivated(User $user, string $previousState): bool
    {
        $state = $user->getState();
        if ($state === 'active' || $state === $previousState) {
            return false;
        }

        $this->revokeAllForUser($user);

        return true;
    }
}
