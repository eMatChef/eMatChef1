<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\UserAgentSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAgentSummaryTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, string|null, string|null, string}>
     */
    public static function agents(): iterable
    {
        yield 'chrome windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36', 'Chrome', 'Windows', 'Chrome / Windows'];
        yield 'edge' => ['Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537 Edg/120', 'Edge', 'Windows', 'Edge / Windows'];
        yield 'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0', 'Firefox', 'Linux', 'Firefox / Linux'];
        yield 'safari mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605 Version/17 Safari/605', 'Safari', 'macOS', 'Safari / macOS'];
        yield 'safari ios' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605 Version/17 Mobile Safari/604', 'Safari', 'iOS', 'Safari / iOS'];
        yield 'chrome android' => ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537 Chrome/120 Mobile Safari/537', 'Chrome', 'Android', 'Chrome / Android'];
        yield 'empty' => [null, null, null, 'Unbekanntes Gerät'];
        yield 'garbage' => ['curl/8.0', null, null, 'Unbekanntes Gerät'];
    }

    #[DataProvider('agents')]
    public function testDescribe(?string $ua, ?string $browser, ?string $os, string $label): void
    {
        self::assertSame(['browser' => $browser, 'os' => $os, 'label' => $label], UserAgentSummary::describe($ua));
    }
}
