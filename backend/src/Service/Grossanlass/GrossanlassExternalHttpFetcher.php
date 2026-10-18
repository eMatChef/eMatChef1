<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassExternalSource;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ruft die JSON-Antwort einer externen Quelle ab (GET). Nur https, keine Weiterleitungen (der Schlüssel würde sonst an
 * Dritte gehen), keine privaten Netze (SSRF), begrenzte Grösse und Dauer. Fehlermeldungen enthalten nie den Schlüssel.
 */
class GrossanlassExternalHttpFetcher
{
    public const MAX_BYTES = 5_000_000;

    private readonly HttpClientInterface $client;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->client = $httpClient instanceof NoPrivateNetworkHttpClient ? $httpClient : new NoPrivateNetworkHttpClient($httpClient);
    }

    /** Prüft Adresse und Pfad und gibt die vollständige URL zurück. @throws \InvalidArgumentException */
    public static function buildUrl(string $baseUrl, string $path): string
    {
        $baseUrl = trim($baseUrl);
        $parts = parse_url($baseUrl);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === '') {
            throw new \InvalidArgumentException('Die API-Basisadresse muss mit https:// beginnen');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Die API-Basisadresse darf keine Zugangsdaten oder Anker enthalten');
        }
        $path = trim($path);
        if ($path !== '' && (str_contains($path, '://') || str_starts_with($path, '//') || preg_match('/[\s\\\\]/', $path))) {
            throw new \InvalidArgumentException('Ungültiger Endpunkt-Pfad');
        }

        return rtrim($baseUrl, '/') . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    /**
     * @return array{status: int, body: string}
     * @throws GrossanlassExternalFetchException
     */
    public function fetch(DepartmentGrossanlassExternalSource $source, ?string $secret): array
    {
        try {
            $url = self::buildUrl($source->getBaseUrl(), $source->getEndpointPath());
        } catch (\InvalidArgumentException $e) {
            throw new GrossanlassExternalFetchException($e->getMessage());
        }
        $headers = ['Accept' => 'application/json'];
        $options = ['headers' => $headers, 'timeout' => 15, 'max_duration' => 30, 'max_redirects' => 0];
        $secret = $secret === null || $secret === '' ? null : $secret;
        switch ($source->getAuthType()) {
            case DepartmentGrossanlassExternalSource::AUTH_BEARER:
                $secret !== null && $options['auth_bearer'] = $secret;
                break;
            case DepartmentGrossanlassExternalSource::AUTH_HEADER:
                if ($secret !== null && $source->getAuthHeader() !== null) {
                    $options['headers'][$source->getAuthHeader()] = $secret;
                }
                break;
            case DepartmentGrossanlassExternalSource::AUTH_BASIC:
                $secret !== null && $options['auth_basic'] = [(string) $source->getAuthUser(), $secret];
                break;
        }

        try {
            $response = $this->client->request('GET', $url, $options);
            $status = $response->getStatusCode();
            $body = '';
            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();
                if (strlen($body) > self::MAX_BYTES) {
                    $response->cancel();
                    throw new GrossanlassExternalFetchException('Die Antwort ist zu gross (über 5 MB)', $status);
                }
            }
        } catch (GrossanlassExternalFetchException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Transportfehler können die URL, aber keinen Header enthalten; trotzdem nur eine generische Meldung weitergeben.
            throw new GrossanlassExternalFetchException('Verbindung fehlgeschlagen: ' . self::safeMessage($e, $secret));
        }
        if ($status >= 300 && $status < 400) {
            throw new GrossanlassExternalFetchException('Die API leitet weiter (HTTP ' . $status . '); Weiterleitungen werden nicht verfolgt', $status);
        }
        if ($status === 401 || $status === 403) {
            throw new GrossanlassExternalFetchException('Zugriff verweigert (HTTP ' . $status . '): Authentifizierung prüfen', $status);
        }
        if ($status < 200 || $status >= 300) {
            throw new GrossanlassExternalFetchException('Die API antwortet mit HTTP ' . $status, $status);
        }

        return ['status' => $status, 'body' => $body];
    }

    private static function safeMessage(\Throwable $e, ?string $secret): string
    {
        $message = mb_substr($e->getMessage(), 0, 200);

        return $secret !== null ? str_replace($secret, '***', $message) : $message;
    }
}
