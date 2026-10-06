<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserEmailAlias;
use App\Service\MembershipNotificationEmailResolver;
use App\Service\UserEmailAliasService;
use PHPUnit\Framework\TestCase;

final class MembershipNotificationEmailResolverTest extends TestCase
{
    /** @var array<string, list<UserEmailAlias>> aliases by user id */
    private array $aliases = [];

    private MembershipNotificationEmailResolver $resolver;

    protected function setUp(): void
    {
        $service = $this->createMock(UserEmailAliasService::class);
        // Spiegelt UserEmailAliasService::userOwnsEmail: Primary oder eigener verifizierter Alias.
        $service->method('userOwnsEmail')->willReturnCallback(function (User $user, string $email): bool {
            $email = strtolower(trim($email));
            if (strtolower((string) $user->getProfile()?->getEmail()) === $email) {
                return true;
            }
            foreach ($this->aliases[$user->getId()] ?? [] as $alias) {
                if ($alias->getEmail() === $email && $alias->isVerified()) {
                    return true;
                }
            }

            return false;
        });
        $service->method('listForUser')->willReturnCallback(fn (User $user): array => $this->aliases[$user->getId()] ?? []);
        $this->resolver = new MembershipNotificationEmailResolver($service);
    }

    public function testNoSelectionFallsBackToPrimary(): void
    {
        $m = $this->membership($this->user('u1', 'Main@x.test'), false);

        self::assertSame('main@x.test', $this->resolver->effectiveEmail($m));
    }

    public function testExplicitPrimaryClearsSelection(): void
    {
        $user = $this->user('u1', 'main@x.test');
        $this->alias($user, 'alt@x.test', true);
        $m = $this->membership($user, false);
        $this->resolver->select($m, 'alt@x.test');
        $this->resolver->select($m, 'MAIN@x.test');

        self::assertNull($m->getNotificationEmail());
        self::assertSame('main@x.test', $this->resolver->effectiveEmail($m));
    }

    public function testVerifiedAliasIsUsed(): void
    {
        $user = $this->user('u1', 'main@x.test');
        $this->alias($user, 'alt@x.test', true);
        $m = $this->membership($user, false);
        $this->resolver->select($m, 'Alt@x.test');

        self::assertSame('alt@x.test', $this->resolver->effectiveEmail($m));
        self::assertSame(['main@x.test', 'alt@x.test'], $this->resolver->selectableEmails($user));
    }

    public function testUnverifiedAliasIsRejectedAndNotSelectable(): void
    {
        $user = $this->user('u1', 'main@x.test');
        $this->alias($user, 'pending@x.test', false);
        $m = $this->membership($user, false);

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->resolver->select($m, 'pending@x.test');
        } finally {
            self::assertNull($m->getNotificationEmail());
            self::assertSame(['main@x.test'], $this->resolver->selectableEmails($user));
        }
    }

    public function testForeignAddressIsRejected(): void
    {
        $user = $this->user('u1', 'main@x.test');
        $other = $this->user('u2', 'other@x.test');
        $this->alias($other, 'other-alt@x.test', true);
        $m = $this->membership($user, false);

        foreach (['other@x.test', 'other-alt@x.test', 'free@elsewhere.test'] as $email) {
            try {
                $this->resolver->select($m, $email);
                self::fail("$email must be rejected");
            } catch (\InvalidArgumentException) {
                self::assertNull($m->getNotificationEmail());
            }
        }
    }

    public function testDeletedOrInvalidatedAliasFallsBackToPrimary(): void
    {
        $user = $this->user('u1', 'main@x.test');
        $alias = $this->alias($user, 'alt@x.test', true);
        $m = $this->membership($user, false);
        $this->resolver->select($m, 'alt@x.test');

        $alias->resetVerification();
        self::assertSame('main@x.test', $this->resolver->effectiveEmail($m));

        $this->aliases['u1'] = [];
        self::assertSame('main@x.test', $this->resolver->effectiveEmail($m));
    }

    public function testPrimarySwitchMakesFallbackUseNewPrimary(): void
    {
        $user = $this->user('u1', 'main@x.test');
        $alias = $this->alias($user, 'alt@x.test', true);
        $m = $this->membership($user, false);
        $this->resolver->select($m, 'alt@x.test');
        $other = $this->membership($user, true);

        // makePrimary: alt wird Primary, die alte Primary bleibt als verifizierter Alias
        $user->getProfile()->setEmail('alt@x.test');
        $alias->setEmail('main@x.test');

        self::assertSame('alt@x.test', $this->resolver->effectiveEmail($m));
        self::assertSame('alt@x.test', $this->resolver->effectiveEmail($other));
        // die alte Primary bleibt wählbar
        $this->resolver->select($other, 'main@x.test');
        self::assertSame('main@x.test', $this->resolver->effectiveEmail($other));
    }

    public function testDepartmentAndGrossanlassAreIndependent(): void
    {
        $user = $this->user('u1', 'main@x.test');
        $this->alias($user, 'alt@x.test', true);
        $department = $this->membership($user, false);
        $grossanlass = $this->membership($user, true);
        $this->resolver->select($grossanlass, 'alt@x.test');

        self::assertSame('main@x.test', $this->resolver->effectiveEmail($department));
        self::assertSame('alt@x.test', $this->resolver->effectiveEmail($grossanlass));
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

        return $user;
    }

    private function alias(User $user, string $email, bool $verified): UserEmailAlias
    {
        $alias = new UserEmailAlias();
        $alias->setId('a' . \count($this->aliases[$user->getId()] ?? []));
        $alias->setUser($user);
        $alias->setEmail($email);
        if ($verified) {
            $alias->markVerified();
        }
        $this->aliases[$user->getId()][] = $alias;

        return $alias;
    }

    private function membership(User $user, bool $grossanlass): Membership
    {
        static $n = 0;
        $department = new Department();
        $department->setId('d' . ++$n);
        $department->setIsGrossanlass($grossanlass);
        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);

        return $membership;
    }
}
