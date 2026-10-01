<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\Mailbox\GrossanlassMailboxRegistry;
use App\Service\Grossanlass\Mailbox\OutlookOAuthClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GrossanlassMailboxRegistryTest extends TestCase
{
    public function testOutlookRedirectUsesLoopbackOnTestHost(): void
    {
        $client = new OutlookOAuthClient(
            $this->createMock(HttpClientInterface::class),
            'https://app.ematchef.test',
            '',
            '',
            'common',
            '',
        );

        self::assertFalse($client->isConfigured());
        self::assertSame(
            'http://127.0.0.1:8081/api/auth/microsoft/outlook/callback',
            $client->getRedirectUri(),
        );
    }

    public function testRegistryListsEveryProvider(): void
    {
        $gmail = $this->createStub(\App\Service\Grossanlass\Mailbox\GrossanlassMailboxProvider::class);
        $gmail->method('id')->willReturn('gmail');
        $gmail->method('label')->willReturn('Gmail');
        $gmail->method('isConfigured')->willReturn(true);
        $gmail->method('redirectUri')->willReturn('https://example.test/gmail');
        $gmail->method('capabilities')->willReturn(['drafts' => true, 'inbox' => true, 'folders' => true]);

        $outlook = $this->createStub(\App\Service\Grossanlass\Mailbox\GrossanlassMailboxProvider::class);
        $outlook->method('id')->willReturn('outlook');
        $outlook->method('label')->willReturn('Outlook 365');
        $outlook->method('isConfigured')->willReturn(false);
        $outlook->method('redirectUri')->willReturn('http://127.0.0.1:8081/api/auth/microsoft/outlook/callback');
        $outlook->method('capabilities')->willReturn(['drafts' => true, 'inbox' => true, 'folders' => false]);

        $registry = new GrossanlassMailboxRegistry([$gmail, $outlook]);
        $ids = array_map(static fn (array $row): string => $row['id'], $registry->catalog());

        self::assertSame(['gmail', 'outlook'], $ids);
        self::assertSame('Gmail', $registry->get('gmail')->label());
        self::assertFalse($registry->get('outlook')->isConfigured());
    }
}
