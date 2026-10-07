<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\UserChecker;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\MfaChallengeService;
use App\Service\Auth\MfaException;
use App\Service\Auth\TrustedDeviceService;
use App\Service\Auth\UserSessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Zweiter Login-Schritt: Challenge + TOTP- oder Recovery-Code. Erst hier entstehen Sitzung und Tokens.
 */
#[Route('/api/auth/mfa', name: 'api_auth_mfa_')]
final class MfaController extends AbstractController
{
    public function __construct(
        private readonly MfaChallengeService $mfaChallenges,
        private readonly UserSessionManager $sessionManager,
        private readonly CurrentAuthSession $currentSession,
        private readonly EntityManagerInterface $entityManager,
        private readonly TrustedDeviceService $trustedDevices,
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
    ) {
    }

    /** Body: {"challenge": "...", "method": "totp"|"recovery_code", "code": "...", "trust_device": bool (optional)} */
    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $data = \is_array($data) ? $data : [];
        $challenge = \is_string($data['challenge'] ?? null) ? $data['challenge'] : '';
        $method = \is_string($data['method'] ?? null) ? $data['method'] : '';
        $code = \is_string($data['code'] ?? null) ? $data['code'] : '';
        $trustDevice = ($data['trust_device'] ?? false) === true;

        try {
            $verified = $this->mfaChallenges->verify($challenge, $method, $code);
            $user = $verified->getUser();
            if ($user->getState() !== UserChecker::ACTIVE_STATE) {
                throw new MfaException(MfaException::INACTIVE, 'Dieses Konto ist nicht aktiv.');
            }
        } catch (MfaException $e) {
            return $this->noStore(new JsonResponse(['error' => $e->getMessage(), 'code' => $e->reason], match ($e->reason) {
                MfaException::LOCKED => 429,
                MfaException::INACTIVE => 403,
                MfaException::INVALID_CHALLENGE => 401,
                default => 400,
            }));
        }

        // Neue Sitzung für diesen Login; die MFA-Bestätigung gilt nur für sie (nie aus einer alten Sitzung übernommen).
        $session = $this->sessionManager->startSession($user, $verified->getAuthMethod());
        $session->markMfaVerified($method);
        $trustCookie = null;
        if ($trustDevice) {
            // Nur nach tatsächlich bestandener MFA (TOTP oder Recovery Code) und ausdrücklicher Wahl des Users.
            ['device' => $device, 'cookie' => $trustCookie] = $this->trustedDevices->grant($user, $request);
            $session->setTrustedDevice($device);
        }
        $this->entityManager->flush();
        $this->currentSession->setIssued($session);

        $response = $this->noStore($this->authenticationSuccessHandler->handleAuthenticationSuccess($user));
        if ($trustCookie !== null) {
            $response->headers->setCookie($trustCookie);
        }

        return $response;
    }

    private function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
