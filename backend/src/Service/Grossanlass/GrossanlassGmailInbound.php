<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Zuordnung eingehender Gmail-Nachrichten: Thread/ID, keine Inhalts-KI.
 */
final class GrossanlassGmailInbound
{
    public const BODY_MAX = 8000;

    /**
     * @param array<string, string> $headers lowercase header name => value
     */
    public static function isIgnorable(array $headers, string $from, string $subject): bool
    {
        $auto = strtolower($headers['auto-submitted'] ?? '');
        if ($auto !== '' && $auto !== 'no') {
            return true;
        }
        if (($headers['x-autoreply'] ?? '') !== '' || ($headers['x-autorespond'] ?? '') !== '') {
            return true;
        }
        $precedence = strtolower($headers['precedence'] ?? '');
        if (in_array($precedence, ['bulk', 'junk', 'list'], true)) {
            return true;
        }
        $fromLower = strtolower($from);
        if (str_contains($fromLower, 'mailer-daemon')
            || str_contains($fromLower, 'postmaster@')
            || str_contains($fromLower, 'mail-delivery-daemon')
        ) {
            return true;
        }
        $sub = mb_strtolower(trim($subject));
        $patterns = [
            '/^auto(matic)?[- ]?(reply|response)/u',
            '/out of office/u',
            '/abwesenheitsnotiz/u',
            '/abwesend/u',
            '/\booo\b/u',
            '/undeliverable/u',
            '/delivery status notification/u',
            '/mail delivery failed/u',
            '/unzustellbar/u',
            '/returned mail/u',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sub) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function findInquiryIds(string ...$texts): array
    {
        $found = [];
        foreach ($texts as $text) {
            if (preg_match_all('/\biq[0-9a-f]{10}\b/i', $text, $matches) !== false) {
                foreach ($matches[0] as $id) {
                    $normalized = strtolower($id);
                    if (!in_array($normalized, $found, true)) {
                        $found[] = $normalized;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @return array{email: string, name: string}
     */
    public static function parseFrom(string $fromHeader): array
    {
        $fromHeader = trim($fromHeader);
        if (preg_match('/<([^>]+)>/', $fromHeader, $m) === 1) {
            $email = strtolower(trim($m[1]));
            $name = trim(str_replace($m[0], '', $fromHeader), " \t\"'");

            return ['email' => $email, 'name' => $name];
        }
        if (filter_var($fromHeader, FILTER_VALIDATE_EMAIL)) {
            return ['email' => strtolower($fromHeader), 'name' => ''];
        }

        return ['email' => '', 'name' => $fromHeader];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public static function headerMap(array $payload): array
    {
        $map = [];
        foreach ($payload['headers'] ?? [] as $header) {
            if (!is_array($header)) {
                continue;
            }
            $name = strtolower(trim((string) ($header['name'] ?? '')));
            $value = (string) ($header['value'] ?? '');
            if ($name !== '' && !isset($map[$name])) {
                $map[$name] = $value;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function extractBody(array $payload): string
    {
        $plain = self::findPartBody($payload, 'text/plain');
        if ($plain !== '') {
            return self::truncate(trim($plain));
        }
        $html = self::findPartBody($payload, 'text/html');
        if ($html !== '') {
            $withBreaks = preg_replace('/<(?:br|\/p|\/div|\/h[1-6]|\/li)\s*\/?>/i', "\n", $html) ?? $html;
            $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return self::truncate(trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function findPartBody(array $payload, string $mime): string
    {
        $mimeLower = strtolower((string) ($payload['mimeType'] ?? ''));
        if ($mimeLower === $mime && is_array($payload['body'] ?? null)) {
            $data = (string) ($payload['body']['data'] ?? '');
            if ($data !== '') {
                return self::decodeBody($data);
            }
        }
        foreach ($payload['parts'] ?? [] as $part) {
            if (!is_array($part)) {
                continue;
            }
            $found = self::findPartBody($part, $mime);
            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }

    private static function decodeBody(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($padded, true);

        return is_string($decoded) ? $decoded : '';
    }

    public static function truncate(string $text, int $max = self::BODY_MAX): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max) . '…';
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return array{has_draft: bool, has_sent: bool, has_firm_reply: bool}
     */
    public static function mailboxFlags(array $messages, string $okEmail, bool $draftStillThere): array
    {
        $hasDraft = $draftStillThere;
        $hasSent = false;
        $hasFirm = false;
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $labels = [];
            foreach ($message['labelIds'] ?? [] as $label) {
                $labels[] = strtoupper((string) $label);
            }
            $isDraft = in_array('DRAFT', $labels, true);
            $isSent = in_array('SENT', $labels, true);
            $from = (string) ($message['from'] ?? '');
            $subject = (string) ($message['subject'] ?? '');
            $headers = is_array($message['headers'] ?? null) ? $message['headers'] : [];
            if ($isSent) {
                $hasSent = true;
                continue;
            }
            if ($isDraft) {
                $hasDraft = true;
                continue;
            }
            if (self::isFromAddress($from, $okEmail)) {
                $hasSent = true;
                continue;
            }
            if (!self::isIgnorable($headers, $from, $subject)) {
                $hasFirm = true;
            }
        }

        return [
            'has_draft' => $hasDraft,
            'has_sent' => $hasSent,
            'has_firm_reply' => $hasFirm,
        ];
    }

    public static function statusFromMailbox(
        string $current,
        bool $hasFirmReply,
        bool $hasSent,
        bool $hasDraft,
    ): ?string {
        if (in_array($current, ['zusage', 'absage'], true)) {
            return null;
        }
        if ($hasFirmReply) {
            return $current === 'antwort' ? null : 'antwort';
        }
        if ($hasSent) {
            if (in_array($current, ['gesendet', 'antwort'], true)) {
                return null;
            }

            return 'gesendet';
        }
        if ($hasDraft) {
            if ($current === 'vorschlag' || $current === 'gesendet') {
                return 'entwurf';
            }

            return null;
        }

        return null;
    }

    public static function isFromAddress(string $fromHeader, string $okEmail): bool
    {
        $ok = strtolower(trim($okEmail));
        if ($ok === '') {
            return false;
        }
        $parsed = self::parseFrom($fromHeader);

        return $parsed['email'] !== '' && $parsed['email'] === $ok;
    }
}
