<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Löst die eingeloggte Sitzung für OAuth-Callbacks auf. Die OAuth-Firewalls laufen mit `security: false`
 * (normaler Login hat noch keinen Benutzer), dort gibt es deshalb keinen Security-Token. Für den Link-Flow
 * wird das BEARER-Cookie hier explizit geprüft: Signatur, Ablauf, `sid`, nicht widerrufene Sitzung, passender
 * User, aktives Konto. Ein abgelaufenes oder ungültiges JWT ergibt null (kein 401, nie eine Zuordnung).
 */
class OAuthCallbackSessionResolver
{
    public const COOKIE_NAME = 'BEARER';

    public function __construct(
        private readonly JWTEncoderInterface $encoder,
        private readonly UserSessionRepository $sessions,
    ) {}

    public function resolve(Request $request): ?UserSession
    {
        $token = (string) $request->cookies->get(self::COOKIE_NAME, '');
        if ($token === '') {
            return null;
        }
        try {
            $payload = $this->encoder->decode($token);
        } catch (\Throwable) {
            return null; // abgelaufen, manipuliert oder unlesbar
        }
        if (!\is_array($payload)) {
            return null;
        }

        $sid = $payload[JwtSessionClaims::SESSION] ?? null;
        $identifier = $payload[JwtSessionClaims::USER] ?? null;
        if (!\is_string($sid) || $sid === '' || !\is_string($identifier) || $identifier === '') {
            return null;
        }
        $session = $this->sessions->findOneById($sid);
        if (
            !$session instanceof UserSession
            || $session->isRevoked()
            || $session->getUser()->getUserIdentifier() !== $identifier
            || $session->getUser()->getState() !== 'active'
        ) {
            return null;
        }

        return $session;
    }
}
