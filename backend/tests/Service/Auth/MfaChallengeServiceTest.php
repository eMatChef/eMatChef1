<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\MfaChallenge;
use App\Entity\User;
use App\Enum\AuthMethod;
use App\Service\Auth\MfaChallengeService;
use App\Service\Auth\MfaException;
use App\Service\Auth\TotpException;
use App\Service\Auth\TotpService;
use App\Service\Auth\TrustedDevicePolicy;
use App\Service\Auth\TrustedDeviceService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class MfaChallengeServiceTest extends TestCase
{
    /** @var list<MfaChallenge> */
    private array $challenges = [];

    private bool $totpEnabled = true;

    private bool $codeValid = true;

    private bool $locked = false;

    private int $verifyCalls = 0;

    private ?\App\Entity\TrustedDevice $trustedDevice = null;

    private MfaChallengeService $service;

    protected function setUp(): void
    {
        $this->challenges = [];
        $this->totpEnabled = true;
        $this->codeValid = true;
        $this->locked = false;
        $this->verifyCalls = 0;
        $this->trustedDevice = null;

        $totp = $this->createMock(TotpService::class);
        $totp->method('isEnabled')->willReturnCallback(fn (): bool => $this->totpEnabled);
        $totp->method('verifySecondFactor')->willReturnCallback(function (): bool {
            ++$this->verifyCalls;
            if ($this->locked) {
                throw new TotpException(TotpException::LOCKED, 'Zu viele Fehlversuche.');
            }

            return $this->codeValid;
        });

        $em = $this->createMock(EntityManager::class);
        $em->method('getRepository')->willReturnCallback(function (): EntityRepository {
            $repo = $this->createMock(EntityRepository::class);
            $repo->method('findOneBy')->willReturnCallback(function (array $c): ?MfaChallenge {
                foreach ($this->challenges as $ch) {
                    if (isset($c['tokenHash']) && (new \ReflectionProperty($ch, 'tokenHash'))->getValue($ch) === $c['tokenHash']) {
                        return $ch;
                    }
                }

                return null;
            });
            $repo->method('findBy')->willReturn([]);

            return $repo;
        });
        $em->method('persist')->willReturnCallback(function (object $o): void {
            $this->challenges[] = $o;
        });
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $f) => $f());

        $trusted = $this->createMock(TrustedDeviceService::class);
        $trusted->method('authenticate')->willReturnCallback(fn () => $this->trustedDevice);
        $policy = $this->createMock(TrustedDevicePolicy::class);
        $policy->method('daysFor')->willReturn(90);

        $this->service = new MfaChallengeService($em, $totp, $trusted, $policy);
    }

    public function testNoChallengeWithoutActiveTotpOrWithConfirmedProviderMfa(): void
    {
        $this->totpEnabled = false;
        self::assertNull($this->service->issueIfRequired($this->user(), AuthMethod::PASSWORD));
        self::assertSame([], $this->challenges);

        $this->totpEnabled = true;
        self::assertNull($this->service->issueIfRequired($this->user(), AuthMethod::MIDATA, true));
        self::assertSame([], $this->challenges);
    }

    public function testTrustedDeviceSkipsTheChallengeOnlyWhenARequestIsGiven(): void
    {
        $this->trustedDevice = new \App\Entity\TrustedDevice('d1', $this->user(), str_repeat('a', 64), 'Chrome', new \DateTime('+1 day'));
        $user = $this->user();

        self::assertNull($this->service->issueIfRequired($user, AuthMethod::PASSWORD, false, new \Symfony\Component\HttpFoundation\Request()));
        self::assertSame([], $this->challenges);
        // ohne Request (kein Cookie lesbar) bleibt die Challenge Pflicht
        self::assertNotNull($this->service->issueIfRequired($user, AuthMethod::PASSWORD));
    }

    public function testNoTrustedDeviceMeansChallengeWithTrustDays(): void
    {
        $challenge = $this->service->issueIfRequired($this->user(), AuthMethod::GOOGLE, false, new \Symfony\Component\HttpFoundation\Request());

        self::assertSame(90, $challenge['trust_days']);
        self::assertCount(1, $this->challenges);
    }

    public function testChallengeIsRandomShortLivedAndStoredHashedOnly(): void
    {
        $a = $this->service->issueIfRequired($this->user(), AuthMethod::GOOGLE);
        $b = $this->service->issueIfRequired($this->user(), AuthMethod::GOOGLE);

        self::assertTrue($a['mfa_required']);
        self::assertSame(['totp', 'recovery_code'], $a['methods']);
        self::assertSame(300, $a['expires_in']);
        self::assertNotSame($a['challenge'], $b['challenge']);
        self::assertSame(64, \strlen($a['challenge']));
        $stored = (new \ReflectionProperty($this->challenges[0], 'tokenHash'))->getValue($this->challenges[0]);
        self::assertSame(hash('sha256', $a['challenge']), $stored);
        self::assertNotSame($a['challenge'], $stored);
        $seconds = $this->challenges[0]->getExpiresAt()->getTimestamp() - time();
        self::assertGreaterThan(290, $seconds);
        self::assertLessThanOrEqual(300, $seconds);
    }

    public function testValidTotpVerifiesOnceAndKeepsTheLoginMethod(): void
    {
        $user = $this->user();
        $issued = $this->service->issueIfRequired($user, AuthMethod::MIDATA);

        $challenge = $this->service->verify($issued['challenge'], 'totp', '123456');

        self::assertSame($user, $challenge->getUser());
        self::assertSame(AuthMethod::MIDATA, $challenge->getAuthMethod());
        $this->expectMfa(MfaException::INVALID_CHALLENGE, fn () => $this->service->verify($issued['challenge'], 'totp', '123456'));
    }

    public function testRecoveryCodeMethod(): void
    {
        $issued = $this->service->issueIfRequired($this->user(), AuthMethod::PASSWORD);

        $this->service->verify($issued['challenge'], 'recovery_code', 'ABCDE-FGHJK');

        self::assertSame(1, $this->verifyCalls);
    }

    public function testMethodMustMatchCodeShape(): void
    {
        $issued = $this->service->issueIfRequired($this->user(), AuthMethod::PASSWORD);

        $this->expectMfa(MfaException::INVALID_CODE, fn () => $this->service->verify($issued['challenge'], 'recovery_code', '123456'));
        $this->expectMfa(MfaException::INVALID_CODE, fn () => $this->service->verify($issued['challenge'], 'totp', 'ABCDE-FGHJK'));
        $this->expectMfa(MfaException::INVALID_CODE, fn () => $this->service->verify($issued['challenge'], 'sms', '123456'));
        self::assertSame(0, $this->verifyCalls);
    }

    public function testUnknownEmptyAndExpiredChallengesAreRejected(): void
    {
        $issued = $this->service->issueIfRequired($this->user(), AuthMethod::PASSWORD);
        (new \ReflectionProperty($this->challenges[0], 'expiresAt'))->setValue($this->challenges[0], new \DateTime('-1 second'));

        $this->expectMfa(MfaException::INVALID_CHALLENGE, fn () => $this->service->verify($issued['challenge'], 'totp', '123456'));
        $this->expectMfa(MfaException::INVALID_CHALLENGE, fn () => $this->service->verify('nope', 'totp', '123456'));
        $this->expectMfa(MfaException::INVALID_CHALLENGE, fn () => $this->service->verify('', 'totp', '123456'));
        self::assertSame(0, $this->verifyCalls);
    }

    public function testWrongCodeKeepsChallengeUntilFiveFailures(): void
    {
        $issued = $this->service->issueIfRequired($this->user(), AuthMethod::PASSWORD);
        $this->codeValid = false;

        for ($i = 0; $i < 5; ++$i) {
            $this->expectMfa(MfaException::INVALID_CODE, fn () => $this->service->verify($issued['challenge'], 'totp', '000000'));
        }
        // nach 5 Fehlversuchen ist die Challenge tot, auch mit richtigem Code
        $this->codeValid = true;
        $this->expectMfa(MfaException::INVALID_CHALLENGE, fn () => $this->service->verify($issued['challenge'], 'totp', '123456'));
    }

    public function testLockedTotpIsReportedAndDoesNotConsumeTheChallenge(): void
    {
        $issued = $this->service->issueIfRequired($this->user(), AuthMethod::PASSWORD);
        $this->locked = true;
        $this->expectMfa(MfaException::LOCKED, fn () => $this->service->verify($issued['challenge'], 'totp', '123456'));

        $this->locked = false;
        self::assertInstanceOf(MfaChallenge::class, $this->service->verify($issued['challenge'], 'totp', '123456'));
    }

    private function expectMfa(string $reason, callable $call): void
    {
        try {
            $call();
            self::fail('Expected MfaException ' . $reason);
        } catch (MfaException $e) {
            self::assertSame($reason, $e->reason);
        }
    }

    private function user(): User
    {
        static $n = 0;
        ++$n;
        $user = new User();
        $user->setId('mfau' . $n);

        return $user;
    }
}
