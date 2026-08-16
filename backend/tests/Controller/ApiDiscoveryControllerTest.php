<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ApiDiscoveryController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ApiDiscoveryControllerTest extends TestCase
{
    public function testHtmlLandingContainsFormatsAndLinks(): void
    {
        $controller = new ApiDiscoveryController('https://app.ematchef.ch', 'https://ematchef.ch');
        $response = $controller->root(Request::create('https://api.ematchef.ch/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $html = (string) $response->getContent();
        self::assertStringContainsString('eMatChef API', $html);
        self::assertStringContainsString('Available formats', $html);
        self::assertStringContainsString('https://ematchef.ch/', $html);
        self::assertStringContainsString('https://app.ematchef.ch/', $html);
        self::assertStringContainsString('/api/health', $html);
        self::assertStringContainsString('noindex', $html);
    }

    public function testJsonFormatQuery(): void
    {
        $controller = new ApiDiscoveryController('https://app.ematchef.ch', 'https://ematchef.ch');
        $response = $controller->root(Request::create('https://api.ematchef.ch/', 'GET', ['format' => 'json']));

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('eMatChef API', $data['name']);
        self::assertSame('https://ematchef.ch/', $data['_links']['website']['href']);
        self::assertSame('https://api.ematchef.ch/api/health', $data['_links']['health']['href']);
    }

    public function testJsonAcceptHeader(): void
    {
        $controller = new ApiDiscoveryController('https://app.ematchef.ch/', 'https://ematchef.ch/');
        $request = Request::create('https://api.ematchef.ch/', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $controller->root($request);

        self::assertStringContainsString('json', (string) $response->headers->get('Content-Type'));
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('eMatChef API', $data['name'] ?? null);
    }
}
