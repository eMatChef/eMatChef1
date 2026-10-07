<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Grobe, lesbare Beschreibung eines User-Agents («Chrome auf Windows»). Kein Fingerprinting:
 * nur Browser-Familie und Betriebssystem-Familie aus dem ohnehin gespeicherten Header.
 */
final class UserAgentSummary
{
    /**
     * @return array{browser: string|null, os: string|null, label: string}
     */
    public static function describe(?string $userAgent): array
    {
        $ua = (string) $userAgent;
        $browser = match (true) {
            $ua === '' => null,
            (bool) preg_match('/Edg(e|A|iOS)?\//', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $ua) => 'Opera',
            (bool) preg_match('/Firefox\/|FxiOS\//', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\/|CriOS\//', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//', $ua) => 'Safari',
            default => null,
        };
        $os = match (true) {
            $ua === '' => null,
            (bool) preg_match('/Android/', $ua) => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            (bool) preg_match('/Windows/', $ua) => 'Windows',
            (bool) preg_match('/Mac OS X|Macintosh/', $ua) => 'macOS',
            (bool) preg_match('/CrOS/', $ua) => 'ChromeOS',
            (bool) preg_match('/Linux|X11/', $ua) => 'Linux',
            default => null,
        };

        $label = match (true) {
            $browser !== null && $os !== null => $browser . ' / ' . $os,
            $browser !== null => $browser,
            $os !== null => $os,
            default => 'Unbekanntes Gerät',
        };

        return ['browser' => $browser, 'os' => $os, 'label' => $label];
    }
}
