<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserRecoveryCode;
use App\Entity\UserTotp;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\AuditLogger;
use App\Service\Auth\TotpException;
use App\Service\Auth\TotpService;
use App\Service\Crypto\SecretBox;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;

/**
 * In-Memory-Fake für UserTotp und UserRecoveryCode.
 */
final class TotpServiceTest extends TestCase
{
    private const APP_SECRET = 'test-app-secret';

    /** @var list<object> */
    private array $store = [];

    /** @var list<array{string, array<string, mixed>}> */
    private array $audits = [];

    private SecretBox $box;

    private TotpService $service;

    protected function setUp(): void
    {
        $this->store = [];
        $this->audits = [];
        $this->box = new SecretBox(self::APP_SECRET);
        $this->service = $this->buildService();
    }

    public function testStartCreatesSecretButActivatesNothing(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);

        self::assertNotSame('', $setup['secret']);
        self::assertStringStartsWith('otpauth://totp/', $setup['otpauth_uri']);
        self::assertStringContainsString($setup['secret'], $setup['otpauth_uri']);
        $status = $this->service->status($user);
        self::assertFalse($status['enabled']);
        self::assertTrue($status['pending_enrollment']);
        self::assertSame(0, $this->rows(UserRecoveryCode::class));
        self::assertFalse($this->service->isEnabled($user));
    }

    public function testWrongConfirmationCodeIsRejected(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);
        $wrong = $this->code($setup['secret']) === '000000' ? '111111' : '000000';

        try {
            $this->service->confirmEnrollment($user, $wrong);
            self::fail('wrong code accepted');
        } catch (TotpException $e) {
            self::assertSame(TotpException::INVALID_CODE, $e->reason);
        }
        self::assertFalse($this->service->isEnabled($user));
        self::assertSame(0, $this->rows(UserRecoveryCode::class));
    }

    public function testConfirmActivatesAndCreatesExactlyThreeHashedRecoveryCodes(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);
        $codes = $this->service->confirmEnrollment($user, $this->code($setup['secret']));

        self::assertTrue($this->service->isEnabled($user));
        self::assertCount(3, $codes);
        self::assertCount(3, array_unique($codes));
        self::assertSame(3, $this->rows(UserRecoveryCode::class));
        foreach ($this->all(UserRecoveryCode::class) as $row) {
            foreach ($codes as $plain) {
                self::assertStringNotContainsStringIgnoringCase(str_replace('-', '', $plain), $row->getCodeHash());
                self::assertNotSame($plain, $row->getCodeHash());
            }
            self::assertSame(64, \strlen($row->getCodeHash()));
        }
        $status = $this->service->status($user);
        self::assertSame(3, $status['recovery_codes_remaining']);
        self::assertFalse($status['recovery_codes_low']);
    }

    public function testSecretIsStoredEncryptedAndNotInStatusOrAudit(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);
        $codes = $this->service->confirmEnrollment($user, $this->code($setup['secret']));

        /** @var UserTotp $row */
        $row = $this->all(UserTotp::class)[0];
        self::assertNotNull($row->getSecretEncrypted());
        self::assertStringNotContainsString($setup['secret'], (string) $row->getSecretEncrypted());
        self::assertSame($setup['secret'], $this->box->decrypt((string) $row->getSecretEncrypted()));

        $blob = json_encode([$this->service->status($user), $this->audits], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($setup['secret'], $blob);
        foreach ($codes as $plain) {
            self::assertStringNotContainsString($plain, $blob);
        }
    }

    public function testRecoveryCodeWorksOnceAndDoesNotDisableTotp(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);
        $codes = $this->service->confirmEnrollment($user, $this->code($setup['secret']));

        self::assertTrue($this->service->verifySecondFactor($user, strtolower($codes[0])));
        self::assertFalse($this->service->verifySecondFactor($user, $codes[0]));
        self::assertTrue($this->service->isEnabled($user));
        self::assertSame(2, $this->service->status($user)['recovery_codes_remaining']);
        self::assertTrue($this->service->verifySecondFactor($user, $codes[1]));
        $status = $this->service->status($user);
        self::assertSame(1, $status['recovery_codes_remaining']);
        self::assertTrue($status['recovery_codes_low']);
    }

    public function testRegenerationInvalidatesOldCodesAndCreatesExactlyThreeNew(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);
        $old = $this->service->confirmEnrollment($user, $this->code($setup['secret']));
        $this->service->verifySecondFactor($user, $old[0]);

        $new = $this->service->regenerateRecoveryCodes($user, $old[1]);

        self::assertCount(3, $new);
        self::assertSame(3, $this->rows(UserRecoveryCode::class));
        self::assertSame(3, $this->service->status($user)['recovery_codes_remaining']);
        self::assertSame([], array_intersect($old, $new));
        self::assertFalse($this->service->verifySecondFactor($user, $old[2]));
        self::assertTrue($this->service->verifySecondFactor($user, $new[0]));
    }

    public function testRegenerationNeedsValidCode(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);
        $this->service->confirmEnrollment($user, $this->code($setup['secret']));

        $this->expectException(TotpException::class);
        $this->service->regenerateRecoveryCodes($user, null);
    }

    public function testOrdinaryUserCanDisableAndEverythingIsRemoved(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);
        $codes = $this->service->confirmEnrollment($user, $this->code($setup['secret']));

        $this->service->disable($user, $codes[0]);

        self::assertFalse($this->service->isEnabled($user));
        self::assertSame(0, $this->rows(UserTotp::class));
        self::assertSame(0, $this->rows(UserRecoveryCode::class));
    }

    public function testAdminCannotDisableUntilLastAdminRoleIsGone(): void
    {
        $user = $this->user(['ROLE_ORGANISATIONSCHEF']);
        self::assertTrue($this->service->status($user)['admin_blocked']);
        $setup = $this->service->startEnrollment($user);
        $codes = $this->service->confirmEnrollment($user, $this->code($setup['secret']));

        $status = $this->service->status($user);
        self::assertTrue($status['required']);
        self::assertFalse($status['admin_blocked']);
        self::assertFalse($status['can_disable']);
        try {
            $this->service->disable($user, $codes[0]);
            self::fail('admin disabled totp');
        } catch (TotpException $e) {
            self::assertSame(TotpException::REQUIRED_FOR_ADMIN, $e->reason);
        }
        self::assertTrue($this->service->isEnabled($user));
        self::assertSame(3, $this->service->status($user)['recovery_codes_remaining']);

        // letzte Adminrolle entfernt: TOTP bleibt, wird optional und ist deaktivierbar
        $user->getProfile()->setRoles([]);
        $status = $this->service->status($user);
        self::assertTrue($status['enabled']);
        self::assertFalse($status['required']);
        self::assertTrue($status['can_disable']);
        $this->service->disable($user, $codes[0]);
        self::assertFalse($this->service->isEnabled($user));
    }

    public function testAllGlobalAdminRolesRequireTotp(): void
    {
        foreach (['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'] as $role) {
            self::assertTrue($this->service->isAdminAccessBlocked($this->user([$role])), $role);
        }
        self::assertFalse($this->service->isAdminAccessBlocked($this->user()));
    }

    public function testReEnrollmentNeedsCodeAndInvalidatesOldSecretAndCodes(): void
    {
        $user = $this->user();
        $first = $this->service->startEnrollment($user);
        $oldCodes = $this->service->confirmEnrollment($user, $this->code($first['secret']));

        try {
            $this->service->startEnrollment($user, null);
            self::fail('re-enrollment without code');
        } catch (TotpException $e) {
            self::assertSame(TotpException::CODE_REQUIRED, $e->reason);
        }

        $second = $this->service->startEnrollment($user, $oldCodes[0]);
        self::assertNotSame($first['secret'], $second['secret']);
        // bis zur Bestätigung gilt das alte Secret weiter
        self::assertTrue($this->service->isEnabled($user));

        $newCodes = $this->service->confirmEnrollment($user, $this->code($second['secret']));
        self::assertCount(3, $newCodes);
        self::assertSame(3, $this->rows(UserRecoveryCode::class));
        self::assertFalse($this->service->verifySecondFactor($user, $oldCodes[1]));
        self::assertTrue($this->service->verifySecondFactor($user, $newCodes[0]));
        /** @var UserTotp $row */
        $row = $this->all(UserTotp::class)[0];
        self::assertSame($second['secret'], $this->box->decrypt((string) $row->getSecretEncrypted()));
    }

    public function testTotpCodeCannotBeReplayedAndLockoutAfterRepeatedFailures(): void
    {
        $user = $this->user();
        $setup = $this->service->startEnrollment($user);
        $this->service->confirmEnrollment($user, $this->code($setup['secret']));

        // Der Bestätigungscode verbraucht keinen Auth-Schritt: einmal für den ersten Login nutzbar, dann gesperrt.
        self::assertTrue($this->service->verifySecondFactor($user, $this->code($setup['secret'])));
        self::assertFalse($this->service->verifySecondFactor($user, $this->code($setup['secret'])));

        for ($i = 0; $i < 4; ++$i) {
            self::assertFalse($this->service->verifySecondFactor($user, 'AAAAA-BBBBB'));
        }
        $this->expectException(TotpException::class);
        $this->service->verifySecondFactor($user, 'AAAAA-BBBBB');
    }

    public function testRealEnrollmentUsesRandom160BitSecrets(): void
    {
        $secrets = [];
        foreach ([$this->user(), $this->user()] as $user) {
            $secrets[] = $this->service->startEnrollment($user)['secret'];
        }

        self::assertNotSame($secrets[0], $secrets[1]);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secrets[0]);
    }

    private function code(string $secret): string
    {
        return TOTP::createFromSecret($secret)->now();
    }

    /**
     * @param list<string> $roles
     */
    private function user(array $roles = []): User
    {
        static $n = 0;
        ++$n;
        $profile = new Profile();
        $profile->setId('p_' . $n);
        $profile->setEmail("u$n@x.test");
        $profile->setRoles($roles);
        $user = new User();
        $user->setId('u' . $n);
        $user->setProfileId('p_' . $n);
        $user->setProfile($profile);

        return $user;
    }

    /**
     * @param class-string $class
     *
     * @return list<object>
     */
    private function all(string $class): array
    {
        return array_values(array_filter($this->store, static fn (object $o): bool => $o instanceof $class));
    }

    /**
     * @param class-string $class
     */
    private function rows(string $class): int
    {
        return \count($this->all($class));
    }

    /**
     * @param class-string         $class
     * @param array<string, mixed> $criteria
     *
     * @return list<object>
     */
    private function match(string $class, array $criteria): array
    {
        return array_values(array_filter($this->all($class), function (object $o) use ($criteria): bool {
            foreach ($criteria as $field => $value) {
                $prop = new \ReflectionProperty($o, $field);
                if ($prop->getValue($o) !== $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function buildService(): TotpService
    {
        $em = $this->createMock(EntityManager::class);
        $em->method('getRepository')->willReturnCallback(function (string $class): EntityRepository {
            $repo = $this->createMock(EntityRepository::class);
            $repo->method('findOneBy')->willReturnCallback(fn (array $c) => $this->match($class, $c)[0] ?? null);
            $repo->method('find')->willReturnCallback(static fn () => null);
            $repo->method('findBy')->willReturnCallback(fn (array $c) => $this->match($class, $c));
            $repo->method('count')->willReturnCallback(fn (array $c) => \count($this->match($class, $c)));

            return $repo;
        });
        $em->method('persist')->willReturnCallback(function (object $o): void {
            $this->store[] = $o;
        });
        $em->method('remove')->willReturnCallback(function (object $o): void {
            $this->store = array_values(array_filter($this->store, static fn (object $x): bool => $x !== $o));
        });
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $f) => $f());

        $audit = $this->createMock(AuditLogger::class);
        $audit->method('log')->willReturnCallback(function (string $type, string $id, string $action, $actor, $target, $dept, array $changes): void {
            $this->audits[] = [$action, $changes];
        });

        $checker = (new \ReflectionClass(AdminCapabilityChecker::class))->newInstanceWithoutConstructor();

        return new TotpService($em, $this->box, $checker, $audit, self::APP_SECRET);
    }
}
