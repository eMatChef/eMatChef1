<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\RefreshToken;
use App\Service\Auth\CurrentAuthSession;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Gesdinet erzeugt Refresh-Tokens (Login und single_use-Rotation) ohne Sitzungsbezug;
 * hier bekommt das neue Token die in diesem Request ausgestellte Sitzung.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: RefreshToken::class)]
final class RefreshTokenSessionListener
{
    public function __construct(
        private readonly CurrentAuthSession $currentSession,
    ) {
    }

    public function prePersist(RefreshToken $refreshToken): void
    {
        if ($refreshToken->getSession() !== null) {
            return;
        }

        $session = $this->currentSession->getIssued();
        if ($session !== null) {
            $refreshToken->setSession($session);
        }
    }
}
