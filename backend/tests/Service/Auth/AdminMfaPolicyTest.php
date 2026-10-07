<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\AdminMfaPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AdminMfaPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string|null}>
     */
    public static function routes(): iterable
    {
        yield 'normal department function' => ['GET', '/api/departments/abc/activities', '', null];
        yield 'normal user profile' => ['PATCH', '/api/profiles/abc', '{"nickname":"x"}', null];
        yield 'TOTP setup stays reachable' => ['POST', '/api/profiles/abc/security/totp/enroll', '', null];
        yield 'step-up endpoint stays reachable' => ['POST', '/api/auth/step-up', '{"code":"1"}', null];
        yield 'public organisation list' => ['GET', '/api/organisations', '', null];
        yield 'public admin request creation' => ['POST', '/api/join-requests/admin-request', '{}', null];
        yield 'admin area' => ['GET', '/api/admin/security-monitoring', '', AdminMfaPolicy::LEVEL_ADMIN];
        yield 'admin user list' => ['GET', '/api/users/admin/list', '', AdminMfaPolicy::LEVEL_ADMIN];
        yield 'admin user detail' => ['GET', '/api/users/u1/admin-detail', '', AdminMfaPolicy::LEVEL_ADMIN];
        yield 'organisation edit' => ['PATCH', '/api/organisations/o1', '{}', AdminMfaPolicy::LEVEL_ADMIN];
        yield 'mail log' => ['GET', '/api/mail/send-log', '', AdminMfaPolicy::LEVEL_ADMIN];
        yield 'admin request decision' => ['PATCH', '/api/join-requests/admin-request/r1', '{}', AdminMfaPolicy::LEVEL_ADMIN];
        yield 'admin user: harmless edit is admin level only' => ['PATCH', '/api/users/u1/admin', '{"nickname":"x"}', AdminMfaPolicy::LEVEL_ADMIN];
        yield 'admin user: role change needs step-up' => ['PATCH', '/api/users/u1/admin', '{"global_admin_role":"sub"}', AdminMfaPolicy::LEVEL_STEP_UP];
        yield 'admin user: scope change needs step-up' => ['PATCH', '/api/users/u1/admin', '{"admin_capabilities":{}}', AdminMfaPolicy::LEVEL_STEP_UP];
        yield 'admin user: email change needs step-up' => ['PATCH', '/api/users/u1/admin', '{"email":"a@b.test"}', AdminMfaPolicy::LEVEL_STEP_UP];
        yield 'admin user: state change needs step-up' => ['PATCH', '/api/users/u1/admin', '{"state":"inactive"}', AdminMfaPolicy::LEVEL_STEP_UP];
        yield 'mail settings change needs step-up' => ['PATCH', '/api/mail/settings', '{}', AdminMfaPolicy::LEVEL_STEP_UP];
        yield 'integration change needs step-up' => ['PUT', '/api/admin/integrations/fcal', '{}', AdminMfaPolicy::LEVEL_STEP_UP];
        yield 'integration read is admin level' => ['GET', '/api/admin/integrations/fcal', '', AdminMfaPolicy::LEVEL_ADMIN];
        yield 'revoke other sessions needs self step-up' => ['POST', '/api/profiles/p1/security/sessions/revoke-others', '', AdminMfaPolicy::LEVEL_SELF_STEP_UP];
        yield 'listing sessions is a normal function' => ['GET', '/api/profiles/p1/security/sessions', '', null];
        yield 'revoking one session needs self step-up' => ['DELETE', '/api/profiles/p1/security/sessions/abc', '', AdminMfaPolicy::LEVEL_SELF_STEP_UP];
        yield 'revoking trust is normal' => ['DELETE', '/api/profiles/p1/security/trusted-devices/d1', '', null];
        yield 'reserved admin 2FA reset path' => ['POST', '/api/admin/security/users/u1/totp-reset', '', AdminMfaPolicy::LEVEL_STEP_UP];
    }

    #[DataProvider('routes')]
    public function testLevels(string $method, string $path, string $body, ?string $expected): void
    {
        $request = Request::create($path, $method, [], [], [], [], $body === '' ? null : $body);

        self::assertSame($expected, (new AdminMfaPolicy())->levelFor($request));
    }
}
