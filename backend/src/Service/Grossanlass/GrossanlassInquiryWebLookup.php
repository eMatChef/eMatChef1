<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Öffentliche Kontaktdaten (Webseite, E-Mail, Telefon) zu einer Firma suchen.
 * Treffer nur vorschlagen — Übernehmen bleibt in der UI.
 */
final class GrossanlassInquiryWebLookup
{
    private const JUNK_HOSTS = [
        'duckduckgo.com', 'bing.com', 'google.com', 'google.ch', 'facebook.com',
        'instagram.com', 'linkedin.com', 'twitter.com', 'x.com', 'youtube.com',
        'wikipedia.org', 'kununu.com', 'glassdoor.com', 'dnb.com', 'crunchbase.com',
        'zoominfo.com',
    ];

    /** Branchenbücher: Telefon ok, nicht als Firmen-Webseite/E-Mail-Domain. */
    private const DIRECTORY_HOSTS = [
        'moneyhouse.ch', 'local.ch', 'search.ch', 'tel.search.ch', 'directories.ch',
        'compass.ch', 'zip.ch', 'zefix.ch', 'uid.admin.ch', 'cylex.ch', 'cylex.de',
        'wlw.ch', 'wlw.de', 'gelbeseiten.de', '118.ch', 'swissfirms.ch',
        'northdata.de', 'northdata.com', 'homegate.ch', 'comparis.ch',
        'firmeneintrag.ch', 'help.ch', 'infobel.ch', 'gelbeseiten.ch',
        'map.search.ch',
    ];

    private const JUNK_EMAIL_DOMAINS = [
        'example.com', 'example.org', 'sentry.io', 'wixpress.com', 'wix.com',
        'cloudflare.com', 'google.com', 'gstatic.com', 'schema.org', 'w3.org',
        'facebook.com', 'instagram.com', 'linkedin.com', 'twitter.com', 'x.com',
        'youtube.com', 'gravatar.com', 'wordpress.com', 'squarespace.com',
        'sentry-next.wixpress.com',
    ];

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{
     *   query: string,
     *   search_url: string,
     *   website: string|null,
     *   emails: list<array{value: string, source: string}>,
     *   phones: list<array{value: string, source: string}>
     * }
     */
    public function lookup(string $name, string $place = '', string $website = ''): array
    {
        $name = trim($name);
        $place = trim($place);
        $website = $this->normalizeUrl($website);
        $query = trim($name.' '.$place.' Kontakt E-Mail');
        $searchUrl = 'https://www.google.ch/search?hl=de&q='.rawurlencode($query);

        $urls = [];
        if ($website !== null) {
            $urls[] = $website;
        }
        foreach ($this->searchUrls($query) as $url) {
            $urls[] = $url;
        }
        $urls = $this->uniqueUrls($urls);
        usort($urls, function (string $a, string $b) use ($name): int {
            return ($this->hostMatchesName($b, $name) ? 1 : 0) <=> ($this->hostMatchesName($a, $name) ? 1 : 0);
        });

        $emails = [];
        $phones = [];
        $foundWebsite = $website;
        $fetched = 0;
        foreach ($urls as $url) {
            if ($fetched >= 4) {
                break;
            }
            $html = $this->fetchHtml($url);
            if ($html === null) {
                continue;
            }
            ++$fetched;
            if ($foundWebsite === null && !$this->isDirectoryHost($url) && $this->hostMatchesName($url, $name)) {
                $foundWebsite = $this->originOf($url);
            }
            foreach ($this->extractEmails($html, $url) as $row) {
                $emails[] = $row;
            }
            foreach ($this->extractPhones($html, $url) as $row) {
                $phones[] = $row;
            }
            foreach ($this->contactLinks($html, $url) as $extra) {
                if ($fetched >= 4) {
                    break;
                }
                $extraHtml = $this->fetchHtml($extra);
                if ($extraHtml === null) {
                    continue;
                }
                ++$fetched;
                foreach ($this->extractEmails($extraHtml, $extra) as $row) {
                    $emails[] = $row;
                }
                foreach ($this->extractPhones($extraHtml, $extra) as $row) {
                    $phones[] = $row;
                }
            }
        }

        return [
            'query' => $query,
            'search_url' => $searchUrl,
            'website' => $foundWebsite,
            'emails' => $this->uniqueHits($this->rankEmails($emails, $foundWebsite)),
            'phones' => $this->uniqueHits($phones),
        ];
    }

