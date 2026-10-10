<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Auth\AdminMfaGuard;
use App\Service\Auth\AdminMfaPolicy;
use App\Service\Auth\CurrentAuthSession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Zentrale Durchsetzung der Admin-MFA-Regeln (AdminMfaPolicy/AdminMfaGuard) für alle API-Requests,
 * nach der Authentifizierung und vor dem Controller. Controller enthalten dafür keine eigenen Prüfungen.
 */
final class AdminMfaGuardSubscriber implements EventSubscriberInterface
{
    private const MESSAGES = [
        AdminMfaGuard::MFA_SETUP_REQUIRED => 'Für administrative Funktionen musst du zuerst die Zwei-Faktor-Authentifizierung einrichten.',
        AdminMfaGuard::MFA_REQUIRED => 'Bestätige diese Sitzung mit der Zwei-Faktor-Authentifizierung.',
        AdminMfaGuard::STEP_UP_REQUIRED => 'Für diese Aktion ist eine erneute Sicherheitsbestätigung nötig.',
        AdminMfaGuard::REAUTH_REQUIRED => 'Re-authentication required for this action.',
    ];

    public function __construct(
        private readonly AdminMfaPolicy $policy,
        private readonly AdminMfaGuard $guard,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly CurrentAuthSession $currentSession,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Nach dem Firewall-Listener (Priorität 8), damit Token und Sitzung (JWT_DECODED) bekannt sind.
        return [KernelEvents::REQUEST => ['onRequest', 4]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $level = $this->policy->levelFor($request);
        if ($level === null) {
            return;
        }
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return; // nicht angemeldet: bestehende Zugriffskontrolle (401)
        }

        $reason = $this->guard->denialReason($user, $this->currentSession->getAuthenticated(), $level);
        if ($reason === null) {
            return;
        }

        $response = new JsonResponse(['error' => $reason, 'message' => self::MESSAGES[$reason]], 403);
        $response->headers->set('Cache-Control', 'no-store');
        $event->setResponse($response);
    }
}
