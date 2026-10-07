<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\HitobitoApiClient;
use App\Service\Auth\HitobitoApiException;
use App\Service\Auth\HitobitoGroup;
use App\Service\Auth\HitobitoGroupParseException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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

    public function testChildGroupsRequestUsesSparseFieldsetAndFollowsNextLink(): void
    {
        $urls = [];
        $child = static fn (string $id): array => [
            'type' => 'groups',
            'id' => $id,
            'attributes' => ['type' => 'Group::Pfadi', 'name' => 'Stufe ' . $id, 'parent_id' => 100],
        ];
        $http = new MockHttpClient(static function (string $method, string $url) use (&$urls, $child): MockResponse {
            $urls[] = $url;

            return new MockResponse(json_encode(count($urls) === 1
                ? ['data' => [$child('201')], 'links' => ['next' => 'https://db.scout.ch/api/groups?page%5Bnumber%5D=2']]
                : ['data' => [$child('202')]], JSON_THROW_ON_ERROR));
        });

        $children = $this->client($http)->getChildGroups('midata', 'token', '100');

        self::assertSame(['201', '202'], array_map(static fn ($g): string => $g->id, $children));
        self::assertStringContainsString('filter%5Bparent_id%5D=100', $urls[0]);
        self::assertStringContainsString('fields%5Bgroups%5D=name%2Ctype%2Cparent_id', $urls[0]);
        self::assertSame('https://db.scout.ch/api/groups?page%5Bnumber%5D=2', $urls[1]);
    }

    public function testNormalizesGroupsUsingHitobitoJsonApiRelationships(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'data' => [
                    'type' => 'groups',
                    'id' => '111',
                    'attributes' => ['group_type' => 'Pfadi-Abteilung', 'name' => 'Pfadi Zytturm'],
                    'relationships' => [
                        'parent' => ['data' => ['type' => 'groups', 'id' => '100']],
                        'layer_group' => ['data' => ['type' => 'groups', 'id' => '1']],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $group = $this->client($http)->getGroup('midata', 'token', '111');

        self::assertNotNull($group);
        self::assertSame('100', $group->parentId);
        self::assertSame('Pfadi-Abteilung', $group->type);
        self::assertSame('Pfadi Zytturm', $group->name);
        self::assertSame('1', $group->layerGroupId);
    }

    public function testNormalizesRootGroupWithNullParentRelationship(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'data' => [
                    'type' => 'groups',
                    'id' => '1',
                    'attributes' => ['group_type' => 'Root', 'name' => 'Root'],
                    'relationships' => [
                        'parent' => ['data' => null],
                        'layer_group' => ['data' => null],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $group = $this->client($http)->getGroup('midata', 'token', '1');

        self::assertNotNull($group);
        self::assertNull($group->parentId);
        self::assertNull($group->layerGroupId);
    }

    public function testParsesPbsZytturmGroupResponseWithAttributeIdsAndMetaOnlyRelationships(): void
    {
        $group = $this->fetchGroup([
            'type' => 'groups',
            'id' => '51',
            'attributes' => [
                'name' => 'Pfadi Zytturm',
                'type' => 'Group::Abteilung',
                'parent_id' => 50,
                'layer_group_id' => 51,
            ],
            'relationships' => [
                'parent' => ['meta' => ['included' => false]],
                'layer_group' => ['meta' => ['included' => false]],
            ],
        ]);

        self::assertSame('51', $group->id);
        self::assertSame('Pfadi Zytturm', $group->name);
        self::assertSame('Group::Abteilung', $group->type);
        self::assertSame('50', $group->parentId);
        self::assertSame('51', $group->layerGroupId);
    }

    public function testFetchGroupParsesFullJsonApiDocumentWithMetaOnlyRelationshipsLikeLiveResponse(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'data' => [
                    'type' => 'groups',
                    'id' => '51',
                    'attributes' => [
                        'name' => 'Pfadi Zytturm',
                        'type' => 'Group::Abteilung',
                        'parent_id' => 50,
                        'layer_group_id' => 51,
                    ],
                    'relationships' => [
                        'parent' => ['meta' => ['included' => false]],
                        'layer_group' => ['meta' => ['included' => false]],
                    ],
                ],
                'meta' => [],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $group = $this->client($http)->getGroup('midata', 'token', '51');

        self::assertNotNull($group);
        self::assertSame('51', $group->id);
        self::assertSame('Pfadi Zytturm', $group->name);
        self::assertSame('Group::Abteilung', $group->type);
        self::assertSame('50', $group->parentId);
        self::assertSame('51', $group->layerGroupId);
    }

    public function testMetaOnlyRelationshipsWithoutAttributeIdsNormalizeToNull(): void
    {
        $group = $this->fetchGroup([
            'type' => 'groups',
            'id' => '51',
            'attributes' => [
                'name' => 'Pfadi Zytturm',
                'type' => 'Group::Abteilung',
            ],
            'relationships' => [
                'parent' => ['meta' => ['included' => false]],
                'layer_group' => ['meta' => ['included' => false]],
            ],
        ]);

        self::assertNull($group->parentId);
        self::assertNull($group->layerGroupId);
    }

    public function testMetaAndNullRelationshipDataUseAttributeIds(): void
    {
        $group = $this->fetchGroup([
            'type' => 'groups',
            'id' => '51',
            'attributes' => [
                'name' => 'Pfadi Zytturm',
                'type' => 'Group::Abteilung',
                'parent_id' => 50,
                'layer_group_id' => 51,
            ],
            'relationships' => [
                'parent' => ['meta' => ['included' => false], 'data' => null],
                'layer_group' => ['meta' => ['included' => false], 'data' => null],
            ],
        ]);

        self::assertSame('50', $group->parentId);
        self::assertSame('51', $group->layerGroupId);
    }

    public function testAcceptsMatchingAttributeAndRelationshipIds(): void
    {
        $group = $this->fetchGroup([
            'type' => 'groups',
            'id' => '51',
            'attributes' => [
                'name' => 'Pfadi Zytturm',
                'type' => 'Group::Abteilung',
                'parent_id' => 50,
                'layer_group_id' => 51,
            ],
            'relationships' => [
                'parent' => ['meta' => ['included' => true], 'data' => ['type' => 'groups', 'id' => '50']],
                'layer_group' => ['meta' => ['included' => true], 'data' => ['type' => 'groups', 'id' => '51']],
            ],
        ]);

        self::assertSame('50', $group->parentId);
        self::assertSame('51', $group->layerGroupId);
    }

    public function testUsesRelationshipIdsWhenAttributesAreAbsent(): void
    {
        $group = $this->fetchGroup([
            'type' => 'groups',
            'id' => '51',
            'attributes' => [
                'name' => 'Pfadi Zytturm',
                'type' => 'Group::Abteilung',
            ],
            'relationships' => [
                'parent' => ['data' => ['type' => 'groups', 'id' => '50']],
                'layer_group' => ['data' => ['type' => 'groups', 'id' => '51']],
            ],
        ]);

        self::assertSame('50', $group->parentId);
        self::assertSame('51', $group->layerGroupId);
    }

    public function testRejectsConflictingAttributeAndRelationshipIds(): void
    {
        try {
            $this->fetchGroup([
                'type' => 'groups',
                'id' => '51',
                'attributes' => [
                    'name' => 'Pfadi Zytturm',
                    'type' => 'Group::Abteilung',
                    'parent_id' => 50,
                    'layer_group_id' => 51,
                ],
                'relationships' => [
                    'parent' => ['meta' => ['included' => true], 'data' => ['type' => 'groups', 'id' => '49']],
                    'layer_group' => ['meta' => ['included' => true], 'data' => ['type' => 'groups', 'id' => '51']],
                ],
            ]);
            self::fail('Expected conflicting parent ids to be rejected');
        } catch (HitobitoApiException $exception) {
            self::assertSame('malformed_response', $exception->reason);
            self::assertSame('parent_id_conflict', $exception->getPrevious()?->reasonCode);
        }
    }

    public function testRejectsPresentButInvalidRelationshipData(): void
    {
        try {
            $this->fetchGroup([
                'type' => 'groups',
                'id' => '51',
                'attributes' => [
                    'name' => 'Pfadi Zytturm',
                    'type' => 'Group::Abteilung',
                    'parent_id' => 50,
                ],
                'relationships' => [
                    'parent' => ['meta' => ['included' => true], 'data' => 'invalid'],
                ],
            ]);
            self::fail('Expected invalid parent relationship data to be rejected');
        } catch (HitobitoApiException $exception) {
            self::assertSame('malformed_response', $exception->reason);
            self::assertInstanceOf(HitobitoGroupParseException::class, $exception->getPrevious());
            self::assertSame('invalid_parent_relationship', $exception->getPrevious()->reasonCode);
        }
    }

    public function testAllowsNullParentAndLayerGroupWhenBothSourcesAreNull(): void
    {
        $group = $this->fetchGroup([
            'type' => 'groups',
            'id' => '51',
            'attributes' => [
                'name' => 'Pfadi Zytturm',
                'type' => 'Group::Abteilung',
                'parent_id' => null,
                'layer_group_id' => null,
            ],
            'relationships' => [
                'parent' => ['data' => null],
                'layer_group' => ['data' => null],
            ],
        ]);

        self::assertNull($group->parentId);
        self::assertNull($group->layerGroupId);
    }

    public function testAllowsParentAndLayerGroupWhenBothSourcesAreAbsent(): void
    {
        $group = $this->fetchGroup([
            'type' => 'groups',
            'id' => '51',
            'attributes' => [
                'name' => 'Pfadi Zytturm',
                'type' => 'Group::Abteilung',
            ],
        ]);

        self::assertNull($group->parentId);
        self::assertNull($group->layerGroupId);
    }

    public function testMalformedGroupLogsOnlyAConciseParserWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $warnings = [];
        $logger->expects(self::never())->method('info');
        $logger->expects(self::once())->method('warning')->willReturnCallback(
            static function (string $message, array $context = []) use (&$warnings): void {
                $warnings[] = $message . json_encode($context);
            },
        );
        $response = [
            'meta' => ['trace_id' => 'must-not-be-logged'],
            'data' => [
                'type' => 'group',
                'id' => '51',
                'attributes' => [
                    'name' => 'Pfadi Zytturm',
                    'group_type' => 'Group::Abteilung',
                    'parent_id' => null,
                    'layer_group_id' => 1,
                    'private_attribute' => 'must-not-be-logged',
                ],
                'relationships' => [
                    'parent' => ['data' => ['type' => 'groups', 'id' => '20']],
                    'layer_group' => ['data' => ['type' => 'groups', 'id' => '1']],
                ],
            ],
        ];
        $client = new HitobitoApiClient(
            new MockHttpClient([new MockResponse(json_encode($response, JSON_THROW_ON_ERROR))]),
            ['midata' => 'https://db.scout.ch'],
            $logger,
        );

        try {
            $client->getGroup('midata', 'secret-token-not-to-log', '51');
            self::fail('Expected an invalid resource type to fail validation');
        } catch (HitobitoApiException $exception) {
            self::assertSame('malformed_response', $exception->reason);
        }

        self::assertCount(1, $warnings);
        self::assertStringContainsString('MiData group parser failed', $warnings[0]);
        self::assertStringContainsString('requested_group_id=51', $warnings[0]);
        self::assertStringContainsString('parser_reason=invalid_resource_type', $warnings[0]);
        self::assertStringNotContainsString('must-not-be-logged', $warnings[0]);
        self::assertStringNotContainsString('secret-token-not-to-log', $warnings[0]);
        self::assertStringNotContainsString('Pfadi Zytturm', $warnings[0]);
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

    public function testNormalizesRolesUsingHitobitoJsonApiRelationshipsAndAttributes(): void
    {
        $resource = [
            'type' => 'roles',
            'id' => 'role-123',
            'attributes' => [
                'role_type' => 'Leitung',
                'role_class' => 'Group::Leader',
                'label' => 'Stufenleiter*in PTA',
                'start_on' => '2025-01-01',
                'end_on' => null,
            ],
            'relationships' => [
                'group' => ['data' => ['type' => 'groups', 'id' => '111']],
            ],
        ];
        $http = new MockHttpClient([
            new MockResponse(json_encode(['data' => [$resource]], JSON_THROW_ON_ERROR)),
        ]);

        $roles = $this->client($http)->getRolesForPerson('midata', 'token', '1131');

        self::assertCount(1, $roles);
        self::assertSame('1131', $roles[0]->personId);
        self::assertSame('role-123', $roles[0]->id);
        self::assertSame('111', $roles[0]->groupId);
        self::assertSame('Group::Leader', $roles[0]->type);
        self::assertSame('Leitung', $roles[0]->roleType);
        self::assertSame('Stufenleiter*in PTA', $roles[0]->label);
        self::assertSame('2025-01-01', $roles[0]->startOn?->format('Y-m-d'));
        self::assertNull($roles[0]->endOn);
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
        return new HitobitoApiClient($http, ['midata' => 'https://db.scout.ch'], new NullLogger());
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function fetchGroup(array $resource): HitobitoGroup
    {
        $client = $this->client(new MockHttpClient([
            new MockResponse(json_encode(['data' => $resource], JSON_THROW_ON_ERROR)),
        ]));
        $group = $client->getGroup('midata', 'token', (string) $resource['id']);
        self::assertNotNull($group);

        return $group;
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