    /**
     * @return list<string>
     */
    public function searchUrls(string $query): array
    {
        try {
            $response = $this->httpClient->request('POST', 'https://html.duckduckgo.com/html/', [
                'body' => ['q' => $query],
                'timeout' => 8,
                'headers' => [
                    'User-Agent' => 'eMatChef/1.0 (inquiry contact lookup)',
                    'Accept' => 'text/html',
                ],
            ]);
            $html = $response->getContent(false);
        } catch (\Throwable) {
            return [];
        }

        return $this->urlsFromSearchHtml($html);
    }

    /**
     * @return list<string>
     */
    public function urlsFromSearchHtml(string $html): array
    {
        $urls = [];
        if (preg_match_all('/uddg=([^&"\']+)/i', $html, $matches)) {
            foreach ($matches[1] as $encoded) {
                $url = html_entity_decode(urldecode((string) $encoded), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($this->isHttpUrl($url) && !$this->isJunkHost($url)) {
                    $urls[] = $url;
                }
            }
        }
        if (preg_match_all('/class="result__a"[^>]*href="([^"]+)"/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $url = html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (str_contains($url, 'uddg=')) {
                    continue;
                }
                if ($this->isHttpUrl($url) && !$this->isJunkHost($url)) {
                    $urls[] = $url;
                }
            }
        }

        return $this->uniqueUrls($urls);
    }

    /**
     * @return list<array{value: string, source: string}>
     */
    public function extractEmails(string $html, string $source): array
    {
        $hits = [];
        if (preg_match_all('/mailto:([^\s"\'>?]+)/i', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $email = $this->cleanEmail(urldecode((string) $raw));
                if ($email !== null) {
                    $hits[] = ['value' => $email, 'source' => $source];
                }
            }
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)) {
            foreach ($matches[0] as $raw) {
                $email = $this->cleanEmail((string) $raw);
                if ($email !== null) {
                    $hits[] = ['value' => $email, 'source' => $source];
                }
            }
        }

