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
}
