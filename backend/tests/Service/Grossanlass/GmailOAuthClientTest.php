<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GmailOAuthClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GmailOAuthClientTest extends TestCase
{
    public function testLocalTestHostUsesLoopbackRedirect(): void
    {
        $client = new GmailOAuthClient(
            $this->createMock(HttpClientInterface::class),
            'https://app.ematchef.test',
            'id',
            'secret',
            '',
            '',
        );

        self::assertSame(
            'http://127.0.0.1:8081/api/auth/google/gmail/callback',
            $client->getRedirectUri(),
        );
    }

    public function testPublicHostKeepsFrontendCallback(): void
    {
        $client = new GmailOAuthClient(
            $this->createMock(HttpClientInterface::class),
            'https://dev.ematchef.ch',
            'id',
            'secret',
            '',
            '',
        );

        self::assertSame(
            'https://dev.ematchef.ch/api/auth/google/gmail/callback',
            $client->getRedirectUri(),
        );
    }
}
