<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Config\LanguageConfig;
use App\Entity\ExternalIdentity;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\Auth\GoogleOAuthAccountService;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Auth\GoogleOAuthUserInfo;
use App\Service\UserEmailAliasService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GoogleOAuthAccountServiceTest extends TestCase
{
    public function testExternalIdentityCanBeLinkedToUser(): void
    {
        $user = new User();
        $user->setId('123456789abc');
        $user->setPassword('hashed');

        $identity = new ExternalIdentity();
        $identity->setId('aaaaaaaaaaaa');
        $identity->setUser($user);
        $identity->setProvider('google');
        $identity->setExternalUserId('google-sub-123');
        $identity->setEmail('person@example.com');

        $user->addExternalIdentity($identity);

        self::assertCount(1, $user->getExternalIdentities());
        self::assertSame($user, $identity->getUser());
        self::assertSame('google', $identity->getProvider());
    }

    public function testDifferentProvidersCanShareTheSameExternalUserId(): void
    {
        $user = new User();
        $user->setId('shared-user');
        $user->setPassword('hashed');

        $googleIdentity = new ExternalIdentity();
        $googleIdentity->setId('g-1');
        $googleIdentity->setUser($user);
        $googleIdentity->setProvider('google');
        $googleIdentity->setExternalUserId('same-sub');

        $microsoftIdentity = new ExternalIdentity();
        $microsoftIdentity->setId('m-1');
        $microsoftIdentity->setUser($user);
        $microsoftIdentity->setProvider('microsoft');
        $microsoftIdentity->setExternalUserId('same-sub');

        $user->addExternalIdentity($googleIdentity);
        $user->addExternalIdentity($microsoftIdentity);

        self::assertCount(2, $user->getExternalIdentities());
        self::assertSame('google', $googleIdentity->getProvider());
        self::assertSame('microsoft', $microsoftIdentity->getProvider());
        self::assertSame('same-sub', $googleIdentity->getExternalUserId());
        self::assertSame('same-sub', $microsoftIdentity->getExternalUserId());
    }

    public function testLegacyGoogleUserCreatesExternalIdentityAndKeepsGoogleId(): void
    {
        $user = new User();
        $user->setId('abcdef123456');
        $user->setPassword('hashed');
        $user->setGoogleId('legacy-google-id');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturnCallback(function (array $criteria, ?array $orderBy = null) use ($user): ?User {
            if (($criteria['googleId'] ?? null) === 'legacy-google-id') {
                return $user;
            }

            return null;
        });

        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->method('findOneBy')->willReturn(null);

        $externalIdentityRepository = $this->createMock(ExternalIdentityRepository::class);
        $externalIdentityRepository->method('findOneByProviderAndExternalUserId')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(fn (): object => new class {
            public function findOneBy(array $criteria, ?array $orderBy = null): ?object
            {
                return null;
            }
        });
        $entityManager->expects(self::atLeastOnce())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service = new GoogleOAuthAccountService(
            $entityManager,
            $userRepository,
            $profileRepository,
            $this->createMock(UserPasswordHasherInterface::class),
            new LanguageConfig(['de', 'en'], 'de'),
            $this->createMock(AuditLogger::class),
            $this->createMock(UserEmailAliasService::class),
            $externalIdentityRepository,
        );

        $resolved = $service->resolveOrCreate(new GoogleOAuthUserInfo('legacy-google-id', 'user@example.com', true, 'Ada', 'Lovelace'));

        self::assertInstanceOf(User::class, $resolved);
        self::assertSame('legacy-google-id', $resolved->getGoogleId());
        self::assertCount(1, $resolved->getExternalIdentities());
    }

    public function testExistingExternalIdentityTakesPrecedenceOverLegacyGoogleId(): void
    {
        $user = new User();
        $user->setId('existing-user');
        $user->setPassword('hashed');
        $user->setGoogleId('legacy-google-id');
        $user->setState('active');

        $identity = new ExternalIdentity();
        $identity->setId('existing-identity');
        $identity->setUser($user);
        $identity->setProvider('google');
        $identity->setExternalUserId('legacy-google-id');
        $user->addExternalIdentity($identity);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturnCallback(function (array $criteria, ?array $orderBy = null) use ($user): ?User {
            if (($criteria['googleId'] ?? null) === 'legacy-google-id') {
                return $user;
            }

            return null;
        });

        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->method('findOneBy')->willReturn(null);

        $externalIdentityRepository = $this->createMock(ExternalIdentityRepository::class);
        $externalIdentityRepository->method('findOneByProviderAndExternalUserId')->with('google', 'legacy-google-id')->willReturn($identity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new GoogleOAuthAccountService(
            $entityManager,
            $userRepository,
            $profileRepository,
            $this->createMock(UserPasswordHasherInterface::class),
            new LanguageConfig(['de', 'en'], 'de'),
            $this->createMock(AuditLogger::class),
            $this->createMock(UserEmailAliasService::class),
            $externalIdentityRepository,
        );

        $resolved = $service->resolveOrCreate(new GoogleOAuthUserInfo('legacy-google-id', 'user@example.com', true, 'Ada', 'Lovelace'));

        self::assertSame($user, $resolved);
        self::assertSame('legacy-google-id', $resolved->getGoogleId());
    }

    public function testResolveOrCreateRejectsEmailAutoLinkingWithoutMatchingIdentity(): void
    {
        $profile = new Profile();
        $profile->setId('profile-123');
        $profile->setEmail('person@example.com');

        $existingUser = new User();
        $existingUser->setId('existing-user-1');
        $existingUser->setPassword('hashed');
        $existingUser->setProfile($profile);
        $existingUser->setGoogleId('different-google-id');

        $userRepository = new class($existingUser) extends UserRepository {
            public function __construct(private User $existingUser)
            {
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?User
            {
                if (($criteria['googleId'] ?? null) === 'new-google-id') {
                    return null;
                }

                if (($criteria['profileId'] ?? null) === 'profile-123') {
                    return $this->existingUser;
                }

                return null;
            }
        };

        $profileRepository = new class($profile) extends ProfileRepository {
            public function __construct(private Profile $profile)
            {
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?Profile
            {
                if (($criteria['email'] ?? null) === 'person@example.com') {
                    return $this->profile;
                }

                return null;
            }
        };

        $externalIdentityRepository = $this->createMock(ExternalIdentityRepository::class);
        $externalIdentityRepository->method('findOneByProviderAndExternalUserId')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new GoogleOAuthAccountService(
            $entityManager,
            $userRepository,
            $profileRepository,
            $this->createMock(UserPasswordHasherInterface::class),
            new LanguageConfig(['de', 'en'], 'de'),
            $this->createMock(AuditLogger::class),
            $this->createMock(UserEmailAliasService::class),
            $externalIdentityRepository,
        );

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Email already linked to another account');

        $service->resolveOrCreate(new GoogleOAuthUserInfo('new-google-id', 'person@example.com', true, 'Ada', 'Lovelace'));
    }

    public function testConflictingLegacyGoogleIdAndExternalIdentityIsRejected(): void
    {
        $legacyUser = new User();
        $legacyUser->setId('legacy-user');
        $legacyUser->setPassword('hashed');
        $legacyUser->setGoogleId('same-google-id');

        $otherUser = new User();
        $otherUser->setId('other-user');
        $otherUser->setPassword('hashed');

        $identity = new ExternalIdentity();
        $identity->setId('other-identity');
        $identity->setUser($otherUser);
        $identity->setProvider('google');
        $identity->setExternalUserId('same-google-id');

        $userRepository = new class($legacyUser) extends UserRepository {
            public function __construct(private User $legacyUser)
            {
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?User
            {
                if (($criteria['googleId'] ?? null) === 'same-google-id') {
                    return $this->legacyUser;
                }

                return null;
            }
        };

        $profileRepository = new class extends ProfileRepository {
            public function __construct()
            {
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?Profile
            {
                return null;
            }
        };

        $externalIdentityRepository = new class($identity) extends ExternalIdentityRepository {
            public function __construct(private ExternalIdentity $identity)
            {
            }

            public function findOneByProviderAndExternalUserId(string $provider, string $externalUserId): ?ExternalIdentity
            {
                if ($provider === 'google' && $externalUserId === 'same-google-id') {
                    return $this->identity;
                }

                return null;
            }
        };

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(fn (): object => new class {
            public function findOneBy(array $criteria, ?array $orderBy = null): ?object
            {
                return null;
            }
        });
        $entityManager->expects(self::never())->method('flush');

        $service = new GoogleOAuthAccountService(
            $entityManager,
            $userRepository,
            $profileRepository,
            $this->createMock(UserPasswordHasherInterface::class),
            new LanguageConfig(['de', 'en'], 'de'),
            $this->createMock(AuditLogger::class),
            $this->createMock(UserEmailAliasService::class),
            $externalIdentityRepository,
        );

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Google account already linked to another user');

        $service->resolveOrCreate(new GoogleOAuthUserInfo('same-google-id', 'user@example.com', true, 'Ada', 'Lovelace'));
    }
}
