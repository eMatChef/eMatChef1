<?php

namespace App\Service\Display;

/**
 * 8-stelliger alphanumerischer Zugangscode (ohne verwechselbare Zeichen).
 */
final class DisplayAccessCodeGenerator
{
    private const CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function generate(int $length = 8): string
    {
        if ($length < 6 || $length > 16) {
            throw new \InvalidArgumentException('Display access code length must be between 6 and 16.');
        }

        $max = strlen(self::CHARSET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::CHARSET[random_int(0, $max)];
        }

        return $out;
    }

    public function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    public function isValidFormat(string $code): bool
    {
        $normalized = $this->normalize($code);

        return (bool) preg_match('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/', $normalized);
    }
}
