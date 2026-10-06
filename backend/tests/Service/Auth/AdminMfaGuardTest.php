<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\EventSubscriber\AdminMfaGuardSubscriber;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Auth\AdminMfaGuard;
use App\Service\Auth\AdminMfaPolicy;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\StepUpService;
use App\Service\Auth\TotpService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AdminMfaGuardTest extends TestCase
{
    private bool $totpEnabled = true;

    private AdminMfaGuard $guard;

    protected function setUp(): void
    {
        $this->totpEnabled = true;
        $totp = $this->createMock(TotpService::class);
        $totp->method('isEnabled')->willReturnCallback(fn (): bool => $this->totpEnabled);
        $checker = (new \ReflectionClass(AdminCapabilityChecker::class))->newInstanceWithoutConstructor();
        $this->guard = new AdminMfaGuard($checker, $totp);
    }

    public function testNormalUserIsNeverAffected(): void
    {
        $user = $this->user([]);
        $this->totpEnabled = false;

        self::assertNull($this->guard->denialReason($user, null, AdminMfaPolicy::LEVEL_STEP_UP));
        self::assertNull($this->guard->denialReason($user, $this->session($user), AdminMfaPolicy::LEVEL_ADMIN));
    }

    public function testAllGlobalAdminRolesWithoutTotpNeedSetup(): void
    {
        $this->totpEnabled = false;
        foreach (['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'] as $role) {
            $user = $this->user([$role]);
            self::assertSame(AdminMfaGuard::MFA_SETUP_REQUIRED, $this->guard->denialReason($user, $this->session($user), AdminMfaPolicy::LEVEL_ADMIN), $role);
        }
    }

    public function testTotpActiveButSessionWithoutMfaIsBlocked(): void
    {
        $user = $this->user(['ROLE_ORGANISATIONSCHEF']);

        self::assertSame(AdminMfaGuard::MFA_REQUIRED, $this->guard->denialReason($user, $this->session($user), AdminMfaPolicy::LEVEL_ADMIN));
        self::assertSame(AdminMfaGuard::MFA_REQUIRED, $this->guard->denialReason($user, null, AdminMfaPolicy::LEVEL_ADMIN), 'Legacy-JWT ohne Sitzung');
    }

    public function testMfaVerifiedSessionMayUseAdminFunctionsButNotStepUpActions(): void
    {
        $user = $this->user(['ROLE_SUBORGCHEF']);
        $session = $this->session($user, true);

        self::assertNull($this->guard->denialReason($user, $session, AdminMfaPolicy::LEVEL_ADMIN));
        self::assertSame(AdminMfaGuard::STEP_UP_REQUIRED, $this->guard->denialReason($user, $session, AdminMfaPolicy::LEVEL_STEP_UP));
    }

    public function testStepUpIsFreshForTenMinutesOnly(): void
    {
        self::assertSame(600, StepUpService::FRESHNESS_SECONDS);
        $user = $this->user(['ROLE_SUPERADMIN']);
        $session = $this->session($user, true);
        $session->markStepUp();

        self::assertNull($this->guard->denialReason($user, $session, AdminMfaPolicy::LEVEL_STEP_UP));
        self::assertNull($this->guard->denialReason($user, $session, AdminMfaPolicy::LEVEL_STEP_UP, new \DateTime('+599 seconds')));
        self::assertSame(AdminMfaGuard::STEP_UP_REQUIRED, $this->guard->denialReason($user, $session, AdminMfaPolicy::LEVEL_STEP_UP, new \DateTime('+601 seconds')));
    }

    public function testStepUpOfASessionWithoutMfaAlsoVerifiesMfa(): void
    {
        $user = $this->user(['ROLE_ORGANISATIONSCHEF']);
        $session = $this->session($user);
        self::assertNull($session->getMfaVerifiedAt());

        $session->markStepUp();

        self::assertNotNull($session->getMfaVerifiedAt());
        self::assertNull($this->guard->denialReason($user, $session, AdminMfaPolicy::LEVEL_STEP_UP));
    }

    public function testStepUpOfAnMfaSessionKeepsTheOriginalMfaTimestamp(): void
    {
        $user = $this->user(['ROLE_ORGANISATIONSCHEF']);
        $session = $this->session($user, true);
        $before = $session->getMfaVerifiedAt();

        $session->markStepUp();

        self::assertSame($before, $session->getMfaVerifiedAt());
    }

    public function testStepUpIsPerSessionAndNewSessionsStartClean(): void
    {
        $user = $this->user(['ROLE_ORGANISATIONSCHEF']);
        $first = $this->session($user, true);
        $first->markStepUp();
        $second = $this->session($user);

        self::assertNull($second->getStepUpAt());
        self::assertNull($second->getMfaVerifiedAt());
        self::assertSame(AdminMfaGuard::MFA_REQUIRED, $this->guard->denialReason($user, $second, AdminMfaPolicy::LEVEL_STEP_UP));
        // Dieselbe Sitzung (z. B. nach einem Token-Refresh) behält ihren Status.
        self::assertNull($this->guard->denialReason($user, $first, AdminMfaPolicy::LEVEL_STEP_UP));
    }

    public function testSubscriberAnswers403WithMachineReadableErrorAndLeavesOtherRoutesAlone(): void
    {
        $user = $this->user(['ROLE_SUPERADMIN']);
        $this->totpEnabled = false;
        $current = new CurrentAuthSession();
        $subscriber = $this->subscriber($user, $current);

        $blocked = $this->event('GET', '/api/admin/security-monitoring');
        $subscriber->onRequest($blocked);
        self::assertInstanceOf(JsonResponse::class, $blocked->getResponse());
        self::assertSame(403, $blocked->getResponse()->getStatusCode());
        self::assertSame('mfa_setup_required', json_decode((string) $blocked->getResponse()->getContent(), true)['error']);

        foreach (['/api/profiles/p1/security/totp', '/api/departments/d1/activities', '/api/auth/step-up'] as $path) {
            $allowed = $this->event('GET', $path);
            $subscriber->onRequest($allowed);
            self::assertNull($allowed->getResponse(), $path);
        }
    }

    public function testSubscriberRequiresStepUpForRoleChangesOnly(): void
    {
        $user = $this->user(['ROLE_SUPERADMIN']);
        $current = new CurrentAuthSession();
        $current->setAuthenticated($this->session($user, true));
        $subscriber = $this->subscriber($user, $current);

        $harmless = $this->event('PATCH', '/api/users/u1/admin', '{"nickname":"x"}');
        $subscriber->onRequest($harmless);
        self::assertNull($harmless->getResponse());

        $critical = $this->event('PATCH', '/api/users/u1/admin', '{"global_admin_role":"sub"}');
        $subscriber->onRequest($critical);
        self::assertSame('step_up_required', json_decode((string) $critical->getResponse()?->getContent(), true)['error']);

        $current->getAuthenticated()?->markStepUp();
        $again = $this->event('PATCH', '/api/users/u1/admin', '{"global_admin_role":"sub"}');
        $subscriber->onRequest($again);
        self::assertNull($again->getResponse());
    }

    private function subscriber(User $user, CurrentAuthSession $current): AdminMfaGuardSubscriber
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'api', ['ROLE_USER']));

        return new AdminMfaGuardSubscriber(new AdminMfaPolicy(), $this->guard, $storage, $current);
    }

    private function event(string $method, string $path, string $body = ''): RequestEvent
    {
        $request = Request::create($path, $method, [], [], [], [], $body === '' ? null : $body);

        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    /**
     * @param list<string> $roles
     */
    private function user(array $roles): User
    {
        static $n = 0;
        ++$n;
        $profile = new Profile();
        $profile->setId('gp' . $n);
        $profile->setEmail("g$n@x.test");
        $profile->setRoles($roles);
        $user = new User();
        $user->setId('gu' . $n);
        $user->setProfileId('gp' . $n);
        $user->setProfile($profile);

        return $user;
    }

    private function session(User $user, bool $mfa = false): UserSession
    {
        $session = new UserSession($user, AuthMethod::PASSWORD, null);
        if ($mfa) {
            $session->markMfaVerified();
        }

        return $session;
    }
}
