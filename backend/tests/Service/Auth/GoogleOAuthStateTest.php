<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\GoogleOAuthState;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthStateTest extends TestCase
{
    public function testIssueAndVerifyRoundtrip(): void
    {
        $state = new GoogleOAuthState('test-secret');
        $issued = $state->issue('/pending-assignment');

        self::assertNotSame('', $issued['token']);
        self::assertSame('/pending-assignment', $state->verify($issued['cookieValue'], $issued['token']));
    }

    public function testRejectsTamperedCookie(): void
    {
        $state = new GoogleOAuthState('test-secret');
        $issued = $state->issue(null);
        $tampered = $issued['cookieValue'] . 'x';

        self::assertNull($state->verify($tampered, $issued['token']));
    }

    public function testRejectsWrongReturnedState(): void
    {
        $state = new GoogleOAuthState('test-secret');
        $issued = $state->issue(null);

        self::assertNull($state->verify($issued['cookieValue'], 'other-nonce'));
    }

    public function testSanitizeRedirectAllowsInternalPath(): void
    {
        $state = new GoogleOAuthState('secret');
        self::assertSame('/dccffd078d5c/join', $state->sanitizeRedirect('/dccffd078d5c/join'));
        self::assertNull($state->sanitizeRedirect('https://evil.example/phish'));
        self::assertNull($state->sanitizeRedirect('//evil.example'));
        self::assertNull($state->sanitizeRedirect('javascript:alert(1)'));
    }

    public function testLinkStateBindsUserSessionNonceAndVerifier(): void
    {
        $state = new GoogleOAuthState('test-secret');
        $issued = $state->issue('/dashboard', 'user00000001', 'sid-1');

        $verified = $state->verifyDetailed($issued['cookieValue'], $issued['token']);

        self::assertSame('user00000001', $verified['link_user_id']);
        self::assertSame('sid-1', $verified['session_id']);
        self::assertSame($issued['nonce'], $verified['nonce']);
        self::assertSame($issued['codeVerifier'], $verified['code_verifier']);
        self::assertNotSame($issued['token'], $issued['nonce']);
        self::assertNull($state->verifyDetailed($issued['cookieValue'] . 'x', $issued['token']));
        self::assertNull($state->verifyDetailed($issued['cookieValue'], 'other'));
    }

    public function testLoginStateHasNoLinkBinding(): void
    {
        $state = new GoogleOAuthState('test-secret');
        $issued = $state->issue(null);

        $verified = $state->verifyDetailed($issued['cookieValue'], $issued['token']);

        self::assertNull($verified['link_user_id']);
        self::assertNull($verified['session_id']);
    }
}
