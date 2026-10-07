<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\UserSessionManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Refresh-Firewall: das vorgelegte Refresh-Token muss zu einer gültigen Sitzung desselben Users gehören.
 * Die Sitzung wird für JWT_CREATED und das rotierte Refresh-Token übernommen (gleiche `sid`).
 * Fehler hier führen über den AuthenticatorManager zu 401.
 */
#[AsEventListener(event: CheckPassportEvent::class, dispatcher: 'security.event_dispatcher.refresh')]
final class RefreshTokenSessionCheckListener
{
    public function __construct(
        private readonly UserSessionManager $sessionManager,
        private readonly CurrentAuthSession $currentSession,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $refreshToken = $passport->getAttribute('refreshToken');
        if (!$refreshToken instanceof RefreshToken) {
            return;
        }

        $user = $passport->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->currentSession->setIssued($this->sessionManager->resolveForRefresh($refreshToken, $user));
    }
}
