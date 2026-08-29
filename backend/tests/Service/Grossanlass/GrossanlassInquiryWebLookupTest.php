<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassInquiryWebLookup;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GrossanlassInquiryWebLookupTest extends TestCase
{
    public function testUrlsFromSearchHtmlDecodesDuckDuckGoLinks(): void
    {
        $lookup = new GrossanlassInquiryWebLookup(new MockHttpClient());
        $html = '<a class="result__url" href="//duckduckgo.com/l/?uddg=https%3A%2F%2Facme-schrauben.ch%2Fkontakt&rut=1">x</a>'
            .'<a class="result__a" href="https://acme-schrauben.ch/">Acme</a>'
            .'<a class="result__a" href="https://facebook.com/acme">skip</a>'
            .'<a class="result__a" href="https://prod.zip.ch/acme">skip dir</a>';

        $urls = $lookup->urlsFromSearchHtml($html);
        self::assertContains('https://acme-schrauben.ch/kontakt', $urls);
        self::assertContains('https://acme-schrauben.ch/', $urls);
        self::assertContains('https://prod.zip.ch/acme', $urls);
        self::assertNotContains('https://facebook.com/acme', $urls);
    }

    public function testExtractEmailsKeepsCompanyAddressAndDropsJunk(): void
    {
        $lookup = new GrossanlassInquiryWebLookup(new MockHttpClient());
        $html = '<a href="mailto:info@acme-schrauben.ch?subject=Hi">Mail</a>'
            .' <p>noreply@acme-schrauben.ch privacy@acme-schrauben.ch user@example.com info@help.ch</p>'
            .' <img src="https://cdn.example.com/foo@bar.png">';

        $hits = $lookup->extractEmails($html, 'https://acme-schrauben.ch/kontakt');
        $values = array_column($hits, 'value');

        self::assertContains('info@acme-schrauben.ch', $values);
        self::assertNotContains('noreply@acme-schrauben.ch', $values);
        self::assertNotContains('user@example.com', $values);
        self::assertNotContains('info@help.ch', $values);
    }

    public function testExtractPhonesNormalizesSwissNumbers(): void
    {
        $lookup = new GrossanlassInquiryWebLookup(new MockHttpClient());
        $html = '<p>Tel. 044 111 22 33</p><a href="tel:+41442223344">anrufen</a>';

        $values = array_column($lookup->extractPhones($html, 'https://acme.ch'), 'value');

        self::assertContains('+41441112233', $values);
        self::assertContains('+41442223344', $values);
    }

    public function testLookupUsesSearchThenSiteAndContactPage(): void
    {
        $client = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'duckduckgo')) {
                self::assertSame('POST', $method);

                return new MockResponse('<a class="result__a" href="https://acme-schrauben.ch/">Acme</a>');
            }
            if (str_contains($url, '/kontakt')) {
                return new MockResponse(
                    '<a href="mailto:verkauf@acme-schrauben.ch">Mail</a> Tel. 044 111 22 33',
                );
            }

            return new MockResponse(
                '<a href="/kontakt">Kontakt</a> <a href="mailto:info@acme-schrauben.ch">x</a>',
            );
        });
        $lookup = new GrossanlassInquiryWebLookup($client);
        $result = $lookup->lookup('Acme Schrauben', 'Bülach');

        self::assertSame('https://acme-schrauben.ch', $result['website']);
        self::assertSame('info@acme-schrauben.ch', $result['emails'][0]['value'] ?? null);
        self::assertSame('verkauf@acme-schrauben.ch', $result['emails'][1]['value'] ?? null);
        self::assertSame('+41441112233', $result['phones'][0]['value'] ?? null);
        self::assertStringContainsString('Acme Schrauben', $result['query']);
        self::assertStringContainsString('google.ch', $result['search_url']);
    }

    public function testLookupIgnoresDirectorySiteAsWebsiteAndEmail(): void
    {
        $client = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'duckduckgo')) {
                return new MockResponse('<a class="result__a" href="https://www.help.ch/firma">Help</a>');
            }

            return new MockResponse(
                '<a href="mailto:info@help.ch">x</a> Tel. 044 111 22 33 <a href="mailto:garten@alispach.ch">y</a>',
            );
        });
        $lookup = new GrossanlassInquiryWebLookup($client);
        $result = $lookup->lookup('Alispach Gartenservice', 'Hemmiken');

        self::assertNull($result['website']);
        self::assertSame('garten@alispach.ch', $result['emails'][0]['value'] ?? null);
        self::assertSame('+41441112233', $result['phones'][0]['value'] ?? null);
    }
}
