<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\MiDataOAuthState;
use PHPUnit\Framework\TestCase;

final class MiDataOAuthStateTest extends TestCase
{
    public function testRoundTripKeepsRedirectAndLinkIntentServerSigned(): void
    {
        $state = new MiDataOAuthState('test-secret');
        $issued = $state->issue('/account/security', 'user-123');

        self::assertNotSame('', $issued['token']);
        self::assertNotSame('', $issued['nonce']);
        self::assertSame(
            [
                'nonce' => $issued['nonce'],
                'code_verifier' => $issued['codeVerifier'],
                'redirect' => '/account/security',
                'link_user_id' => 'user-123',
                'session_id' => null,
                'profile_link' => false,
                'intent' => \App\Service\Auth\AuthIntent::LINK_IDENTITY === \App\Service\Auth\AuthIntent::LINK_IDENTITY ? \App\Service\Auth\AuthIntent::fromRedirect('/account/security') : null,
            ],
            $state->verify($issued['cookieValue'], $issued['token'])
        );
    }

    public function testRejectsTamperedCookieAndWrongState(): void
    {
        $state = new MiDataOAuthState('test-secret');
        $issued = $state->issue(null);

        self::assertNull($state->verify($issued['cookieValue'] . 'x', $issued['token']));
        self::assertNull($state->verify($issued['cookieValue'], 'attacker-state'));
    }

    public function testSanitizeRedirectAllowsOnlyInternalPaths(): void
    {
        $state = new MiDataOAuthState('test-secret');

        self::assertSame('/account/security', $state->sanitizeRedirect('/account/security'));
        self::assertNull($state->sanitizeRedirect('https://evil.example'));
        self::assertNull($state->sanitizeRedirect('//evil.example'));
        self::assertNull($state->sanitizeRedirect('/\\evil.example'));
    }

    public function testExtractsOnlyPendingAssignmentJoinIntent(): void
    {
        $state = new MiDataOAuthState('test-secret');

        self::assertSame(
            'AB12CD34',
            $state->extractDepartmentJoinCodeIntent('/pending-assignment?join_code=AB12CD34&auto_join=1'),
        );
        self::assertNull($state->extractDepartmentJoinCodeIntent('/dashboard?join_code=AB12CD34'));
        self::assertNull($state->extractDepartmentJoinCodeIntent('/pending-assignment?invite_id=invite-1&join_code=AB12CD34'));
        self::assertNull($state->extractDepartmentJoinCodeIntent('/pending-assignment?join_code[]=AB12CD34'));
        self::assertNull($state->extractDepartmentJoinCodeIntent('/pending-assignment'));
    }

    public function testExtractsOnlyWellFormedPendingAssignmentOnboardingIntent(): void
    {
        $state = new MiDataOAuthState('test-secret');

        self::assertSame(
            '0a1b2c3d4e5f',
            $state->extractDepartmentOnboardingIntent('/pending-assignment?midata_onboarding=0a1b2c3d4e5f'),
        );
        self::assertNull($state->extractDepartmentOnboardingIntent('/dashboard?midata_onboarding=0a1b2c3d4e5f'));
        self::assertNull($state->extractDepartmentOnboardingIntent('/pending-assignment?midata_onboarding[]=0a1b2c3d4e5f'));
        self::assertNull($state->extractDepartmentOnboardingIntent('/pending-assignment?midata_onboarding=51'));
        self::assertNull($state->extractDepartmentOnboardingIntent('/pending-assignment?midata_onboarding=0A1B2C3D4E5F%27'));
        self::assertNull($state->extractDepartmentOnboardingIntent('/pending-assignment'));
    }
}
