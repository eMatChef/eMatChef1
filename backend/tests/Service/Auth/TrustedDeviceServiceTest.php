<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Profile;
use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\AuditLogger;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\TrustedDevicePolicy;
use App\Service\Auth\TrustedDeviceService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class TrustedDeviceServiceTest extends TestCase
{
    /** @var list<TrustedDevice> */
    private array $devices = [];

    /** @var list<string> */
    private array $audits = [];

    private CurrentAuthSession $current;

    private TrustedDeviceService $service;

    protected function setUp(): void
    {
        $this->devices = [];
        $this->audits = [];
        $this->current = new CurrentAuthSession();

        $em = $this->createMock(EntityManager::class);
        $em->method('getRepository')->willReturnCallback(function (): EntityRepository {
            $repo = $this->createMock(EntityRepository::class);
            $repo->method('findOneBy')->willReturnCallback(function (array $c): ?TrustedDevice {
                foreach ($this->devices as $d) {
                    if (isset($c['credentialHash']) && (new \ReflectionProperty($d, 'credentialHash'))->getValue($d) === $c['credentialHash']) {
                        return $d;
                    }
                }

                return null;
            });
            $repo->method('find')->willReturnCallback(function (string $id): ?TrustedDevice {
                foreach ($this->devices as $d) {
                    if ($d->getId() === $id) {
                        return $d;
                    }
                }

                return null;
            });
            $repo->method('findBy')->willReturnCallback(function (array $c): array {
                return array_values(array_filter($this->devices, static function (TrustedDevice $d) use ($c): bool {
                    if (isset($c['user']) && $d->getUser() !== $c['user']) {
                        return false;
                    }
                    if (\array_key_exists('revokedAt', $c) && $c['revokedAt'] === null && $d->isRevoked()) {
                        return false;
                    }

                    return true;
                }));
            });

            return $repo;
        });
        $em->method('persist')->willReturnCallback(function (object $o): void {
            $this->devices[] = $o;
        });

        $audit = $this->createMock(AuditLogger::class);
        $audit->method('log')->willReturnCallback(function (string $type, string $id, string $action): void {
            $this->audits[] = $action;
        });

        $checker = (new \ReflectionClass(AdminCapabilityChecker::class))->newInstanceWithoutConstructor();
        $this->service = new TrustedDeviceService($em, new TrustedDevicePolicy($checker), $audit, $this->current, '.example.test', true);
    }

    public function testGrantCreatesHashedCredentialWithSecureHttpOnlyCookie(): void
    {
        $user = $this->user([]);
        ['device' => $device, 'cookie' => $cookie] = $this->service->grant($user, $this->request(null, 'Mozilla/5.0 (Windows NT 10.0) Chrome/120'));

        self::assertSame($user, $device->getUser());
        self::assertSame('Chrome / Windows', $device->getLabel());
        $hash = (new \ReflectionProperty($device, 'credentialHash'))->getValue($device);
        self::assertSame(hash('sha256', $cookie->getValue()), $hash);
        self::assertNotSame($cookie->getValue(), $hash);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame('/api/auth', $cookie->getPath());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertSame('.example.test', $cookie->getDomain());
        self::assertSame(['trusted_device_created'], $this->audits);
    }

    public function testNormalUserGets90DaysAndAdminGets30(): void
    {
        $normal = $this->service->grant($this->user([]), $this->request())['device'];
        $admin = $this->service->grant($this->user(['ROLE_ORGANISATIONSCHEF']), $this->request())['device'];

        self::assertEqualsWithDelta(time() + 90 * 86400, $normal->getExpiresAt()->getTimestamp(), 5);
        self::assertEqualsWithDelta(time() + 30 * 86400, $admin->getExpiresAt()->getTimestamp(), 5);
        foreach (['ROLE_SUPERADMIN', 'ROLE_SUBORGCHEF'] as $role) {
            $d = $this->service->grant($this->user([$role]), $this->request())['device'];
            self::assertEqualsWithDelta(time() + 30 * 86400, $d->getExpiresAt()->getTimestamp(), 5, $role);
        }
    }

    public function testUserWhoBecomesAdminGetsTheShorterWindowFromGrantTime(): void
    {
        $user = $this->user([]);
        ['device' => $device, 'cookie' => $cookie] = $this->service->grant($user, $this->request());
        $this->backdateTrust($device, 40);

        self::assertNotNull($this->service->authenticate($user, $this->request($cookie->getValue())), 'als normaler User noch gültig (40 < 90 Tage)');

        $user->getProfile()->setRoles(['ROLE_ORGANISATIONSCHEF']);

        self::assertNull($this->service->authenticate($user, $this->request($cookie->getValue())), 'als Admin nach 30 Tagen abgelaufen');
        self::assertSame([], $this->service->listActive($user));
    }

    public function testAuthenticateAcceptsOnlyTheOwnersValidCredentialAndRecordsUse(): void
    {
        $user = $this->user([]);
        ['cookie' => $cookie] = $this->service->grant($user, $this->request());

        self::assertNull($this->service->authenticate($user, $this->request()), 'ohne Cookie');
        self::assertNull($this->service->authenticate($user, $this->request('nonsense')), 'ungültiges Format');
        self::assertNull($this->service->authenticate($user, $this->request(str_repeat('a', 64))), 'unbekanntes Credential');
        self::assertNull($this->service->authenticate($this->user([]), $this->request($cookie->getValue())), 'Credential eines anderen Users');
        self::assertNull($this->current->getPendingTrustedDevice());

        $device = $this->service->authenticate($user, $this->request($cookie->getValue()));

        self::assertNotNull($device);
        self::assertNotNull($device->getLastUsedAt());
        self::assertSame($device, $this->current->getPendingTrustedDevice());
        self::assertContains('trusted_device_used', $this->audits);
    }

    public function testRevokedAndExpiredDevicesAreRejected(): void
    {
        $user = $this->user([]);
        ['device' => $revoked, 'cookie' => $c1] = $this->service->grant($user, $this->request());
        $revoked->revoke(TrustedDeviceService::REASON_USER);
        ['device' => $expired, 'cookie' => $c2] = $this->service->grant($user, $this->request());
        $this->backdateTrust($expired, 91);

        self::assertNull($this->service->authenticate($user, $this->request($c1->getValue())));
        self::assertNull($this->service->authenticate($user, $this->request($c2->getValue())));
    }

    public function testGrantOnAlreadyTrustedBrowserRenewsAndRotatesTheCredential(): void
    {
        $user = $this->user([]);
        ['cookie' => $old] = $this->service->grant($user, $this->request());

        ['device' => $device, 'cookie' => $new] = $this->service->grant($user, $this->request($old->getValue()));

        self::assertCount(1, $this->devices);
        self::assertNotSame($old->getValue(), $new->getValue());
        self::assertNull($this->service->authenticate($user, $this->request($old->getValue())), 'altes Credential ungültig');
        self::assertSame($device, $this->service->authenticate($user, $this->request($new->getValue())));
    }

    public function testRevokeWorksOnlyForTheOwner(): void
    {
        $owner = $this->user([]);
        $other = $this->user([]);
        $device = $this->service->grant($owner, $this->request())['device'];

        self::assertFalse($this->service->revoke($other, $device->getId()));
        self::assertFalse($device->isRevoked());
        self::assertTrue($this->service->revoke($owner, $device->getId()));
        self::assertTrue($device->isRevoked());
        self::assertFalse($this->service->revoke($owner, $device->getId()), 'bereits widerrufen');
        self::assertContains('trusted_device_revoked', $this->audits);
        self::assertSame([], $this->service->listActive($owner));
    }

    public function testRevokeAllForUserTouchesOnlyThatUser(): void
    {
        $a = $this->user([]);
        $b = $this->user([]);
        $this->service->grant($a, $this->request());
        $this->service->grant($a, $this->request());
        $keep = $this->service->grant($b, $this->request())['device'];

        self::assertSame(2, $this->service->revokeAllForUser($a, TrustedDeviceService::REASON_TOTP_CHANGED));

        self::assertSame([], $this->service->listActive($a));
        self::assertFalse($keep->isRevoked());
        self::assertSame(0, $this->service->revokeAllForUser($a, TrustedDeviceService::REASON_PASSWORD));
    }

    private function backdateTrust(TrustedDevice $device, int $daysAgo): void
    {
        (new \ReflectionProperty($device, 'trustedAt'))->setValue($device, new \DateTime("-$daysAgo days"));
    }

    private function request(?string $credential = null, ?string $userAgent = null): Request
    {
        $request = Request::create('/api/auth/login_check', 'POST');
        if ($credential !== null) {
            $request->cookies->set(TrustedDeviceService::COOKIE_NAME, $credential);
        }
        if ($userAgent !== null) {
            $request->headers->set('User-Agent', $userAgent);
        }

        return $request;
    }

    /**
     * @param list<string> $roles
     */
    private function user(array $roles): User
    {
        static $n = 0;
        ++$n;
        $profile = new Profile();
        $profile->setId('tp' . $n);
        $profile->setEmail("t$n@x.test");
        $profile->setRoles($roles);
        $user = new User();
        $user->setId('tu' . $n);
        $user->setProfileId('tp' . $n);
        $user->setProfile($profile);

        return $user;
    }
}
