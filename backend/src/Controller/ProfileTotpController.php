<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Auth\TotpException;
use App\Service\Auth\TotpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Zwei-Faktor-Authentifizierung (TOTP + Recovery Codes) des eigenen Kontos.
 * Secret und Recovery Codes stehen nur in den Antworten von enroll bzw. confirm/regenerate, nie im Status.
 */
#[Route('/api/profiles/{id}/security/totp', name: 'api_profile_totp_')]
#[IsGranted('ROLE_USER')]
final class ProfileTotpController extends AbstractController
{
    public function __construct(
        private readonly TotpService $totp,
    ) {
    }

    #[Route('', name: 'status', methods: ['GET'])]
    public function status(string $id): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->noStore(new JsonResponse($this->totp->status($user)));
    }

    /** Body (nur bei bereits aktivem TOTP): {"code": "<aktueller TOTP- oder Recovery Code>"} */
    #[Route('/enroll', name: 'enroll', methods: ['POST'])]
    public function enroll(string $id, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $setup = $this->totp->startEnrollment($user, $this->code($request));
        } catch (TotpException $e) {
            return $this->error($e);
        }

        return $this->noStore(new JsonResponse([
            'secret' => $setup['secret'],
            'otpauth_uri' => $setup['otpauth_uri'],
            'status' => $this->totp->status($user),
        ], 201));
    }

    #[Route('/enroll/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(string $id, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $codes = $this->totp->confirmEnrollment($user, (string) $this->code($request));
        } catch (TotpException $e) {
            return $this->error($e);
        }

        return $this->noStore(new JsonResponse(['recovery_codes' => $codes, 'status' => $this->totp->status($user)]));
    }

    /** Body: {"code": "..."} */
    #[Route('/recovery-codes/regenerate', name: 'regenerate', methods: ['POST'])]
    public function regenerate(string $id, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $codes = $this->totp->regenerateRecoveryCodes($user, $this->code($request));
        } catch (TotpException $e) {
            return $this->error($e);
        }

        return $this->noStore(new JsonResponse(['recovery_codes' => $codes, 'status' => $this->totp->status($user)]));
    }

    /** Body: {"code": "..."} */
    #[Route('/disable', name: 'disable', methods: ['POST'])]
    public function disable(string $id, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $this->totp->disable($user, $this->code($request));
        } catch (TotpException $e) {
            return $this->error($e);
        }

        return $this->noStore(new JsonResponse(['status' => $this->totp->status($user)]));
    }

    private function code(Request $request): ?string
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) && isset($data['code']) && \is_string($data['code']) ? $data['code'] : null;
    }

    private function error(TotpException $e): JsonResponse
    {
        $status = match ($e->reason) {
            TotpException::LOCKED => 429,
            TotpException::REQUIRED_FOR_ADMIN => 403,
            TotpException::NOT_ACTIVE, TotpException::NO_PENDING => 409,
            default => 400,
        };

        return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->reason], $status);
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** Nur das eigene Konto (keine Admin-Ausnahme). */
    private function requireOwnUser(string $profileId): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        if ($user->getProfileId() !== $profileId) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        return $user;
    }
}
