<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserRecoveryCode;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\Service\Auth\StepUpService;
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

    public function testFixedDemoSecretIsReproducibleAndReseedKeepsItUntouched(): void
    {
        $user = $this->user(['ROLE_ORGANISATIONSCHEF']);
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

        self::assertTrue($this->service->provisionFixedSecret($user, $secret));
        self::assertTrue($this->service->isEnabled($user));
        self::assertSame(3, $this->service->status($user)['recovery_codes_remaining']);
        self::assertTrue($this->service->verifySecondFactor($user, $this->code($secret)));
        $hashes = array_map(static fn (UserRecoveryCode $r): string => $r->getCodeHash(), $this->all(UserRecoveryCode::class));
        /** @var UserTotp $row */
        $row = $this->all(UserTotp::class)[0];
        $encrypted = $row->getSecretEncrypted();

        // Reseed mit demselben Secret: nichts ändert sich
        self::assertFalse($this->service->provisionFixedSecret($user, $secret));
        self::assertSame($encrypted, $row->getSecretEncrypted());
        self::assertSame($hashes, array_map(static fn (UserRecoveryCode $r): string => $r->getCodeHash(), $this->all(UserRecoveryCode::class)));
        self::assertSame(1, $this->rows(UserTotp::class));
    }

    public function testRealEnrollmentStillUsesRandomSecrets(): void
    {
        $secrets = [];
        foreach ([$this->user(), $this->user()] as $user) {
            $secrets[] = $this->service->startEnrollment($user)['secret'];
        }

        self::assertNotSame($secrets[0], $secrets[1]);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secrets[0]);
    }

    public function testStepUpWithTotpSetsStepUpAndMfaOnAFreshSession(): void
    {
        [$user, $session, $secret] = $this->enrolledUserWithSession();
        $step = $this->stepUpService();

        $step->confirm($user, $session, $this->codeAtOffset($secret, 1));

        self::assertNotNull($session->getStepUpAt());
        self::assertNotNull($session->getMfaVerifiedAt());
        self::assertTrue($this->service->isEnabled($user));
        self::assertContains('step_up_success', array_column($this->audits, 0));
    }

    public function testStepUpWithRecoveryCodeConsumesItOnceAndKeepsTotp(): void
    {
        [$user, $session, , $recovery] = $this->enrolledUserWithSession();
        $step = $this->stepUpService();

        $step->confirm($user, $session, $recovery[0]);

        self::assertNotNull($session->getStepUpAt());
        self::assertTrue($this->service->isEnabled($user));
        self::assertSame(2, $this->service->status($user)['recovery_codes_remaining']);
        $actions = array_column($this->audits, 0);
        self::assertSame(1, \count(array_keys($actions, 'recovery_code_used', true)), 'Recovery-Nutzung wird nicht doppelt geloggt');
        self::assertSame(1, \count(array_keys($actions, 'step_up_success', true)));

        $other = new UserSession($user, AuthMethod::PASSWORD, null);
        try {
            $step->confirm($user, $other, $recovery[0]);
            self::fail('recovery code reused');
        } catch (TotpException $e) {
            self::assertSame(TotpException::INVALID_CODE, $e->reason);
        }
        self::assertNull($other->getStepUpAt());
    }

    public function testWrongCodeAndReplayDoNotStepUp(): void
    {
        [$user, $session, $secret] = $this->enrolledUserWithSession();
        $step = $this->stepUpService();

        try {
            $step->confirm($user, $session, '000000');
            self::fail('wrong code accepted');
        } catch (TotpException $e) {
            self::assertSame(TotpException::INVALID_CODE, $e->reason);
        }
        self::assertNull($session->getStepUpAt());
        self::assertContains('step_up_failure', array_column($this->audits, 0));

        $code = $this->codeAtOffset($secret, 1);
        $step->confirm($user, $session, $code);
        $replay = new UserSession($user, AuthMethod::GOOGLE, null);
        try {
            $step->confirm($user, $replay, $code);
            self::fail('replay accepted');
        } catch (TotpException $e) {
            self::assertSame(TotpException::INVALID_CODE, $e->reason);
        }
        self::assertNull($replay->getStepUpAt(), 'Step-up gilt nur für die eigene Sitzung');
    }

    public function testStepUpWithoutActiveTotpIsRejected(): void
    {
        $user = $this->user(['ROLE_ORGANISATIONSCHEF']);
        $session = new UserSession($user, AuthMethod::PASSWORD, null);

        try {
            $this->stepUpService()->confirm($user, $session, '123456');
            self::fail('step-up without totp');
        } catch (TotpException $e) {
            self::assertSame(TotpException::NOT_ACTIVE, $e->reason);
        }
        self::assertNull($session->getStepUpAt());
        self::assertNull($session->getMfaVerifiedAt());
    }

    /**
     * @return array{User, UserSession, string, list<string>}
     */
    private function enrolledUserWithSession(): array
    {
        $user = $this->user(['ROLE_ORGANISATIONSCHEF']);
        $setup = $this->service->startEnrollment($user);
        $recovery = $this->service->confirmEnrollment($user, $this->code($setup['secret']));
        // Sitzung, die vor der TOTP-Aktivierung entstand: kein MFA-Nachweis
        $session = new UserSession($user, AuthMethod::PASSWORD, null);

        return [$user, $session, $setup['secret'], $recovery];
    }

    private function codeAtOffset(string $secret, int $offsetSteps): string
    {
        return TOTP::createFromSecret($secret)->at((intdiv(time(), 30) + $offsetSteps) * 30);
    }

    private function stepUpService(): StepUpService
    {
        $em = $this->createMock(EntityManager::class);
        $audit = $this->createMock(AuditLogger::class);
        $audit->method('log')->willReturnCallback(function (string $type, string $id, string $action, $actor, $target, $dept, array $changes): void {
            $this->audits[] = [$action, $changes];
        });

        return new StepUpService($this->service, $em, $audit);
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

    public function testDisableAndReEnrollmentRevokeAllTrustedDevices(): void
    {
        $revoked = [];
        $trusted = $this->createMock(\App\Service\Auth\TrustedDeviceService::class);
        $trusted->method('revokeAllForUser')->willReturnCallback(function ($user, string $reason) use (&$revoked): int {
            $revoked[] = $reason;

            return 1;
        });
        $service = $this->buildService($trusted);
        $user = $this->user();

        // Ersteinrichtung: noch nichts zu widerrufen
        $first = $service->startEnrollment($user);
        $codes = $service->confirmEnrollment($user, $this->code($first['secret']));
        self::assertSame([], $revoked);

        // Neueinrichtung (Reset)
        $second = $service->startEnrollment($user, $codes[0]);
        $service->confirmEnrollment($user, $this->codeAtOffset($second['secret'], 0));
        self::assertSame(['totp_changed'], $revoked);

        // Deaktivieren
        $newCodes = $this->all(UserRecoveryCode::class);
        self::assertCount(3, $newCodes);
        $service->disable($user, TOTP::createFromSecret($second['secret'])->at((intdiv(time(), 30) + 1) * 30));
        self::assertSame(['totp_changed', 'totp_changed'], $revoked);
    }

    private function buildService(?\App\Service\Auth\TrustedDeviceService $trusted = null): TotpService
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

        return new TotpService($em, $this->box, $checker, $audit, self::APP_SECRET, $trusted);
    }
}
