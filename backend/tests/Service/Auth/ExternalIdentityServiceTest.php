<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\ExternalIdentity;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Service\AuditLogger;
use App\Service\Auth\ExternalIdentityException;
use App\Service\Auth\ExternalIdentityService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ExternalIdentityServiceTest extends TestCase
{
    /** @var array<string, ExternalIdentity> provider|sub => identity (globaler Bestand) */
    private array $global = [];

    /** @var list<object> */
    private array $persisted = [];

    /** @var list<object> */
    private array $removed = [];

    /** @var list<array{string, array<string, mixed>}> */
    private array $audits = [];

    private int $flushes = 0;

    protected function setUp(): void
    {
        $this->global = [];
        $this->persisted = [];
        $this->removed = [];
        $this->audits = [];
        $this->flushes = 0;
    }

    public function testPasswordLoginCountsOnlyForActiveAccountsWithVerifiedPrimaryEmail(): void
    {
        $service = $this->service();
        $google = $this->identity($user = $this->user('u1'), 'google', 'g1');

        self::assertSame(['password'], $service->remainingLoginMethods($user, $google));

        $user->setEmailVerified(false);
        self::assertSame([], $service->remainingLoginMethods($user, $google));
        self::assertFalse($service->canUnlink($user, $google));

        $user->setEmailVerified(true)->setState('inactive');
        self::assertSame([], $service->remainingLoginMethods($user, $google), 'inaktives Konto hat keine nutzbare Methode');
    }

    public function testOtherIdentitiesCountAsLoginMethodsNotJustTheirNumber(): void
    {
        $service = $this->service();
        $user = $this->user('u1')->setEmailVerified(false);
        $a = $this->identity($user, 'google', 'g1', 'idn000000001');
        $b = $this->identity($user, 'midata', 'm1', 'idn000000002');

        self::assertSame(['identity:idn000000002'], $service->remainingLoginMethods($user, $a));
        self::assertTrue($service->canUnlink($user, $a));
        $service->unlink($user, 'idn000000001');
        self::assertFalse($service->canUnlink($user, $b), 'die letzte verbleibende Identität ohne Passwort-Weg bleibt geschützt');
    }

    public function testUnlinkRefusesTheLastLoginMethodAndChangesNothing(): void
    {
        $service = $this->service();
        $user = $this->user('u1')->setEmailVerified(false);
        $this->identity($user, 'midata', 'm1', 'idn000000001');

        try {
            $service->unlink($user, 'idn000000001');
            self::fail('Letzte Anmeldemethode muss geschützt sein');
        } catch (ExternalIdentityException $e) {
            self::assertSame(ExternalIdentityException::LAST_LOGIN_METHOD, $e->reason);
        }

        self::assertCount(1, $user->getExternalIdentities());
        self::assertSame([], $this->removed);
        self::assertSame([], $this->audits);
    }

    public function testUnlinkRemovesOnlyTheChosenIdentityAndTouchesNoLocalData(): void
    {
        $service = $this->service();
        $user = $this->user('u1');
        $first = $this->identity($user, 'google', 'g1', 'idn000000001');
        $second = $this->identity($user, 'google', 'g2', 'idn000000002');
        $midata = $this->identity($user, 'midata', 'm1', 'idn000000003');
        $user->setGoogleId('g1');

        $service->unlink($user, 'idn000000001');

        self::assertSame([$first], $this->removed);
        self::assertSame([$second, $midata], array_values($user->getExternalIdentities()->toArray()));
        self::assertSame('g2', $user->getGoogleId(), 'Legacy-googleId zeigt auf die verbleibende Google-Identität');
        self::assertSame('external_identity_unlinked', $this->audits[0][0]);
        self::assertSame(['provider' => ['old' => 'google', 'new' => null]], $this->audits[0][1]);
        self::assertSame('u1@x.test', $user->getProfile()?->getEmail(), 'Primary-Adresse unverändert');
    }

    public function testUnlinkOfUnknownOrForeignIdentityIsNotFound(): void
    {
        $service = $this->service();
        $me = $this->user('u1');
        $foreign = $this->identity($this->user('u2'), 'midata', 'm9', 'idn000000009');

        foreach (['idn000000009', 'nope'] as $id) {
            try {
                $service->unlink($me, $id);
                self::fail('muss NOT_FOUND sein');
            } catch (ExternalIdentityException $e) {
                self::assertSame(ExternalIdentityException::NOT_FOUND, $e->reason);
            }
        }
        self::assertSame($foreign->getUser()->getId(), 'u2');
        self::assertSame([], $this->removed);
    }

    public function testAttachCreatesSeveralIdentitiesOfOneProviderWithoutTouchingEmails(): void
    {
        $service = $this->service();
        $user = $this->user('u1');

        $a = $service->attach($user, 'google', 'g-1', 'private@gmail.test', 'Anna A');
        $b = $service->attach($user, 'google', 'g-2', 'work@gmail.test', null);

        self::assertCount(2, $user->getExternalIdentities());
        self::assertNotSame($a->getId(), $b->getId());
        self::assertSame('Anna A', $a->getDisplayName());
        self::assertSame('private@gmail.test', $a->getEmail());
        // Provider-E-Mail wird weder Primary noch Alias, und die Primary-Adresse bleibt unverändert
        self::assertSame('u1@x.test', $user->getProfile()?->getEmail());
        self::assertSame('g-1', $user->getGoogleId());
        self::assertCount(2, array_filter($this->persisted, static fn (object $o): bool => $o instanceof ExternalIdentity));
    }

    public function testAttachIsIdempotentForTheSameUserAndNeverMovesAForeignIdentity(): void
    {
        $service = $this->service();
        $owner = $this->user('u1');
        $other = $this->user('u2');
        $service->attach($owner, 'midata', 'm-1', 'a@b.test', 'A B');
        $persistedBefore = \count($this->persisted);

        $again = $service->attach($owner, 'midata', 'm-1', 'new@b.test', null);
        self::assertSame(1, $owner->getExternalIdentities()->count());
        self::assertSame('new@b.test', $again->getEmail());
        self::assertSame('A B', $again->getDisplayName());
        self::assertCount($persistedBefore, $this->persisted);

        try {
            $service->attach($other, 'midata', 'm-1', 'a@b.test', 'A B');
            self::fail('Fremde Identität darf nicht übernommen werden');
        } catch (ExternalIdentityException $e) {
            self::assertSame(ExternalIdentityException::LINK_CONFLICT, $e->reason);
        }
        self::assertCount(0, $other->getExternalIdentities());
        self::assertSame('u1', $again->getUser()->getId());
    }

    public function testNoAutomaticMergeByEqualEmail(): void
    {
        $service = $this->service();
        $victim = $this->user('u1');
        $attacker = $this->user('u2');

        // Identität eines anderen Anbieter-Kontos mit der E-Mail des Opfers landet nur beim angemeldeten User
        $service->attach($attacker, 'google', 'g-attacker', $victim->getProfile()?->getEmail(), 'Mallory');

        self::assertCount(0, $victim->getExternalIdentities());
        self::assertCount(1, $attacker->getExternalIdentities());
    }

    public function testAttachRejectsInactiveAccountsAndUnknownProviders(): void
    {
        $service = $this->service();

        foreach ([[$this->user('u1')->setState('inactive'), 'google', ExternalIdentityException::INACTIVE], [$this->user('u2'), 'facebook', ExternalIdentityException::UNSUPPORTED_PROVIDER]] as [$user, $provider, $reason]) {
            try {
                $service->attach($user, $provider, 'x', null, null);
                self::fail('muss abgelehnt werden');
            } catch (ExternalIdentityException $e) {
                self::assertSame($reason, $e->reason);
            }
        }
        self::assertSame([], $this->persisted);
    }

    public function testAuditNeverContainsSubjectTokensOrProviderEmail(): void
    {
        $service = $this->service();
        $user = $this->user('u1');

        $service->attach($user, 'google', 'SECRET-SUB-123', 'mail@gmail.test', 'Anna');
        $service->attach($user, 'midata', 'SECRET-SUB-456', 'mail@midata.test', 'Anna');
        $service->unlink($user, $user->getExternalIdentities()->first()->getId());

        $json = json_encode($this->audits, JSON_THROW_ON_ERROR);
        foreach (['SECRET-SUB', 'mail@', 'token', 'code'] as $needle) {
            self::assertStringNotContainsString($needle, $json);
        }
        self::assertSame(['external_identity_linked', 'external_identity_linked', 'external_identity_unlinked'], array_column($this->audits, 0));
    }

    private function service(): ExternalIdentityService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));
        $em->method('persist')->willReturnCallback(function (object $o): void {
            $this->persisted[] = $o;
            if ($o instanceof ExternalIdentity) {
                $this->global[$o->getProvider() . '|' . $o->getExternalUserId()] = $o;
            }
        });
        $em->method('remove')->willReturnCallback(function (object $o): void {
            $this->removed[] = $o;
            if ($o instanceof ExternalIdentity) {
                unset($this->global[$o->getProvider() . '|' . $o->getExternalUserId()]);
            }
        });
        $em->method('flush')->willReturnCallback(function (): void {
            ++$this->flushes;
        });
        $repo = $this->createMock(ExternalIdentityRepository::class);
        $repo->method('findOneByProviderAndExternalUserId')->willReturnCallback(
            fn (string $provider, string $sub): ?ExternalIdentity => $this->global[$provider . '|' . $sub] ?? null,
        );
        $audit = $this->createMock(AuditLogger::class);
        $audit->method('log')->willReturnCallback(function (string $type, string $id, string $action, $actor = null, $target = null, $dept = null, array $changes = []): void {
            $this->audits[] = [$action, $changes];
        });

        return new ExternalIdentityService($em, $repo, $audit);
    }

    private function identity(User $user, string $provider, string $sub, ?string $id = null): ExternalIdentity
    {
        $identity = (new ExternalIdentity())->setId($id ?? 'idn' . substr(md5($provider . $sub), 0, 9))->setProvider($provider)->setExternalUserId($sub);
        $user->addExternalIdentity($identity);
        $this->global[$provider . '|' . $sub] = $identity;

        return $identity;
    }

    private function user(string $id): User
    {
        $profile = (new Profile())->setId('p' . $id)->setEmail($id . '@x.test');
        $user = (new User())->setId($id)->setProfileId('p' . $id)->setProfile($profile)->setState('active')->setEmailVerified(true);

        return $user;
    }
}