        return $hits;
    }

    /**
     * @return list<array{value: string, source: string}>
     */
    public function extractPhones(string $html, string $source): array
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $hits = [];
        if (preg_match_all('/(?:\+41|0041|0)\s*(?:\(0\))?\s*\d(?:[\s.\/-]?\d){8,12}/', $text, $matches)) {
            foreach ($matches[0] as $raw) {
                $phone = $this->cleanPhone((string) $raw);
                if ($phone !== null) {
                    $hits[] = ['value' => $phone, 'source' => $source];
                }
            }
        }
        if (preg_match_all('/tel:([+\d][\d\s.\/()-]{8,20})/i', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $phone = $this->cleanPhone(urldecode((string) $raw));
                if ($phone !== null) {
                    $hits[] = ['value' => $phone, 'source' => $source];
                }
            }
        }

        return $hits;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 8,
                'max_redirects' => 4,
                'headers' => [
                    'User-Agent' => 'eMatChef/1.0 (inquiry contact lookup)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 400) {
                return null;
            }
            $html = $response->getContent(false);
        } catch (\Throwable) {
            return null;
        }
        if (strlen($html) > 400_000) {
            $html = substr($html, 0, 400_000);
        }

        return $html;
    }

    /**
     * @return list<string>
     */
    private function contactLinks(string $html, string $baseUrl): array
    {
        $links = [];
        if (!preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches)) {
            return [];
        }
        foreach ($matches[1] as $href) {
            $href = html_entity_decode(trim((string) $href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $path = strtolower($href);
            if (!str_contains($path, 'kontakt') && !str_contains($path, 'impressum') && !str_contains($path, 'contact')) {
                continue;
            }
            $absolute = $this->absoluteUrl($baseUrl, $href);
            if ($absolute !== null && !$this->isJunkHost($absolute)) {
                $links[] = $absolute;
            }
            if (count($links) >= 2) {
                break;
            }
        }

        return $this->uniqueUrls($links);
    }

    /**
     * @param list<array{value: string, source: string}> $emails
     * @return list<array{value: string, source: string}>
     */
    private function rankEmails(array $emails, ?string $website): array
    {
        $host = $website ? strtolower((string) parse_url($website, PHP_URL_HOST)) : '';
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        usort($emails, function (array $a, array $b) use ($host): int {
            return $this->emailScore($b['value'], $host) <=> $this->emailScore($a['value'], $host);
        });

        return $emails;
    }

    private function emailScore(string $email, string $siteHost): int
    {
        $score = 0;
        [$local, $domain] = array_pad(explode('@', strtolower($email), 2), 2, '');
        if ($siteHost !== '' && ($domain === $siteHost || str_ends_with($domain, '.'.$siteHost) || str_ends_with($siteHost, '.'.$domain))) {
            $score += 8;
        }
        if (in_array($local, ['info', 'kontakt', 'mail', 'office', 'hello', 'anfrage', 'admin', 'sekretariat'], true)) {
            $score += 4;
        }

        return $score;
    }

    private function cleanEmail(string $raw): ?string
    {
        $email = strtolower(trim($raw, " \t\n\r\0\x0B.,;<>"));
        $email = preg_replace('/\?.*$/', '', $email) ?? $email;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '' || in_array($domain, self::JUNK_EMAIL_DOMAINS, true)) {
            return null;
        }
        if ($this->hostInList($domain, self::DIRECTORY_HOSTS) || $this->hostInList($domain, self::JUNK_HOSTS)) {
            return null;
        }
        if (preg_match('/\.(png|jpe?g|gif|webp|css|js|svg)$/i', $email)) {
            return null;
        }
        $local = strstr($email, '@', true) ?: '';
        if (preg_match('/^(noreply|no-reply|donotreply|privacy|mailer-daemon)$/i', $local)) {
            return null;
        }

        return $email;
    }

    private function cleanPhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '0041')) {
            $digits = '41'.substr($digits, 4);
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $digits = '41'.substr($digits, 1);
        }
        if (!str_starts_with($digits, '41') || strlen($digits) < 11 || strlen($digits) > 13) {
            return null;
        }

        return '+'.$digits;
    }

    private function normalizeUrl(string $website): ?string
    {
        $website = trim($website);
        if ($website === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        return $this->isHttpUrl($website) ? $website : null;
    }

    private function isHttpUrl(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }

    private function isJunkHost(string $url): bool
    {
        return $this->hostInList($this->hostOf($url), self::JUNK_HOSTS);
    }

    private function isDirectoryHost(string $url): bool
    {
        return $this->hostInList($this->hostOf($url), self::DIRECTORY_HOSTS);
    }

    private function hostMatchesName(string $url, string $name): bool
    {
        $host = $this->hostOf($url);
        if ($host === '') {
            return false;
        }
        foreach ($this->nameTokens($name) as $token) {
            if (str_contains($host, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function nameTokens(string $name): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', mb_strtolower($name, 'UTF-8')) ?: [];
        $stop = ['gmbh', 'ag', 'sagl', 'sarl', 'ltd', 'inc', 'und', 'and', 'the'];
        $tokens = [];
        foreach ($parts as $part) {
            if (strlen($part) < 4 || in_array($part, $stop, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return $tokens;
    }

    private function hostOf(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /**
     * @param list<string> $hosts
     */
    private function hostInList(string $host, array $hosts): bool
    {
        $host = preg_replace('/^www\./', '', strtolower($host)) ?? strtolower($host);
        if ($host === '') {
            return false;
        }
        foreach ($hosts as $needle) {
            if ($host === $needle || str_ends_with($host, '.'.$needle)) {
                return true;
            }
        }

        return false;
    }

    private function originOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'];
    }

    private function absoluteUrl(string $base, string $href): ?string
    {
        if (str_starts_with($href, '#')) {
            return null;
        }
        if ($this->isHttpUrl($href)) {
            return $href;
        }
        $origin = $this->originOf($base);
        if ($origin === null) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            return 'https:'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        return $origin.'/'.ltrim($href, '/');
    }

    /**
     * @param list<string> $urls
     * @return list<string>
     */
    private function uniqueUrls(array $urls): array
    {
        $seen = [];
        $out = [];
        foreach ($urls as $url) {
            $key = strtolower(rtrim($url, '/'));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $url;
        }

        return $out;
    }

    /**
     * @param list<array{value: string, source: string}> $hits
     * @return list<array{value: string, source: string}>
     */
    private function uniqueHits(array $hits): array
    {
        $seen = [];
        $out = [];
        foreach ($hits as $hit) {
            $key = strtolower($hit['value']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $hit;
        }

        return array_slice($out, 0, 8);
    }
}
