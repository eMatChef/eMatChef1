<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GmailOAuthState;
use PHPUnit\Framework\TestCase;

class GmailOAuthStateTest extends TestCase
{
    public function testVerifyUsesSignedStateWithoutCookie(): void
    {
        $state = new GmailOAuthState('test-secret');
        $issued = $state->issue('dept12abcdef', 'user12abcdef');

        $verified = $state->verify('', $issued['token']);

        self::assertSame('dept12abcdef', $verified['departmentId'] ?? null);
        self::assertSame('user12abcdef', $verified['userId'] ?? null);
    }

    public function testRejectsTamperedState(): void
    {
        $state = new GmailOAuthState('test-secret');
        $issued = $state->issue('dept12abcdef', 'user12abcdef');

        self::assertNull($state->verify('', $issued['token'] . 'x'));
    }
}
