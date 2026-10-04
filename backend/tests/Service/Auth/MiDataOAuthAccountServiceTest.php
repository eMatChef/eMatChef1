<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Config\LanguageConfig;
use App\Entity\ExternalIdentity;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ProfileRepository;
use App\Service\AuditLogger;
use App\Service\Auth\MiDataOAuthAccountService;
use App\Service\Auth\MiDataOAuthException;
use App\Service\Auth\MiDataOAuthUserInfo;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MiDataOAuthAccountServiceTest extends TestCase
{
    public function testCreatesMiDataUserAndExternalIdentityWithoutSettingLegacyGoogleId(): void
    {
        $entityManager = $this->entityManager();
        $identityRepository = $this->createMock(ExternalIdentityRepository::class);
        $identityRepository->method('findOneByProviderAndExternalUserId')->with('midata', 'sub-1')->willReturn(null);
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->method('findOneBy')->willReturn(null);
        $entityManager->expects(self::atLeast(3))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service = $this->service($entityManager, $identityRepository, $profileRepository);
        $user = $service->resolveOrCreate(new MiDataOAuthUserInfo(
            'sub-1',
            'person@example.com',
            true,
            'Ada',
            'Lovelace',
        ));

        self::assertSame('active', $user->getState());
        self::assertTrue($user->isEmailVerified());
        self::assertNull($user->getGoogleId());
        self::assertCount(1, $user->getExternalIdentities());
        self::assertSame('midata', $user->getExternalIdentities()->first()->getProvider());
        self::assertSame('sub-1', $user->getExternalIdentities()->first()->getExternalUserId());
    }

    public function testRejectsEmailAutoLinkingForUnknownMiDataSubject(): void
    {
        $entityManager = $this->entityManager();
        $identityRepository = $this->createMock(ExternalIdentityRepository::class);
        $identityRepository->method('findOneByProviderAndExternalUserId')->willReturn(null);
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->method('findOneBy')->willReturn(new Profile());
        $entityManager->expects(self::never())->method('flush');

        try {
            $this->service($entityManager, $identityRepository, $profileRepository)
                ->resolveOrCreate(new MiDataOAuthUserInfo('new-sub', 'existing@example.com', true, null, null));
            self::fail('Expected a safe email conflict');
        } catch (MiDataOAuthException $exception) {
            self::assertSame('email_conflict', $exception->reason);
        }
    }

    public function testUsesExistingMiDataIdentity(): void
    {
        $owner = $this->user('identity-owner');
        $identity = $this->identity($owner, 'existing-sub');
        $entityManager = $this->entityManager();
        $entityManager->expects(self::once())->method('flush');
        $identityRepository = $this->createMock(ExternalIdentityRepository::class);
        $identityRepository->method('findOneByProviderAndExternalUserId')->with('midata', 'existing-sub')->willReturn($identity);
        $profileRepository = $this->createMock(ProfileRepository::class);

        $resolved = $this->service($entityManager, $identityRepository, $profileRepository)
            ->resolveOrCreate(new MiDataOAuthUserInfo('existing-sub', 'new-metadata@example.com', false, null, null));

        self::assertSame($owner, $resolved);
        self::assertSame('new-metadata@example.com', $identity->getEmail());
    }

    public function testExplicitLinkingAttachesIdentityToAuthenticatedUser(): void
    {
        $user = $this->user('link-target');
        $googleIdentity = $this->identity($user, 'google-sub', 'google');
        $entityManager = $this->entityManager();
        $entityManager->expects(self::atLeastOnce())->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $identityRepository = $this->createMock(ExternalIdentityRepository::class);
        $identityRepository->method('findOneByProviderAndExternalUserId')->willReturn(null);
        $profileRepository = $this->createMock(ProfileRepository::class);

        $resolved = $this->service($entityManager, $identityRepository, $profileRepository)
            ->resolveOrCreate(new MiDataOAuthUserInfo('linked-sub', null, false, null, null), $user);

        self::assertSame($user, $resolved);
        self::assertCount(2, $user->getExternalIdentities());
        self::assertSame('google', $googleIdentity->getProvider());
        self::assertTrue($user->getExternalIdentities()->exists(
            static fn (int $key, ExternalIdentity $identity): bool => $identity->getProvider() === 'midata'
                && $identity->getExternalUserId() === 'linked-sub'
        ));
    }

    public function testExplicitLinkingRejectsIdentityOwnedByAnotherUser(): void
    {
        $existingOwner = $this->user('identity-owner');
        $linkTarget = $this->user('link-target');
        $identity = $this->identity($existingOwner, 'existing-sub');
        $entityManager = $this->entityManager();
        $entityManager->expects(self::never())->method('flush');
        $identityRepository = $this->createMock(ExternalIdentityRepository::class);
        $identityRepository->method('findOneByProviderAndExternalUserId')->willReturn($identity);

        try {
            $this->service($entityManager, $identityRepository, $this->createMock(ProfileRepository::class))
                ->resolveOrCreate(new MiDataOAuthUserInfo('existing-sub', null, false, null, null), $linkTarget);
            self::fail('Expected a linking conflict');
        } catch (MiDataOAuthException $exception) {
            self::assertSame('link_conflict', $exception->reason);
        }
    }

    private function service(
        EntityManagerInterface $entityManager,
        ExternalIdentityRepository $identityRepository,
        ProfileRepository $profileRepository,
    ): MiDataOAuthAccountService {
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-random-password');

        return new MiDataOAuthAccountService(
            $entityManager,
            $identityRepository,
            $profileRepository,
            $passwordHasher,
            new LanguageConfig(['de', 'en'], 'de'),
            $this->createMock(AuditLogger::class),
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(static fn (): object => new class {
            public function findOneBy(array $criteria, ?array $orderBy = null): ?object
            {
                return null;
            }
        });

        return $entityManager;
    }

    private function user(string $id): User
    {
        $user = new User();
        $user->setId($id);
        $user->setPassword('hashed');
        $user->setState('active');

        return $user;
    }

    private function identity(User $user, string $subject, string $provider = 'midata'): ExternalIdentity
    {
        $identity = new ExternalIdentity();
        $identity->setId('identity-id');
        $identity->setUser($user);
        $identity->setProvider($provider);
        $identity->setExternalUserId($subject);
        $user->addExternalIdentity($identity);

        return $identity;
    }
}
