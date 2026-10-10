<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Entity\UserEmailAlias;
use App\Service\MembershipNotificationEmailResolver;
use App\Service\UserEmailAliasConflictException;
use App\Service\UserEmailAliasService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * E-Mail-Adressen des eigenen Kontos: Primary (Profile.email) und zusätzliche Adressen.
 * Nur das eigene Profil; Bestätigung läuft über GET /api/auth/verify.
 */
#[Route('/api/profiles/{id}/emails', name: 'api_profile_emails_')]
#[IsGranted('ROLE_USER')]
final class ProfileEmailController extends AbstractController
{
    public function __construct(
        private readonly UserEmailAliasService $emailAliases,
        private readonly MembershipNotificationEmailResolver $notificationEmails,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->listResponse($user);
    }

    /**
     * Benachrichtigungsadresse je eigener Mitgliedschaft (Department/Grossanlass). Nur Memberships des eigenen Users;
     * ändern läuft über PUT /api/departments/{id}/my-notification-email (kein zweiter Speicherweg).
     */
    #[Route('/department-assignments', name: 'department_assignments', methods: ['GET'])]
    public function departmentAssignments(string $id): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $primary = strtolower(trim((string) ($user->getProfile()?->getEmail() ?? '')));
        $assignments = [];
        foreach ($user->getMemberships() as $membership) {
            if (!$membership instanceof Membership) {
                continue;
            }
            $department = $membership->getDepartment();
            $effective = $this->notificationEmails->effectiveEmail($membership);
            $assignments[] = [
                'department_id' => $department->getId(),
                'name' => $department->getName(),
                'parent_name' => $department->getParent()?->getName(),
                'is_grossanlass' => $department->isGrossanlass(),
                'effective_email' => $effective,
                // null = Standard (Hauptadresse), auch wenn eine gespeicherte Adresse nicht mehr gültig ist
                'selected_email' => $effective !== $primary ? $effective : null,
            ];
        }
        usort($assignments, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return new JsonResponse([
            'assignments' => $assignments,
            'options' => $this->notificationEmails->selectableEmails($user),
        ]);
    }

    #[Route('', name: 'add', methods: ['POST'])]
    public function add(string $id, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = json_decode($request->getContent(), true);
        $email = \is_array($data) ? (string) ($data['email'] ?? '') : '';

        try {
            $this->emailAliases->addEmail($user, $email);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (UserEmailAliasConflictException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\Throwable) {
            // Adresse ist angelegt, nur der Versand schlug fehl: erneut senden ist möglich.
            return new JsonResponse(['error' => 'Bestätigungslink konnte nicht gesendet werden. Bitte später erneut senden.'], 502);
        }

        return $this->listResponse($user, 201);
    }

    #[Route('/{aliasId}/resend', name: 'resend', methods: ['POST'])]
    public function resend(string $id, string $aliasId): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $alias = $this->emailAliases->findForUser($user, $aliasId);
        if (!$alias instanceof UserEmailAlias) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }

        try {
            $this->emailAliases->resendVerification($user, $alias);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 429);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Bestätigungslink konnte nicht gesendet werden.'], 502);
        }

        return $this->listResponse($user);
    }

    #[Route('/primary', name: 'primary', methods: ['PUT'])]
    public function makePrimary(string $id, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $data = json_decode($request->getContent(), true);
        $aliasId = \is_array($data) ? (string) ($data['alias_id'] ?? '') : '';
        $alias = $aliasId !== '' ? $this->emailAliases->findForUser($user, $aliasId) : null;
        if (!$alias instanceof UserEmailAlias) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }

        try {
            $this->emailAliases->makePrimary($user, $alias);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        }

        return $this->listResponse($user);
    }

    #[Route('/{aliasId}', name: 'remove', methods: ['DELETE'])]
    public function remove(string $id, string $aliasId): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $alias = $this->emailAliases->findForUser($user, $aliasId);
        if (!$alias instanceof UserEmailAlias) {
            return new JsonResponse(['error' => 'Adresse nicht gefunden'], 404);
        }

        $this->emailAliases->removeEmail($user, $alias);

        return $this->listResponse($user);
    }

    private function listResponse(User $user, int $status = 200): JsonResponse
    {
        $emails = array_map(static fn (UserEmailAlias $alias): array => [
            'id' => $alias->getId(),
            'email' => $alias->getEmail(),
            'verified' => $alias->isVerified(),
            'verified_at' => $alias->getVerifiedAt()?->format(\DateTimeInterface::ATOM),
            'login_enabled' => $alias->isLoginEnabled(),
            'verification_expires_at' => $alias->isVerified()
                ? null
                : $alias->getVerificationExpiresAt()?->format(\DateTimeInterface::ATOM),
        ], $this->emailAliases->listForUser($user));

        return new JsonResponse([
            'primary' => [
                'email' => $user->getProfile()?->getEmail(),
                'verified' => $user->isEmailVerified(),
            ],
            'emails' => $emails,
        ], $status);
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
