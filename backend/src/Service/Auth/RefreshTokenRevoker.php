<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Widerruft Gesdinet-Refresh-Tokens eines Users (Spalte username = User-Identifier = profileId)
 * bzw. einer UserSession. Sitzungen selbst widerruft UserSessionManager.
 */
class RefreshTokenRevoker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param string|null $keepToken     dieses Token behalten (z. B. aktuelles Legacy-Token ohne Sitzung)
     * @param string|null $keepSessionId Tokens dieser Sitzung behalten
     *
     * @return int Anzahl gelöschter Refresh-Tokens
     */
    public function revokeAllForUser(User $user, ?string $keepToken = null, ?string $keepSessionId = null): int
    {
        $username = $user->getUserIdentifier();
        if ($username === '') {
            return 0;
        }

        $dql = 'DELETE FROM ' . RefreshToken::class . ' r WHERE r.username = :username';
        $parameters = ['username' => $username];
        if ($keepToken !== null && $keepToken !== '') {
            $dql .= ' AND r.refreshToken <> :keepToken';
            $parameters['keepToken'] = $keepToken;
        }
        if ($keepSessionId !== null && $keepSessionId !== '') {
            $dql .= ' AND (r.session IS NULL OR r.session <> :keepSessionId)';
            $parameters['keepSessionId'] = $keepSessionId;
        }

        $query = $this->entityManager->createQuery($dql);
        foreach ($parameters as $name => $value) {
            $query->setParameter($name, $value);
        }

        return (int) $query->execute();
    }

    /**
     * @return int Anzahl gelöschter Refresh-Tokens
     */
    public function revokeForSession(string $sessionId): int
    {
        return (int) $this->entityManager
            ->createQuery('DELETE FROM ' . RefreshToken::class . ' r WHERE r.session = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->execute();
    }
}
