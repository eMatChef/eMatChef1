<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\Repository\UserSessionRepository;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\LegacySessionCutoff;
use App\Service\Auth\UserSessionManager;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Bindet jedes JWT an eine UserSession (Claim `sid`) und prüft die Sitzung bei jedem JWT-Request.
 *
 * Ausstellung (JWT_CREATED) — einziger Erzeugungspunkt für alle Login-Wege:
 * - Refresh: Sitzung des Refresh-Tokens (von RefreshTokenSessionCheckListener gesetzt), keine neue Sitzung;
 * - Passwort-Login (Firewall `login`), Google- und MiData-Callback: neue Sitzung;
 * - alles andere: kein JWT ohne bekannte Sitzung (LogicException).
 *
 * Prüfung (JWT_DECODED, vor dem Laden des Users und vor dem UserChecker): Sitzung muss existieren,
 * dem User des Tokens gehören und darf nicht widerrufen sein. JWTs ohne `sid` nur befristet (LegacySessionCutoff).
 */
final class JwtSessionSubscriber implements EventSubscriberInterface
{
    public const CLAIM = 'sid';

    private const USER_ID_CLAIM = 'username';

    /** Login-Routen ohne Security-Token (OAuth-Firewalls mit security: false). */
    private const OAUTH_LOGIN_ROUTES = [
        'api_auth_google_callback' => AuthMethod::GOOGLE,
        'api_auth_midata_callback' => AuthMethod::MIDATA,
    ];

    private const PASSWORD_LOGIN_FIREWALL = 'login';

    public function __construct(
        private readonly UserSessionManager $sessionManager,
        private readonly UserSessionRepository $sessionRepository,
        private readonly CurrentAuthSession $currentSession,
        private readonly LegacySessionCutoff $legacyCutoff,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJwtCreated',
            Events::JWT_DECODED => 'onJwtDecoded',
            Events::JWT_AUTHENTICATED => 'onJwtAuthenticated',
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('JWT can only be issued for App\Entity\User.');
        }

        $session = $this->currentSession->getIssued() ?? $this->startLoginSession($user);
        if ($session->getUser()->getId() !== $user->getId()) {
            throw new \LogicException('Issued session does not belong to the JWT user.');
        }

        $payload = $event->getData();
        $payload[self::CLAIM] = $session->getId();
        $event->setData($payload);
    }

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $sid = $payload[self::CLAIM] ?? null;

        if ($sid === null) {
            if (!$this->legacyCutoff->allowsJwtWithoutSid($payload)) {
                $event->markAsInvalid();
            }

            return;
        }

        $session = \is_string($sid) ? $this->sessionRepository->findOneById($sid) : null;
        if (
            !$session instanceof UserSession
            || $session->isRevoked()
            || $session->getUser()->getUserIdentifier() !== (string) ($payload[self::USER_ID_CLAIM] ?? '')
        ) {
            $event->markAsInvalid();

            return;
        }

        $this->currentSession->setAuthenticated($session);
    }

    public function onJwtAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $session = $this->currentSession->getAuthenticated();
        if ($session !== null) {
            $this->sessionManager->touchLastSeen($session);
        }
    }

    private function startLoginSession(User $user): UserSession
    {
        $token = $this->tokenStorage->getToken();
        if ($token instanceof PostRefreshTokenAuthenticationToken) {
            throw new \LogicException('Refresh without resolved session.');
        }

        $authMethod = $this->resolveLoginMethod($token);
        if ($authMethod === null) {
            throw new \LogicException('JWT issued outside a known login flow.');
        }

        // Login-MFA entfiel dank Trusted Device (nur dieses Users): Sitzung entsprechend kennzeichnen.
        $trusted = $this->currentSession->getPendingTrustedDevice();
        $trustedForUser = $trusted !== null && $trusted->getUser()->getId() === $user->getId();

        $session = $this->sessionManager->startSession($user, $authMethod, $trustedForUser ? 'trusted_device' : null);
        $this->currentSession->setIssued($session);

        if ($trusted !== null && $trustedForUser) {
            $this->sessionManager->markTrustedDeviceLogin($session, $trusted);
        }
        $this->currentSession->setPendingTrustedDevice(null);

        return $session;
    }

    private function resolveLoginMethod(mixed $token): ?AuthMethod
    {
        if (
            ($token instanceof UsernamePasswordToken || $token instanceof PostAuthenticationToken)
            && $token->getFirewallName() === self::PASSWORD_LOGIN_FIREWALL
        ) {
            return AuthMethod::PASSWORD;
        }
        if ($token !== null) {
            return null;
        }

        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        return \is_string($route) ? (self::OAUTH_LOGIN_ROUTES[$route] ?? null) : null;
    }
}
