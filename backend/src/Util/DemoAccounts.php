<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Demo-/Testkonten für Development und Staging.
 *
 * Einzige Quelle ist data/seeds/dev-demo/demo-accounts.json; dieselbe Datei speist die Testumgebungsseite
 * auf docs.ematchef.ch. Die TOTP-Secrets darin sind öffentliche Testwerte (nur Demo-Konten der Admin-Rollen).
 * Nur die per EMATCHEF_DEV_TOOLS geschützten Seed-Commands dürfen diese Konten anlegen.
 */
final class DemoAccounts
{
    public const FILE = __DIR__ . '/../../data/seeds/dev-demo/demo-accounts.json';

    /** @var array{domain: string, password: string, totpIssuer: string, accounts: list<array<string, string>>}|null */
    private static ?array $data = null;

    public static function domain(): string
    {
        return self::data()['domain'];
    }

    public static function password(): string
    {
        return self::data()['password'];
    }

    public static function totpIssuer(): string
    {
        return self::data()['totpIssuer'];
    }

    /**
     * @return list<array<string, string>> key, email, legacyEmail, label, role, group[, totpSecret]
     */
    public static function all(): array
    {
        return self::data()['accounts'];
    }

    public static function email(string $key): string
    {
        return self::account($key)['email'];
    }

    /** Frühere Adresse (`*@ematchef.ch`), nur zur Migration bestehender Dev-/Staging-Datenbanken. */
    public static function legacyEmail(string $key): string
    {
        return self::account($key)['legacyEmail'];
    }

    public static function isDemoEmail(?string $email): bool
    {
        return $email !== null && str_ends_with(strtolower(trim($email)), '@' . self::domain());
    }

    /**
     * Exakte Adresse eines Kontos aus demo-accounts.json (nur diese gelten als vom Seed besessen).
     * Die Domain allein ist keine Ownership; `*@ematchef.ch` schon gar nicht.
     */
    public static function isSeedOwnedEmail(?string $email): bool
    {
        if ($email === null) {
            return false;
        }
        $email = strtolower(trim($email));
        foreach (self::all() as $account) {
            if ($account['email'] === $email) {
                return true;
            }
        }

        return false;
    }

    /** Fester Test-TOTP-Secret des Demo-Kontos oder null (nur superadmin/orgchef/suborgchef). */
    public static function totpSecretForEmail(string $email): ?string
    {
        $email = strtolower(trim($email));
        foreach (self::all() as $account) {
            if ($account['email'] === $email) {
                return $account['totpSecret'] ?? null;
            }
        }

        return null;
    }

    /** otpauth-URI, aus demselben Secret wie Seed und Doku. */
    public static function otpauthUri(string $email): ?string
    {
        $secret = self::totpSecretForEmail($email);
        if ($secret === null) {
            return null;
        }
        $issuer = self::totpIssuer();

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode(strtolower(trim($email))),
            $secret,
            rawurlencode($issuer),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function account(string $key): array
    {
        foreach (self::all() as $account) {
            if ($account['key'] === $key) {
                return $account;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unbekanntes Demo-Konto "%s".', $key));
    }

    /**
     * @return array{domain: string, password: string, totpIssuer: string, accounts: list<array<string, string>>}
     */
    private static function data(): array
    {
        if (self::$data === null) {
            $decoded = json_decode((string) file_get_contents(self::FILE), true, 512, JSON_THROW_ON_ERROR);
            self::$data = $decoded;
        }

        return self::$data;
    }
}
