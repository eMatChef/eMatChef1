<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Config\LanguageConfig;
use App\Entity\ExternalIdentity;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ProfileRepository;
use App\Service\AuditLogger;
use App\Util\IdGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MiDataOAuthAccountService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalIdentityRepository $externalIdentityRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LanguageConfig $languageConfig,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function resolveOrCreate(MiDataOAuthUserInfo $info, ?User $linkToUser = null): User
    {
        if ($info->subject === '') {
            throw new MiDataOAuthException('failed', 'MiData userinfo is missing sub');
        }

        $identity = $this->externalIdentityRepository->findOneByProviderAndExternalUserId('midata', $info->subject);
        if ($identity instanceof ExternalIdentity) {
            $identityUser = $identity->getUser();
            if ($linkToUser instanceof User && $identityUser->getId() !== $linkToUser->getId()) {
                throw new MiDataOAuthException('link_conflict', 'MiData identity is already linked to another account');
            }
            $this->assertActive($identityUser);
            $identity->setEmail($info->email);
            $identity->setUpdatedAt(new \DateTime());
            $this->flushIdentity($identityUser, $info->subject);

            return $identityUser;
        }

        if ($linkToUser instanceof User) {
            $this->assertActive($linkToUser);
            $this->createIdentity($linkToUser, $info);
            $this->auditLogger->log(
                'user',
                $linkToUser->getId(),
                'external_identity_linked',
                null,
                $linkToUser,
                null,
                [
                    'provider' => ['old' => null, 'new' => 'midata'],
                    'external_user_id' => ['old' => null, 'new' => $info->subject],
                ]
            );
            $this->flushIdentity($linkToUser, $info->subject);

            return $linkToUser;
        }

        if ($info->email === null) {
            throw new MiDataOAuthException('no_email', 'MiData account has no usable email address');
        }

        $existingProfile = $this->profileRepository->findOneBy(['email' => $info->email]);
        if ($existingProfile instanceof Profile) {
            $this->auditLogger->log(
                'user',
                '',
                'external_identity_email_conflict',
                null,
                null,
                null,
                [
                    'provider' => ['old' => null, 'new' => 'midata'],
                    'external_user_id' => ['old' => null, 'new' => $info->subject],
                    'email' => ['old' => null, 'new' => $info->email],
                ]
            );
            throw new MiDataOAuthException('email_conflict', 'Email already belongs to an eMatChef account');
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
        $user->setState('active');
        $user->setEmailVerified($info->emailVerified);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationExpiresAt(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $this->createIdentity($user, $info);

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
                'source' => ['old' => null, 'new' => 'midata_oauth'],
                'profile_id' => ['old' => null, 'new' => $profile->getId()],
                'email' => ['old' => null, 'new' => $profile->getEmail()],
                'email_verified' => ['old' => null, 'new' => $info->emailVerified],
            ]
        );
        $this->flushIdentity($user, $info->subject);

        return $user;
    }

    private function createIdentity(User $user, MiDataOAuthUserInfo $info): void
    {
        $identity = new ExternalIdentity();
        $identity->setId(IdGenerator::generateUnique($this->entityManager, ExternalIdentity::class));
        $identity->setUser($user);
        $identity->setProvider('midata');
        $identity->setExternalUserId($info->subject);
        $identity->setEmail($info->email);
        $user->addExternalIdentity($identity);
        $this->entityManager->persist($identity);
    }

    private function flushIdentity(User $user, string $subject): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $this->auditLogger->log(
                'user',
                $user->getId(),
                'external_identity_midata_conflict',
                null,
                $user,
                null,
                [
                    'provider' => ['old' => null, 'new' => 'midata'],
                    'external_user_id' => ['old' => null, 'new' => $subject],
                ]
            );
            throw new MiDataOAuthException('link_conflict', 'MiData identity is already linked to another account', $exception);
        }
    }

    private function assertActive(User $user): void
    {
        if ($user->getState() !== 'active') {
            throw new MiDataOAuthException('inactive', 'User is not active');
        }
    }
}
