<?php

declare(strict_types=1);

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lädt ein Bild von einer öffentlichen http(s)-URL und liefert eine temporäre UploadedFile.
 */
class MediaUrlImportService
{
    private const MAX_BYTES = MediaCompressionService::MAX_BYTES;
    private const TIMEOUT_SECONDS = 15;
    private const MAX_REDIRECT_DEPTH = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function toUploadedFile(string $url, int $redirectDepth = 0): UploadedFile
    {
        $url = $this->maybeUnwrapGoogleImageUrl(trim($url), $redirectDepth);
        $parsed = $this->parseAndValidateUrl($url);
        $this->assertPublicHost((string) $parsed['host']);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT_SECONDS,
                'max_redirects' => 5,
                'headers' => [
                    'User-Agent' => 'eMatChef/1.0 (material-image-import)',
                    'Accept' => 'image/jpeg,image/png,image/webp,image/gif,image/*,*/*;q=0.8',
                ],
            ]);
        } catch (HttpClientExceptionInterface $e) {
            throw new \InvalidArgumentException('Bild konnte nicht geladen werden: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new \InvalidArgumentException('Bild konnte nicht geladen werden (HTTP ' . $status . ')');
        }

        $body = $response->getContent(false);
        $size = \strlen($body);
        if ($size <= 0) {
            throw new \InvalidArgumentException('Leere Antwort vom Server');
        }
        if ($size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Bild zu gross (max. 10 MB)');
        }

        if ($this->looksLikeHtml($body)) {
            throw new \InvalidArgumentException(
                'Die URL zeigt keine Bilddatei. In Google: Rechtsklick auf das Bild → «Link-Adresse kopieren» (direkte .jpg/.png-URL).',
            );
        }

        $headers = $response->getHeaders(false);
        $contentType = strtolower(trim(explode(';', (string) ($headers['content-type'][0] ?? ''))[0]));
        $mime = $this->resolveImageMime($contentType, $url, $body);

        $tmp = tempnam(sys_get_temp_dir(), 'emc_img_');
        if ($tmp === false) {
            throw new \RuntimeException('Temporäre Datei konnte nicht angelegt werden');
        }

        if (file_put_contents($tmp, $body) === false) {
            @unlink($tmp);
            throw new \RuntimeException('Bild konnte nicht zwischengespeichert werden');
        }

        $originalName = $this->guessOriginalFilename($parsed, $mime);

        return new UploadedFile($tmp, $originalName, $mime, null, true);
    }

    private function maybeUnwrapGoogleImageUrl(string $url, int $redirectDepth = 0): string
    {
        $parsed = parse_url($url);
        if (!\is_array($parsed) || empty($parsed['host']) || empty($parsed['query'])) {
            return $url;
        }

        $host = strtolower((string) $parsed['host']);
        if (!str_contains($host, 'google.') || !str_contains((string) ($parsed['path'] ?? ''), '/url')) {
            return $url;
        }

        parse_str((string) $parsed['query'], $query);
        $target = $query['url'] ?? $query['imgurl'] ?? null;
        if (!\is_string($target) || trim($target) === '') {
            throw new \InvalidArgumentException(
                'Google-Seitenlink erkannt. Bitte Rechtsklick auf das Bild → «Link-Adresse kopieren» und diese URL einfügen.',
            );
        }

        if ($redirectDepth >= self::MAX_REDIRECT_DEPTH) {
            throw new \InvalidArgumentException('Zu viele Weiterleitungen bei der Bild-URL');
        }

        return $this->maybeUnwrapGoogleImageUrl(urldecode(trim($target)), $redirectDepth + 1);
    }

    /** @return array<string, mixed> */
    private function parseAndValidateUrl(string $url): array
    {
        if ($url === '') {
            throw new \InvalidArgumentException('URL ist erforderlich');
        }

        $parsed = parse_url($url);
        if (!\is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new \InvalidArgumentException('Ungültige URL');
        }

        $scheme = strtolower((string) $parsed['scheme']);
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Nur http- und https-URLs erlaubt');
        }

        return $parsed;
    }

    private function assertPublicHost(string $host): void
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new \InvalidArgumentException('Diese URL ist nicht erlaubt');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!$this->isPublicIp($host)) {
                throw new \InvalidArgumentException('Private Netzwerk-URLs sind nicht erlaubt');
            }

            return;
        }

        $ips = gethostbynamel($host);
        if ($ips === false || $ips === []) {
            throw new \InvalidArgumentException('Host konnte nicht aufgelöst werden');
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new \InvalidArgumentException('Private Netzwerk-URLs sind nicht erlaubt');
            }
        }
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function looksLikeHtml(string $body): bool
    {
        $start = strtolower(ltrim(substr($body, 0, 512)));

        return str_starts_with($start, '<!doctype html')
            || str_starts_with($start, '<html')
            || str_starts_with($start, '<head')
            || str_contains($start, '<body');
    }

    private function sniffImageMime(string $body): ?string
    {
        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')) {
            return 'image/gif';
        }
        if (\strlen($body) >= 12 && str_starts_with($body, 'RIFF') && substr($body, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    private function resolveImageMime(string $contentType, string $url, string $body): string
    {
        if ($contentType !== '' && isset(MediaCompressionService::MIME_TO_EXT[$contentType])) {
            return $contentType;
        }

        $sniffed = $this->sniffImageMime($body);
        if ($sniffed !== null) {
            return $sniffed;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (\is_string($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mimeFromExt = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => '',
            };
            if ($mimeFromExt !== '' && isset(MediaCompressionService::MIME_TO_EXT[$mimeFromExt])) {
                return $mimeFromExt;
            }
        }

        throw new \InvalidArgumentException(
            'URL liefert kein gültiges Bild (JPEG, PNG, WebP oder GIF). Direkte Bild-URL nötig.',
        );
    }

    /** @param array<string, mixed> $parsed */
    private function guessOriginalFilename(array $parsed, string $mime): string
    {
        $ext = MediaCompressionService::MIME_TO_EXT[$mime];
        $path = (string) ($parsed['path'] ?? '');
        $basename = $path !== '' ? basename($path) : 'image';
        $basename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $basename) ?: 'image';
        if (!str_contains(strtolower($basename), '.')) {
            $basename .= '.' . $ext;
        }

        return $basename;
    }
}
