<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Config\LanguageConfig;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Util\IdGenerator;
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
    ) {}

    public function resolveOrCreate(GoogleOAuthUserInfo $info): User
    {
        $user = $this->userRepository->findOneBy(['googleId' => $info->googleId]);
        if ($user instanceof User) {
            $this->assertActive($user);
            $this->ensureVerified($user);
            $this->entityManager->flush();

            return $user;
        }

        $profile = $this->profileRepository->findOneBy(['email' => $info->email]);
        if ($profile instanceof Profile) {
            $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
            if (!$user instanceof User) {
                throw new GoogleOAuthException('failed', 'Profile without user');
            }
            $this->assertActive($user);
            if ($user->getGoogleId() !== null && $user->getGoogleId() !== $info->googleId) {
                throw new GoogleOAuthException('failed', 'Email already linked to another Google account');
            }
            $user->setGoogleId($info->googleId);
            $this->ensureVerified($user);
            $this->entityManager->flush();

            return $user;
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
        $this->entityManager->flush();

        return $user;
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
