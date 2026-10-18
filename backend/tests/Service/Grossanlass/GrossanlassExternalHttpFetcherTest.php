<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassExternalSource as S;
use App\Service\Grossanlass\GrossanlassExternalFetchException;
use App\Service\Grossanlass\GrossanlassExternalHttpFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GrossanlassExternalHttpFetcherTest extends TestCase
{
    private static function source(string $auth = S::AUTH_BEARER): S
    {
        return (new S('xs0000000001', 'dept00000001'))->setBaseUrl('https://93.184.216.34/v1/')->setEndpointPath('/bookings')->setAuthType($auth)->setAuthHeader('X-Key')->setAuthUser('svc');
    }

    public function testBuildsUrlAndRejectsUnsafeAddresses(): void
    {
        self::assertSame('https://api.example.org/v1/bookings', GrossanlassExternalHttpFetcher::buildUrl('https://api.example.org/v1/', '/bookings'));
        foreach ([['http://api.example.org', ''], ['https://user:pw@api.example.org', ''], ['ftp://x.y', ''], ['https://api.example.org', 'https://evil.example/x'], ['https://api.example.org', '//evil.example']] as [$base, $path]) {
            try {
                GrossanlassExternalHttpFetcher::buildUrl($base, $path);
                self::fail("Erwartet: abgelehnt: $base $path");
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testSendsAuthenticationAndReturnsBody(): void
    {
        $seen = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen) {
            $seen = ['method' => $method, 'url' => $url, 'headers' => $options['headers']];

            return new MockResponse('{"ok":true}', ['http_code' => 200]);
        });
        $res = (new GrossanlassExternalHttpFetcher($client))->fetch(self::source(), 'TOPSECRET');

        self::assertSame(['status' => 200, 'body' => '{"ok":true}'], $res);
        self::assertSame('GET', $seen['method']);
        self::assertSame('https://93.184.216.34/v1/bookings', $seen['url']);
        self::assertContains('Authorization: Bearer TOPSECRET', $seen['headers']);

        (new GrossanlassExternalHttpFetcher($client))->fetch(self::source(S::AUTH_HEADER), 'K2');
        self::assertContains('X-Key: K2', $seen['headers']);
    }

    public function testErrorsAreDisplayableAndNeverContainTheSecret(): void
    {
        foreach ([[401, 'Authentifizierung'], [500, 'HTTP 500'], [302, 'Weiterleit']] as [$code, $needle]) {
            $client = new MockHttpClient(new MockResponse('secret-body TOPSECRET', ['http_code' => $code]));
            try {
                (new GrossanlassExternalHttpFetcher($client))->fetch(self::source(), 'TOPSECRET');
                self::fail('Erwartet: Fehler');
            } catch (GrossanlassExternalFetchException $e) {
                self::assertStringContainsString($needle, $e->getMessage());
                self::assertStringNotContainsString('TOPSECRET', $e->getMessage());
                self::assertSame($code, $e->httpStatus);
            }
        }
        $client = new MockHttpClient(static fn () => throw new \RuntimeException('connect failed with TOPSECRET'));
        try {
            (new GrossanlassExternalHttpFetcher($client))->fetch(self::source(), 'TOPSECRET');
            self::fail('Erwartet: Fehler');
        } catch (GrossanlassExternalFetchException $e) {
            self::assertStringNotContainsString('TOPSECRET', $e->getMessage());
        }
    }

    public function testPrivateNetworksAreBlocked(): void
    {
        foreach (['https://127.0.0.1', 'https://10.0.0.5', 'https://169.254.169.254', 'https://192.168.1.10'] as $base) {
            $client = new MockHttpClient(new MockResponse('{}'));
            try {
                (new GrossanlassExternalHttpFetcher($client))->fetch((new S('xs0000000001', 'dept00000001'))->setBaseUrl($base), null);
                self::fail("Erwartet: blockiert: $base");
            } catch (GrossanlassExternalFetchException) {
                self::assertTrue(true);
            }
        }
    }

    public function testRejectsOversizedResponses(): void
    {
        $client = new MockHttpClient(new MockResponse(str_repeat('a', GrossanlassExternalHttpFetcher::MAX_BYTES + 10)));
        $this->expectException(GrossanlassExternalFetchException::class);
        (new GrossanlassExternalHttpFetcher($client))->fetch(self::source(S::AUTH_NONE), null);
    }
}
