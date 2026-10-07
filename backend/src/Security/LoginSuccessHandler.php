<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\AuthMethod;
use App\Service\Auth\MfaChallengeService;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Passwort-Login: Hat der User aktives TOTP, gibt es statt Sitzung/Tokens eine MFA-Challenge.
 * Ohne TOTP läuft der bisherige Lexik-Handler unverändert.
 */
final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandler $inner,
        private readonly MfaChallengeService $mfaChallenges,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if ($user instanceof User) {
            $challenge = $this->mfaChallenges->issueIfRequired($user, AuthMethod::PASSWORD, false, $request);
            if ($challenge !== null) {
                $response = new JsonResponse($challenge);
                $response->headers->set('Cache-Control', 'no-store');

                return $response;
            }
        }

        return $this->inner->onAuthenticationSuccess($request, $token);
    }
}
