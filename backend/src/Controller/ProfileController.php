<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\VerificationEmailService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/profiles', name: 'api_profiles_')]
class ProfileController extends AbstractController
{
    public function __construct(
        private ProfileRepository $profileRepository,
        private UserRepository $userRepository,
        private VerificationEmailService $verificationEmailService,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * Lädt Profile-Daten
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProfileData(string $id): JsonResponse
    {
        $profile = $this->profileRepository->find($id);
        
        if (!$profile) {
            return new JsonResponse(['error' => 'Profile not found'], 404);
        }

        // Prüfe ob User auf eigenes Profile zugreift oder Admin ist
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        // User kann nur eigenes Profile sehen, außer er ist Admin
        if ($profile->getId() !== $currentUser->getProfileId() && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }
        $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);

        return new JsonResponse([
            'id' => $profile->getId(),
            'email' => $profile->getEmail(),
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName(),
            'first_name' => $profile->getFirstName(),
            'last_name' => $profile->getLastName(),
            'nickname' => $profile->getNickname(),
            'avatarInitials' => $profile->getAvatarInitials(),
            'avatar_initials' => $profile->getAvatarInitials(),
            'pendingEmail' => $user?->getPendingEmail(),
            'pending_email' => $user?->getPendingEmail(),
            'language' => $profile->getLanguage(),
            'roles' => $profile->getRoles(),
            'backgroundColor' => $profile->getBackgroundColor(),
            'textColor' => $profile->getTextColor(),
            'background_color' => $profile->getBackgroundColor(),
            'text_color' => $profile->getTextColor()
        ]);
    }

