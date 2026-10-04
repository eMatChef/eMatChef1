<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HitobitoApiClient implements HitobitoGroupLookup
{
    private const MAX_ROLE_PAGES = 100;

    /**
     * @param array<string, string> $providerIssuers
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly array $providerIssuers,
    ) {}

    public function getGroup(string $provider, string $accessToken, string $groupId): ?HitobitoGroup
    {
        if ($groupId === '') {
            throw new HitobitoApiException('invalid_group_id', 'Hitobito group id is missing');
        }

        $document = $this->getDocument(
            $accessToken,
            $this->providerBaseUrl($provider) . '/api/groups/' . rawurlencode($groupId),
        );
        if ($document === null) {
            return null;
        }

        try {
            $resource = $document['data'] ?? null;
            if (!is_array($resource)) {
                throw new \UnexpectedValueException('Hitobito group data is missing');
            }

            $group = HitobitoGroup::fromJsonApi($resource);
            if ($group->id !== $groupId) {
                throw new \UnexpectedValueException('Hitobito returned a different group id');
            }

            return $group;
        } catch (\UnexpectedValueException $exception) {
            throw new HitobitoApiException('malformed_response', 'Hitobito group response is malformed', 200, $exception);
        }
    }

    public function getRole(string $provider, string $accessToken, string $roleId): ?HitobitoRole
    {
        if ($roleId === '') {
            throw new HitobitoApiException('invalid_role_id', 'Hitobito role id is missing');
        }

        $document = $this->getDocument(
            $accessToken,
            $this->providerBaseUrl($provider) . '/api/roles/' . rawurlencode($roleId),
        );
        if ($document === null) {
            return null;
        }

        try {
            $resource = $document['data'] ?? null;
            if (!is_array($resource)) {
                throw new \UnexpectedValueException('Hitobito role data is missing');
            }

            $roleResourceId = $resource['id'] ?? null;
            if ((!is_string($roleResourceId) && !is_int($roleResourceId)) || (string) $roleResourceId !== $roleId) {
                throw new \UnexpectedValueException('Hitobito returned a different role id');
            }

            return HitobitoRole::fromJsonApi($resource);
        } catch (\UnexpectedValueException $exception) {
            throw new HitobitoApiException('malformed_response', 'Hitobito role response is malformed', 200, $exception);
        }
    }

    /**
     * @return list<HitobitoRole>
     */
    public function getRolesForPerson(string $provider, string $accessToken, string $personId): array
    {
        if ($personId === '') {
            throw new HitobitoApiException('invalid_person_id', 'Hitobito person id is missing');
        }

        $baseUrl = $this->providerBaseUrl($provider);
        $url = $baseUrl . '/api/roles?' . http_build_query(['filter' => ['person_id' => $personId]]);
        $seenUrls = [];
        $roles = [];

        for ($page = 0; $url !== null; $page++) {
            if ($page >= self::MAX_ROLE_PAGES) {
                throw new HitobitoApiException('pagination_limit', 'Hitobito roles pagination exceeded the safety limit');
            }
            if (isset($seenUrls[$url])) {
                throw new HitobitoApiException('pagination_cycle', 'Hitobito roles pagination contains a cycle');
            }
            $seenUrls[$url] = true;

            $document = $this->getDocument($accessToken, $url);
            if ($document === null) {
                throw new HitobitoApiException('not_found', 'Hitobito roles endpoint was not found', 404);
            }
            $resources = $document['data'] ?? null;
            if (!is_array($resources) || !array_is_list($resources)) {
                throw new HitobitoApiException('malformed_response', 'Hitobito roles response is malformed', 200);
            }

            foreach ($resources as $resource) {
                if (!is_array($resource)) {
                    throw new HitobitoApiException('malformed_response', 'Hitobito role entry is malformed', 200);
                }
                try {
                    $role = HitobitoRole::fromJsonApi($resource);
                } catch (\UnexpectedValueException $exception) {
                    throw new HitobitoApiException('malformed_response', 'Hitobito role entry is malformed', 200, $exception);
                }
                if ($role->personId === $personId) {
                    $roles[] = $role;
                }
            }

            $links = $document['links'] ?? [];
            if (!is_array($links)) {
                throw new HitobitoApiException('malformed_response', 'Hitobito roles pagination links are malformed', 200);
            }
            $next = $links['next'] ?? null;
            if ($next === null || $next === '') {
                $url = null;
            } elseif (is_string($next)) {
                $url = $this->resolvePaginationUrl($baseUrl, $next);
            } elseif (is_array($next) && is_string($next['href'] ?? null)) {
                $url = $this->resolvePaginationUrl($baseUrl, $next['href']);
            } else {
                throw new HitobitoApiException('malformed_response', 'Hitobito roles pagination link is malformed', 200);
            }
        }

        return $roles;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getDocument(string $accessToken, string $url): ?array
    {
        if ($accessToken === '') {
            throw new HitobitoApiException('missing_token', 'Hitobito API access token is missing');
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/vnd.api+json',
                ],
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
            if ($status === 404) {
                return null;
            }
            if ($status < 200 || $status >= 300) {
                throw new HitobitoApiException(
                    $status === 401 || $status === 403 ? 'permission_denied' : 'api_error',
                    'Hitobito API request failed with HTTP ' . $status,
                    $status,
                );
            }

            $document = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);
        } catch (HitobitoApiException $exception) {
            throw $exception;
        } catch (TransportExceptionInterface $exception) {
            throw new HitobitoApiException('transport_error', 'Hitobito API request failed', previous: $exception);
        } catch (\JsonException $exception) {
            throw new HitobitoApiException('malformed_response', 'Hitobito API response is not valid JSON', 200, $exception);
        }

        if (!is_array($document)) {
            throw new HitobitoApiException('malformed_response', 'Hitobito API response must be a JSON object', 200);
        }

        return $document;
    }

    private function providerBaseUrl(string $provider): string
    {
        $issuer = $this->providerIssuers[$provider] ?? null;
        if (!is_string($issuer) || filter_var($issuer, FILTER_VALIDATE_URL) === false) {
            throw new HitobitoApiException('unknown_provider', 'Hitobito API provider is not configured');
        }

        $parts = parse_url($issuer);
        if (
            !is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || !is_string($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new HitobitoApiException('invalid_provider_url', 'Hitobito API provider URL must be an HTTPS origin');
        }

        return rtrim($issuer, '/');
    }

    private function resolvePaginationUrl(string $baseUrl, string $next): string
    {
        if (str_starts_with($next, 'https://')) {
            $resolved = $next;
        } elseif (str_starts_with($next, '?')) {
            $resolved = $baseUrl . '/api/roles' . $next;
        } elseif (str_starts_with($next, '/')) {
            $resolved = $baseUrl . $next;
        } else {
            throw new HitobitoApiException('invalid_pagination_link', 'Hitobito roles pagination link is malformed');
        }
        $baseParts = parse_url($baseUrl);
        $nextParts = parse_url($resolved);

        if (
            !is_array($baseParts)
            || !is_array($nextParts)
            || ($nextParts['scheme'] ?? null) !== 'https'
            || ($nextParts['host'] ?? null) !== ($baseParts['host'] ?? null)
            || ($nextParts['port'] ?? null) !== ($baseParts['port'] ?? null)
            || ($nextParts['path'] ?? null) !== '/api/roles'
            || isset($nextParts['user'])
            || isset($nextParts['pass'])
            || isset($nextParts['fragment'])
        ) {
            throw new HitobitoApiException('invalid_pagination_link', 'Hitobito roles pagination link is outside the configured API origin');
        }

        return $resolved;
    }
}
