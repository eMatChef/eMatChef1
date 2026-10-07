<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserEmailAlias;
use App\Repository\ProfileRepository;
use App\Service\AuditLogger;
use App\Service\UserEmailAliasConflictException;
use App\Service\UserEmailAliasService;
use App\Service\VerificationEmailService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * In-Memory-Fake für Profile, User und UserEmailAlias.
 */
final class UserEmailAliasServiceTest extends TestCase
{
    /** @var list<User> */
    private array $users = [];

    /** @var list<UserEmailAlias> */
    private array $aliases = [];

    /** @var list<array{string, string}> sent [email, token] */
    private array $sent = [];

    private int $transactions = 0;

    public function testLoginResolvesPrimaryCaseInsensitive(): void
    {
        $user = $this->user('u1', 'Anna@Example.ch');

        self::assertSame($user, $this->service()->findLoginUserByEmail(' anna@example.CH '));
    }

    public function testLoginResolvesVerifiedLoginEnabledAliasOnly(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $this->alias($user, 'verified@example.ch', true);
        $this->alias($user, 'pending@example.ch', false);
        $this->alias($user, 'nologin@example.ch', true)->setLoginEnabled(false);
        $service = $this->service();

        self::assertSame($user, $service->findLoginUserByEmail('Verified@example.ch'));
        self::assertNull($service->findLoginUserByEmail('pending@example.ch'));
        self::assertNull($service->findLoginUserByEmail('nologin@example.ch'));
        self::assertNull($service->findLoginUserByEmail('unknown@example.ch'));
    }

    public function testUnverifiedAliasIsNotProofOfOwnership(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $this->alias($user, 'pending@example.ch', false);
        $service = $this->service();

        self::assertNull($service->findUserByEmail('pending@example.ch'));
        self::assertFalse($service->userOwnsEmail($user, 'pending@example.ch'));
    }

    public function testTakenConsidersOtherAccountsPrimaryAliasesAndOpenPendingOnly(): void
    {
        $owner = $this->user('u1', 'anna@example.ch');
        $other = $this->user('u2', 'ben@example.ch');
        $this->alias($owner, 'open@example.ch', false);
        $this->alias($owner, 'expired@example.ch', false, new \DateTime('-1 hour'));
        $service = $this->service();

        self::assertTrue($service->isEmailTaken('ANNA@example.ch', $other));
        self::assertTrue($service->isEmailTaken('open@example.ch', $other));
        self::assertFalse($service->isEmailTaken('expired@example.ch', $other));
        self::assertFalse($service->isEmailTaken('open@example.ch', $owner));
        self::assertFalse($service->isEmailTaken('free@example.ch', $other));
    }

    public function testAddCreatesUnverifiedAliasAndSendsHashedToken(): void
    {
        $user = $this->user('u1', 'anna@example.ch');

        $alias = $this->service()->addEmail($user, ' Second@Example.ch ');

        self::assertSame('second@example.ch', $alias->getEmail());
        self::assertFalse($alias->isVerified());
        self::assertTrue($alias->isLoginEnabled());
        self::assertCount(1, $this->sent);
        [$to, $token] = $this->sent[0];
        self::assertSame('second@example.ch', $to);
        self::assertSame($alias, $this->findAliasBy('verificationTokenHash', hash('sha256', $token)));
    }

