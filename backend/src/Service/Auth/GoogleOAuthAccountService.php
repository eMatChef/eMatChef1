<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Config\LanguageConfig;
use App\Entity\ExternalIdentity;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\UserEmailAliasService;
use App\Util\IdGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GoogleOAuthAccountService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LanguageConfig $languageConfig,
        private readonly AuditLogger $auditLogger,
        private readonly UserEmailAliasService $emailAliases,
        private readonly ExternalIdentityRepository $externalIdentityRepository,
    ) {}

    public function resolveOrCreate(GoogleOAuthUserInfo $info): User
    {
        $provider = 'google';
        $identity = $this->externalIdentityRepository->findOneByProviderAndExternalUserId($provider, $info->googleId);
        if ($identity instanceof ExternalIdentity) {
            $user = $identity->getUser();
            $legacyUser = $this->userRepository->findOneBy(['googleId' => $info->googleId]);
            if ($legacyUser instanceof User && $legacyUser->getId() !== $user->getId()) {
                $this->auditLogger->log(
                    'user',
                    $user->getId(),
                    'external_identity_google_id_conflict',
                    null,
                    $user,
                    null,
                    [
                        'provider' => ['old' => null, 'new' => $provider],
                        'external_user_id' => ['old' => null, 'new' => $info->googleId],
                        'legacy_user_id' => ['old' => null, 'new' => $legacyUser->getId()],
                    ]
                );
                throw new GoogleOAuthException('failed', 'Google account already linked to another user');
            }

            $this->assertActive($user);
            $this->ensureExternalIdentity($user, $provider, $info->googleId, $info->email);
            $this->ensureVerified($user);
            $this->flushGoogleIdentity($provider, $info->googleId);

            return $user;
        }

        $user = $this->userRepository->findOneBy(['googleId' => $info->googleId]);
        if ($user instanceof User) {
            $this->assertActive($user);
            $this->ensureExternalIdentity($user, $provider, $info->googleId, $info->email);
            $this->ensureVerified($user);
            $this->flushGoogleIdentity($provider, $info->googleId);

            return $user;
        }

        $profile = $this->profileRepository->findOneBy(['email' => $info->email]);
        if ($profile instanceof Profile) {
            $existingUser = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
            if ($existingUser instanceof User) {
                $this->auditLogger->log(
                    'user',
                    $existingUser->getId(),
                    'external_identity_email_conflict',
                    null,
                    $existingUser,
                    null,
                    [
                        'provider' => ['old' => null, 'new' => $provider],
                        'external_user_id' => ['old' => null, 'new' => $info->googleId],
                        'email' => ['old' => null, 'new' => $info->email],
                    ]
                );
                throw new GoogleOAuthException('failed', 'Email already linked to another account');
            }

            throw new GoogleOAuthException('failed', 'Profile without user');
        }
        // Neuanlage nur, wenn die Adresse keinem Konto gehört (auch nicht als zusätzliche Adresse); kein Merge.
        if ($this->emailAliases->isEmailTaken($info->email)) {
            $this->auditLogger->log(
                'user',
                '',
                'external_identity_email_conflict',
                null,
                null,
                null,
                [
                    'provider' => ['old' => null, 'new' => $provider],
                    'external_user_id' => ['old' => null, 'new' => $info->googleId],
                    'email' => ['old' => null, 'new' => $info->email],
                ]
            );
            throw new GoogleOAuthException('failed', 'Email already linked to another account');
        }

        $profile = new Profile();
        $profile->setId(IdGenerator::generateUnique($this->entityManager, Profile::class));
        $profile->setEmail($info->email);
        $profile->setFirstName($info->firstName);
        $profile->setLastName($info->lastName);
        $profile->setLanguage($this->languageConfig->getDefaultLanguage());
        $profile->setRoles(['ROLE_USER']);

        $user = new User();
        $user->setId(IdGenerator::generateUnique($this->entityManager, User::class));
        $user->setProfileId($profile->getId());
        $user->setProfile($profile);
        $user->setGoogleId($info->googleId);
        $user->setState('active');
        $user->setEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationExpiresAt(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->ensureExternalIdentity($user, $provider, $info->googleId, $info->email);

        $this->entityManager->persist($profile);
        $this->entityManager->persist($user);
        $this->auditLogger->log(
            'user',
            $user->getId(),
            'user_created_self',
            null,
            $user,
            null,
            [
                'source' => ['old' => null, 'new' => 'google_oauth'],
                'profile_id' => ['old' => null, 'new' => $profile->getId()],
                'email' => ['old' => null, 'new' => $profile->getEmail()],
                'email_verified' => ['old' => null, 'new' => true],
            ]
        );
        $this->flushGoogleIdentity($provider, $info->googleId);

        return $user;
    }

    private function flushGoogleIdentity(string $provider, string $externalUserId): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $this->auditLogger->log(
                'user',
                '',
                'external_identity_google_id_conflict',
                null,
                null,
                null,
                [
                    'provider' => ['old' => null, 'new' => $provider],
                    'external_user_id' => ['old' => null, 'new' => $externalUserId],
                    'message' => ['old' => null, 'new' => 'unique constraint race condition during Google login'],
                ]
            );
            throw new GoogleOAuthException('failed', 'Google account already linked to another user');
        }
    }

    private function ensureExternalIdentity(User $user, string $provider, string $externalUserId, ?string $email): void
    {
        $identity = $this->externalIdentityRepository->findOneByProviderAndExternalUserId($provider, $externalUserId);
        if ($identity instanceof ExternalIdentity) {
            if ($identity->getUser()->getId() !== $user->getId()) {
                $this->auditLogger->log(
                    'user',
                    $identity->getUser()->getId(),
                    'external_identity_google_id_conflict',
                    null,
                    $identity->getUser(),
                    null,
                    [
                        'provider' => ['old' => $provider, 'new' => $provider],
                        'external_user_id' => ['old' => $externalUserId, 'new' => $externalUserId],
                        'conflicting_user_id' => ['old' => null, 'new' => $user->getId()],
                    ]
                );
                throw new GoogleOAuthException('failed', 'Google account already linked to another user');
            }

            $identity->setEmail($email);
            $identity->setUpdatedAt(new \DateTime());

            return;
        }

        if ($user->getGoogleId() !== null && $user->getGoogleId() !== $externalUserId) {
            throw new GoogleOAuthException('failed', 'Google account already linked to another user');
        }

        $user->setGoogleId($externalUserId);
        $newIdentity = new ExternalIdentity();
        $newIdentity->setId(IdGenerator::generateUnique($this->entityManager, ExternalIdentity::class));
        $newIdentity->setUser($user);
        $newIdentity->setProvider($provider);
        $newIdentity->setExternalUserId($externalUserId);
        $newIdentity->setEmail($email);
        $user->addExternalIdentity($newIdentity);
        $this->entityManager->persist($newIdentity);
    }

    private function ensureVerified(User $user): void
    {
        if ($user->isEmailVerified()) {
            return;
        }
        $user->setEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationExpiresAt(null);
        $user->setUpdatedAt(new \DateTime());
    }

    private function assertActive(User $user): void
    {
        if ($user->getState() !== 'active') {
            throw new GoogleOAuthException('inactive', 'User is not active');
        }
    }
}
