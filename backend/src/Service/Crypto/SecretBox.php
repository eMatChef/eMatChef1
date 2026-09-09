<?php

declare(strict_types=1);

namespace App\Service\Crypto;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** AES-256-GCM für Refresh-Tokens (nicht für Passwörter). */
final class SecretBox
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {}

    public function encrypt(string $plain): string
    {
        $key = hash('sha256', $this->appSecret, true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || $tag === '') {
            throw new \RuntimeException('Verschlüsselung fehlgeschlagen');
        }

        return rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
    }

    public function decrypt(string $encoded): string
    {
        $raw = $this->b64decode($encoded);
        if ($raw === null || strlen($raw) < 29) {
            throw new \InvalidArgumentException('Ungültiges Geheimnis');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $key = hash('sha256', $this->appSecret, true);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \InvalidArgumentException('Entschlüsselung fehlgeschlagen');
        }

        return $plain;
    }

    private function b64decode(string $b64): ?string
    {
        $padded = strtr($b64, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder > 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
