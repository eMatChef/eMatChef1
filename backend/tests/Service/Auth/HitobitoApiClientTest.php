<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\HitobitoApiClient;
use App\Service\Auth\HitobitoApiException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HitobitoApiClientTest extends TestCase
{
    public function testFetchesAndNormalizesGroupUsingBearerToken(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(json_encode([
                'data' => [
                    'type' => 'groups',
                    'id' => '111',
                    'attributes' => [
                        'parent_id' => 110,
                        'type' => 'Group::Woelfe',
                        'name' => 'Meute Mandi',
                    ],
                ],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $group = $this->client($http)->getGroup('midata', 'short-lived-token', '111');

        self::assertNotNull($group);
        self::assertSame('111', $group->id);
        self::assertSame('110', $group->parentId);
        self::assertSame('Group::Woelfe', $group->type);
        self::assertSame('Meute Mandi', $group->name);
        self::assertSame('GET', $requests[0][0]);
        self::assertSame('https://db.scout.ch/api/groups/111', $requests[0][1]);
        self::assertSame('Bearer short-lived-token', $this->headerValue($requests[0][2]['headers'], 'Authorization'));
        self::assertSame('application/vnd.api+json', $this->headerValue($requests[0][2]['headers'], 'Accept'));
        self::assertSame(0, $requests[0][2]['max_redirects']);
    }

    public function testGroup404IsReturnedAsUnknownGroup(): void
    {
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);

        self::assertNull($this->client($http)->getGroup('midata', 'token', 'missing'));
    }

    public function testFetchesAndNormalizesRoleById(): void
    {
        $requests = [];
        $roleResource = $this->roleResource('person-1', 'group-100', 'Group::Leader', '2026-01-01', null);
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $roleResource): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(json_encode([
                'data' => $roleResource,
            ], JSON_THROW_ON_ERROR));
        });

        $role = $this->client($http)->getRole('midata', 'token', 'role-1');

        self::assertNotNull($role);
        self::assertSame('person-1', $role->personId);
        self::assertSame('group-100', $role->groupId);
        self::assertSame('Group::Leader', $role->type);
        self::assertSame('https://db.scout.ch/api/roles/role-1', $requests[0][1]);
    }

    public function testPermissionAndApiErrorsAreExplicit(): void
    {
        foreach ([401, 403, 500] as $status) {
            $http = new MockHttpClient([new MockResponse('{}', ['http_code' => $status])]);

            try {
                $this->client($http)->getGroup('midata', 'token', '111');
                self::fail('Expected a Hitobito API error');
            } catch (HitobitoApiException $exception) {
                self::assertSame($status, $exception->statusCode);
                self::assertSame(in_array($status, [401, 403], true) ? 'permission_denied' : 'api_error', $exception->reason);
            }
        }
    }

    public function testMalformedGroupResponseFailsExplicitly(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode(['data' => ['type' => 'roles', 'id' => '111', 'attributes' => []]], JSON_THROW_ON_ERROR)),
        ]);

        try {
            $this->client($http)->getGroup('midata', 'token', '111');
            self::fail('Expected a malformed response error');
        } catch (HitobitoApiException $exception) {
            self::assertSame('malformed_response', $exception->reason);
        }
    }

    public function testRejectsGroupResponseWithDifferentRequestedId(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'data' => [
                    'type' => 'groups',
                    'id' => 'different',
                    'attributes' => ['parent_id' => null, 'type' => 'Group::Abteilung', 'name' => 'Other'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        try {
            $this->client($http)->getGroup('midata', 'token', '111');
            self::fail('Expected mismatched group id to be rejected');
        } catch (HitobitoApiException $exception) {
            self::assertSame('malformed_response', $exception->reason);
        }
    }

    public function testListsPersonRolesAcrossJsonApiPages(): void
    {
        $requests = [];
        $responses = [
            new MockResponse(json_encode([
                'data' => [$this->roleResource('person-1', 'group-100', 'Group::Leader', '2026-01-01', null)],
                'links' => ['next' => 'https://db.scout.ch/api/roles?page%5Bnumber%5D=2&filter%5Bperson_id%5D=person-1'],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'data' => [$this->roleResource('person-1', 'group-111', 'Group::Member', null, '2026-10-04')],
                'links' => ['next' => null],
            ], JSON_THROW_ON_ERROR)),
        ];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url, $options];

            return array_shift($responses);
        });

        $roles = $this->client($http)->getRolesForPerson('midata', 'token', 'person-1');

        self::assertCount(2, $roles);
        self::assertSame('person-1', $roles[0]->personId);
        self::assertSame('group-100', $roles[0]->groupId);
        self::assertSame('Group::Leader', $roles[0]->type);
        self::assertSame('2026-01-01', $roles[0]->startOn?->format('Y-m-d'));
        self::assertNull($roles[0]->endOn);
        self::assertSame('group-111', $roles[1]->groupId);
        self::assertSame('2026-10-04', $roles[1]->endOn?->format('Y-m-d'));
        self::assertCount(2, $requests);
        self::assertStringContainsString('filter%5Bperson_id%5D=person-1', $requests[0][1]);
        self::assertSame('Bearer token', $this->headerValue($requests[1][2]['headers'], 'Authorization'));
    }

    public function testRejectsPaginationLinkToAnotherHost(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'data' => [],
                'links' => ['next' => 'https://attacker.example/api/roles?page=2'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        try {
            $this->client($http)->getRolesForPerson('midata', 'token', 'person-1');
            self::fail('Expected unsafe pagination link to be rejected');
        } catch (HitobitoApiException $exception) {
            self::assertSame('invalid_pagination_link', $exception->reason);
        }
    }

    private function client(MockHttpClient $http): HitobitoApiClient
    {
        return new HitobitoApiClient($http, ['midata' => 'https://db.scout.ch']);
    }

    /**
     * @param array<array-key, mixed> $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (is_string($key) && strcasecmp($key, $name) === 0 && is_string($value)) {
                return $value;
            }
            if (is_string($value) && str_starts_with(strtolower($value), strtolower($name) . ':')) {
                return trim(substr($value, strlen($name) + 1));
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function roleResource(
        string $personId,
        string $groupId,
        string $type,
        ?string $startOn,
        ?string $endOn,
    ): array {
        return [
            'type' => 'roles',
            'id' => 'role-1',
            'attributes' => [
                'person_id' => $personId,
                'group_id' => $groupId,
                'type' => $type,
                'start_on' => $startOn,
                'end_on' => $endOn,
            ],
        ];
    }
}