    /**
     * Aktualisiert eigenes Profil (Name, Spitzname, Sprache, Avatar-Einstellungen)
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateProfileData(string $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepository->find($id);
        if (!$profile) {
            return new JsonResponse(['error' => 'Profile not found'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        // User darf nur sein eigenes Profil ändern (oder als Admin)
        if ($profile->getId() !== $currentUser->getProfileId() && !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not found for profile'], 404);
        }

        $profileChanges = [];
        $emailChangeRequested = null;

        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            if ($email === '') {
                return new JsonResponse(['error' => 'E-Mail darf nicht leer sein'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['error' => 'Ungültige E-Mail-Adresse'], 400);
            }
            $currentEmail = strtolower($profile->getEmail());
            $requestedEmail = strtolower($email);

            if ($requestedEmail !== $currentEmail) {
                $existing = $this->profileRepository->findOneBy(['email' => $requestedEmail]);
                if ($existing && $existing->getId() !== $profile->getId()) {
                    return new JsonResponse(['error' => 'E-Mail ist bereits vergeben'], 409);
                }

                $pendingToken = bin2hex(random_bytes(32));
                $pendingExpiresAt = (new \DateTime())->modify('+10 days');
                $previousPendingEmail = $user->getPendingEmail();

                $user->setPendingEmail($requestedEmail);
                $user->setEmailVerificationToken($pendingToken);
                $user->setEmailVerificationExpiresAt($pendingExpiresAt);

                // Bestehende Login-Verifikation bleibt gültig, bis neue Adresse bestätigt ist.
                try {
                    $this->verificationEmailService->sendPendingEmailChangeVerification(
                        $user,
                        $requestedEmail,
                        $pendingToken,
                        $pendingExpiresAt
                    );
                } catch (\Throwable) {
                    $user->setPendingEmail(null);
                    $user->setEmailVerificationToken(null);
                    $user->setEmailVerificationExpiresAt(null);
                    return new JsonResponse([
                        'error' => 'Bestaetigungslink konnte nicht gesendet werden. Bitte E-Mail-Adresse pruefen.'
                    ], 400);
                }

                $emailChangeRequested = [
                    'email' => ['old' => $currentEmail, 'new' => $requestedEmail],
                    'pending_email' => ['old' => $previousPendingEmail, 'new' => $requestedEmail],
                ];
            }
        }

        if (array_key_exists('first_name', $data)) {
            $oldFirstName = $profile->getFirstName();
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $newFirstName = $firstName !== '' ? $firstName : null;
            $profile->setFirstName($newFirstName);
            if ($oldFirstName !== $newFirstName) {
                $profileChanges['first_name'] = ['old' => $oldFirstName, 'new' => $newFirstName];
            }
        }

        if (array_key_exists('last_name', $data)) {
            $oldLastName = $profile->getLastName();
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $newLastName = $lastName !== '' ? $lastName : null;
            $profile->setLastName($newLastName);
            if ($oldLastName !== $newLastName) {
                $profileChanges['last_name'] = ['old' => $oldLastName, 'new' => $newLastName];
            }
        }

        if (array_key_exists('nickname', $data)) {
            $oldNickname = $profile->getNickname();
            $nickname = trim((string) ($data['nickname'] ?? ''));
            $newNickname = $nickname !== '' ? $nickname : null;
            $profile->setNickname($newNickname);
            if ($oldNickname !== $newNickname) {
                $profileChanges['nickname'] = ['old' => $oldNickname, 'new' => $newNickname];
            }
        }

        if (array_key_exists('avatar_initials', $data)) {
            $oldAvatarInitials = $profile->getAvatarInitials();
            $avatarInitials = strtoupper(trim((string) ($data['avatar_initials'] ?? '')));
            if ($avatarInitials !== '' && mb_strlen($avatarInitials) > 2) {
                return new JsonResponse(['error' => 'Initialen dürfen maximal 2 Zeichen haben'], 400);
            }
            $newAvatarInitials = $avatarInitials !== '' ? $avatarInitials : null;
            $profile->setAvatarInitials($newAvatarInitials);
            if ($oldAvatarInitials !== $newAvatarInitials) {
                $profileChanges['avatar_initials'] = ['old' => $oldAvatarInitials, 'new' => $newAvatarInitials];
            }
        }

        if (array_key_exists('language', $data)) {
            $oldLanguage = $profile->getLanguage();
            $language = strtolower(trim((string) ($data['language'] ?? '')));
            $allowedLanguages = ['de', 'en', 'fr', 'it'];
            if (!in_array($language, $allowedLanguages, true)) {
                return new JsonResponse(['error' => 'Ungültige Sprache'], 400);
            }
            $profile->setLanguage($language);
            if ($oldLanguage !== $language) {
                $profileChanges['language'] = ['old' => $oldLanguage, 'new' => $language];
            }
        }

        if (array_key_exists('background_color', $data)) {
            $oldBackgroundColor = $profile->getBackgroundColor();
            $bg = strtoupper(trim((string) ($data['background_color'] ?? '')));
            if ($bg !== '' && !preg_match('/^#[0-9A-F]{6}$/', $bg)) {
                return new JsonResponse(['error' => 'Ungültige Hintergrundfarbe'], 400);
            }
            $newBackgroundColor = $bg !== '' ? $bg : null;
            $profile->setBackgroundColor($newBackgroundColor);
            if ($oldBackgroundColor !== $newBackgroundColor) {
                $profileChanges['background_color'] = ['old' => $oldBackgroundColor, 'new' => $newBackgroundColor];
            }
        }

        if (array_key_exists('text_color', $data)) {
            $oldTextColor = $profile->getTextColor();
            $text = strtoupper(trim((string) ($data['text_color'] ?? '')));
            if ($text !== '' && !preg_match('/^#[0-9A-F]{6}$/', $text)) {
                return new JsonResponse(['error' => 'Ungültige Schriftfarbe'], 400);
            }
            $newTextColor = $text !== '' ? $text : null;
            $profile->setTextColor($newTextColor);
            if ($oldTextColor !== $newTextColor) {
                $profileChanges['text_color'] = ['old' => $oldTextColor, 'new' => $newTextColor];
            }
        }

        if ($emailChangeRequested !== null || !empty($profileChanges)) {
            $profile->setUpdatedAt(new \DateTime());
        }

        if ($emailChangeRequested !== null) {
            $this->auditLogger->log(
                'profile',
                $profile->getId(),
                'profile_email_change_requested',
                $currentUser,
                $user,
                null,
                $emailChangeRequested
            );
        }

        if (!empty($profileChanges)) {
            $this->auditLogger->log(
                'profile',
                $profile->getId(),
                'profile_updated',
                $currentUser,
                $user,
                null,
                $profileChanges
            );
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'E-Mail ist bereits vergeben'], 409);
        }

        return $this->getProfileData($id);
    }

    /**
     * Aendert das Passwort des eigenen Kontos
     */
    #[Route('/{id}/password', name: 'change_password', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(string $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepository->find($id);
        if (!$profile) {
            return new JsonResponse(['error' => 'Profile not found'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        if ($profile->getId() !== $currentUser->getProfileId() && !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $currentPassword = (string) ($data['current_password'] ?? '');
        $newPassword = (string) ($data['new_password'] ?? '');
        $confirmNewPassword = (string) ($data['confirm_new_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmNewPassword === '') {
            return new JsonResponse(['error' => 'Aktuelles Passwort, neues Passwort und Passwort-Bestaetigung sind erforderlich'], 400);
        }

        if ($newPassword !== $confirmNewPassword) {
            return new JsonResponse(['error' => 'Neues Passwort und Passwort-Bestaetigung stimmen nicht ueberein'], 400);
        }

        if (strlen($newPassword) < 8) {
            return new JsonResponse(['error' => 'Das Passwort muss mindestens 8 Zeichen lang sein'], 400);
        }

        $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not found for profile'], 404);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return new JsonResponse(['error' => 'Aktuelles Passwort ist falsch'], 400);
        }

        if ($this->passwordHasher->isPasswordValid($user, $newPassword)) {
            return new JsonResponse(['error' => 'Neues Passwort darf nicht dem aktuellen Passwort entsprechen'], 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $profile->setUpdatedAt(new \DateTime());

        $this->auditLogger->log(
            'user',
            $user->getId(),
            'user_password_changed',
            $currentUser,
            $user,
            null,
            [
                'source' => ['old' => null, 'new' => 'profile_change'],
            ]
        );
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Passwort wurde erfolgreich geaendert.'
        ]);
    }

}