    public function testAddRejectsOwnPrimaryExistingAliasForeignAddressAndInvalidInput(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $other = $this->user('u2', 'ben@example.ch');
        $this->alias($user, 'mine@example.ch', true);
        $this->alias($other, 'bens-second@example.ch', true);
        $service = $this->service();

        foreach (['anna@example.ch', 'MINE@example.ch', 'ben@example.ch', 'bens-second@example.ch'] as $email) {
            try {
                $service->addEmail($user, $email);
                self::fail('Expected conflict for ' . $email);
            } catch (UserEmailAliasConflictException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        $service->addEmail($user, 'kein-mail');
    }

    public function testAddReplacesForeignExpiredUnverifiedAlias(): void
    {
        $other = $this->user('u2', 'ben@example.ch');
        $this->alias($other, 'free@example.ch', false, new \DateTime('-1 hour'));
        $user = $this->user('u1', 'anna@example.ch');

        $alias = $this->service()->addEmail($user, 'free@example.ch');

        self::assertSame('u1', $alias->getUserId());
        self::assertCount(1, array_filter($this->aliases, static fn (UserEmailAlias $a): bool => $a->getEmail() === 'free@example.ch'));
    }

    public function testAddEnforcesLimit(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        for ($i = 0; $i < UserEmailAliasService::MAX_ADDITIONAL_EMAILS; ++$i) {
            $this->alias($user, "a{$i}@example.ch", true);
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->addEmail($user, 'one-more@example.ch');
    }

    public function testVerifyByTokenMarksAliasVerified(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $service = $this->service();
        $alias = $service->addEmail($user, 'second@example.ch');

        self::assertSame($alias, $service->verifyByToken($this->sent[0][1]));
        self::assertTrue($alias->isVerified());
        self::assertSame($user, $service->findLoginUserByEmail('second@example.ch'));
        self::assertNull($service->verifyByToken($this->sent[0][1]), 'Token ist danach verbraucht');
    }

    public function testVerifyByTokenRejectsExpiredLink(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $alias = $this->alias($user, 'second@example.ch', false);
        $alias->setVerificationToken(hash('sha256', 'tok'), new \DateTime('-1 minute'));

        $this->expectException(\DomainException::class);
        $this->service()->verifyByToken('tok');
    }

    public function testUnknownTokenIsNotAnAliasToken(): void
    {
        self::assertNull($this->service()->verifyByToken('nope'));
    }

    public function testMakePrimarySwapsAtomicallyAndKeepsOldPrimary(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $user->setPendingEmail('typed@example.ch');
        $alias = $this->alias($user, 'second@example.ch', true);
        $service = $this->service();

        $service->makePrimary($user, $alias);

        self::assertSame(1, $this->transactions);
        self::assertSame('second@example.ch', $user->getProfile()?->getEmail());
        self::assertSame('anna@example.ch', $alias->getEmail());
        self::assertTrue($alias->isVerified());
        self::assertNull($user->getPendingEmail());
        self::assertSame($user, $service->findLoginUserByEmail('anna@example.ch'));
        self::assertSame($user, $service->findLoginUserByEmail('second@example.ch'));
    }

    public function testUnverifiedOldPrimaryStaysUnverifiedAfterSwap(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $user->setEmailVerified(false);
        $alias = $this->alias($user, 'second@example.ch', true);

        $this->service()->makePrimary($user, $alias);

        self::assertTrue($user->isEmailVerified());
        self::assertFalse($alias->isVerified());
    }

    public function testUnverifiedAliasCannotBecomePrimary(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $alias = $this->alias($user, 'second@example.ch', false);

        try {
            $this->service()->makePrimary($user, $alias);
            self::fail('Expected rejection');
        } catch (\InvalidArgumentException) {
            self::assertSame('anna@example.ch', $user->getProfile()?->getEmail());
            self::assertSame(0, $this->transactions);
        }
    }

    public function testRemoveDeletesOnlyOwnAlias(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $other = $this->user('u2', 'ben@example.ch');
        $alias = $this->alias($other, 'bens@example.ch', true);
        $service = $this->service();

        try {
            $service->removeEmail($user, $alias);
            self::fail('Expected rejection');
        } catch (\InvalidArgumentException) {
            self::assertContains($alias, $this->aliases);
        }

        $own = $this->alias($user, 'mine@example.ch', true);
        $service->removeEmail($user, $own);
        self::assertNotContains($own, $this->aliases);
        self::assertSame('anna@example.ch', $user->getProfile()?->getEmail());
    }

    public function testInviteClaimVerifiesOwnPendingAlias(): void
    {
        $user = $this->user('u1', 'anna@example.ch');
        $alias = $this->alias($user, 'invite@example.ch', false);

        $this->service()->claimVerifiedEmail($user, 'invite@example.ch');

        self::assertTrue($alias->isVerified());
    }

    private function service(): UserEmailAliasService
    {
        $aliasRepository = $this->createMock(EntityRepository::class);
        $aliasRepository->method('findOneBy')->willReturnCallback(
            fn (array $criteria): ?UserEmailAlias => $this->findAliasBy((string) array_key_first($criteria), (string) reset($criteria))
        );
        $aliasRepository->method('find')->willReturnCallback(fn (string $id): ?UserEmailAlias => $this->findAliasBy('id', $id));
        $aliasRepository->method('findBy')->willReturnCallback(fn (array $criteria): array => array_values(array_filter(
            $this->aliases,
            static fn (UserEmailAlias $a): bool => $a->getUserId() === $criteria['userId']
        )));

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('findOneBy')->willReturnCallback(function (array $criteria): ?User {
            foreach ($this->users as $user) {
                if (isset($criteria['profileId']) && $user->getProfileId() === $criteria['profileId']) {
                    return $user;
                }
                if (isset($criteria['pendingEmail']) && $user->getPendingEmail() === $criteria['pendingEmail']) {
                    return $user;
                }
            }

            return null;
        });

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class) => $class === UserEmailAlias::class ? $aliasRepository : $userRepository
        );
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof UserEmailAlias && !\in_array($entity, $this->aliases, true)) {
                $this->aliases[] = $entity;
            }
        });
        $entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            $this->aliases = array_values(array_filter($this->aliases, static fn (UserEmailAlias $a): bool => $a !== $entity));
        });
        $entityManager->method('wrapInTransaction')->willReturnCallback(function (callable $func) {
            ++$this->transactions;

            return $func();
        });

        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->method('findOneByEmailInsensitive')->willReturnCallback(function (string $email): ?Profile {
            foreach ($this->users as $user) {
                if (strtolower((string) $user->getProfile()?->getEmail()) === strtolower(trim($email))) {
                    return $user->getProfile();
                }
            }

            return null;
        });

        $mailer = $this->createMock(VerificationEmailService::class);
        $mailer->method('sendPendingEmailChangeVerification')->willReturnCallback(
            function (User $user, string $email, string $token): void {
                $this->sent[] = [$email, $token];
            }
        );

        return new UserEmailAliasService($entityManager, $profileRepository, $mailer, $this->createMock(AuditLogger::class));
    }

    private function findAliasBy(string $field, string $value): ?UserEmailAlias
    {
        foreach ($this->aliases as $alias) {
            $actual = match ($field) {
                'email' => $alias->getEmail(),
                'id' => $alias->getId(),
                'verificationTokenHash' => (new \ReflectionProperty($alias, 'verificationTokenHash'))->getValue($alias),
                default => null,
            };
            if ($actual === $value) {
                return $alias;
            }
        }

        return null;
    }

    private function user(string $id, string $email): User
    {
        $profile = new Profile();
        $profile->setId('p_' . $id);
        $profile->setEmail($email);

        $user = new User();
        $user->setId($id);
        $user->setProfileId('p_' . $id);
        $user->setProfile($profile);
        $user->setEmailVerified(true);
        $this->users[] = $user;

        return $user;
    }

    private function alias(User $user, string $email, bool $verified, ?\DateTime $expiresAt = null): UserEmailAlias
    {
        $alias = new UserEmailAlias();
        $alias->setId('a' . \count($this->aliases));
        $alias->setUser($user);
        $alias->setEmail($email);
        if ($verified) {
            $alias->markVerified();
        } else {
            $alias->setVerificationToken(hash('sha256', 'token-' . $email), $expiresAt ?? new \DateTime('+1 day'));
        }
        $this->aliases[] = $alias;

        return $alias;
    }
}
