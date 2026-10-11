<?php

declare(strict_types=1);

namespace App\Service\Demo;

/**
 * Stabile öffentliche Codes (QR) für Seed-Datensätze. Der Code ergibt sich aus Szenario und Seed-Schlüssel und nicht aus einem
 * Zufallswert; wird ein Seed-Datensatz nach dem Löschen oder einem Reset neu angelegt, trägt er denselben QR-Code wie zuvor.
 * Die URL-Struktur der Codes bleibt unverändert, nur der Wert ist reproduzierbar. Zufallscodes anderer Daten (auch aus dem
 * bisherigen Event) werden nie übernommen.
 */
final class DemoStableCodes
{
    private const SALT = 'ematchef-demo-qr-v1';

    /** Code mit festem Präfix und insgesamt 12 Zeichen (Packaufträge `pk…`, Orte `ga…`, Fahrerkarten `uc…`). */
    public static function prefixed(string $prefix, string $scenarioKey, string $seedKey): string
    {
        return $prefix . substr(self::digest($scenarioKey, $seedKey), 0, 12 - \strlen($prefix));
    }

    /** Interner Lager-QR (Lagerstandort, Regal, Fach): wie die zufälligen Codes 14 Zeichen Base64-URL. */
    public static function internal(string $scenarioKey, string $seedKey): string
    {
        return rtrim(strtr(base64_encode(substr(hash('sha256', self::SALT . '|' . $scenarioKey . '|' . $seedKey, true), 0, 10)), '+/', '-_'), '=');
    }

    private static function digest(string $scenarioKey, string $seedKey): string
    {
        return hash('sha256', self::SALT . '|' . $scenarioKey . '|' . $seedKey);
    }
}
