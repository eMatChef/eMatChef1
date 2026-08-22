<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\GoogleOAuthClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleOAuthClientTest extends TestCase
{
    public function testAuthorizationUrlContainsRequiredParams(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $client = new GoogleOAuthClient(
            $http,
            'https://app.ematchef.test',
            'google-client-id',
            'google-client-secret',
            '',
        );

        $url = $client->buildAuthorizationUrl('nonce-1');
        self::assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $url);
        self::assertStringContainsString('client_id=google-client-id', $url);
        self::assertStringContainsString(urlencode('https://app.ematchef.test/api/auth/google/callback'), $url);
        self::assertStringContainsString('state=nonce-1', $url);
        self::assertStringContainsString('scope=', $url);
    }

    public function testIsConfiguredRequiresSecrets(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $client = new GoogleOAuthClient($http, 'https://app.ematchef.test', '', '', '');
        self::assertFalse($client->isConfigured());
    }
}
