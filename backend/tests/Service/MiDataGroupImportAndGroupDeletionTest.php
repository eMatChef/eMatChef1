<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\ExternalStructureIdentityRepository;
use App\Service\Auth\HitobitoApiClient;
use App\Service\Auth\MiDataDepartmentMembershipVerifier;
use App\Service\Auth\MiDataGroupImportService;
use App\Service\Auth\PbsGroupTypeClassifier;
use App\Service\GroupDeletionService;
use App\Service\GroupNotDeletableException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;

final class MiDataGroupImportAndGroupDeletionTest extends TestCase
{
    private EntityManager&MockObject $em;
    private ExternalStructureIdentityRepository&MockObject $mappings;
    private ArrayAdapter $cache;
    private \App\Repository\ExternalIdentityRepository&MockObject $identities;
    private Department $department;
    private User $user;
    /** @var array<string, ExternalStructureIdentity> */
    private array $mapped = [];
    /** @var list<object> */
    private array $persisted = [];
    /** @var list<object> */
    private array $removed = [];
    private int $memberCount = 0;
    private int $childCount = 0;

    protected function setUp(): void
    {
        $this->department = (new Department())->setId('dept00000001');
        $this->user = (new User())->setId('user00000001');
        $this->cache = new ArrayAdapter();
        $this->identities = $this->createMock(\App\Repository\ExternalIdentityRepository::class);
        $this->identities->method('findOneBy')->willReturn(new \App\Entity\ExternalIdentity());

        $membership = new Membership();
        $membership->setRole('mw');
        $memberships = $this->createMock(EntityRepository::class);
        $memberships->method('findOneBy')->willReturn($membership);
        $groupMemberships = $this->createMock(EntityRepository::class);
        $groupMemberships->method('count')->willReturnCallback(fn (): int => $this->memberCount);
        $groups = $this->createMock(EntityRepository::class);
        $groups->method('count')->willReturnCallback(fn (): int => $this->childCount);
        $groups->method('findOneBy')->willReturn(null);
        $generic = $this->createMock(EntityRepository::class);
        $generic->method('findOneBy')->willReturn(null);

        $this->em = $this->createMock(EntityManager::class);
        $this->em->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => match ($class) {
                Membership::class => $memberships,
                GroupMembership::class => $groupMemberships,
                Group::class => $groups,
                default => $generic,
            },
        );
        $this->em->method('wrapInTransaction')->willReturnCallback(static fn (callable $fn): mixed => $fn());
        $this->em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
            if ($entity instanceof ExternalStructureIdentity) {
                $this->mapped[$entity->getExternalGroupId()] = $entity;
            }
        });
        $this->em->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });

        $root = (new ExternalStructureIdentity())->setProvider('midata')->setExternalGroupId('100')
            ->setExternalType('Group::Abteilung')->setDepartment($this->department);
        $this->mappings = $this->createMock(ExternalStructureIdentityRepository::class);
        $this->mappings->method('findByDepartment')->willReturn([$root]);
        $this->mappings->method('findOneByProviderAndExternalGroupId')->willReturnCallback(
            fn (string $provider, string $id): ?ExternalStructureIdentity => $this->mapped[$id] ?? null,
        );
        $this->mappings->method('findByGroup')->willReturnCallback(
            fn (Group $group): array => array_values(array_filter(
                $this->mapped,
                static fn (ExternalStructureIdentity $m): bool => $m->getGroup() === $group,
            )),
        );
    }

    private function importService(): MiDataGroupImportService
    {
        return new MiDataGroupImportService(
            $this->em,
            $this->mappings,
            $this->identities,
            $this->createMock(MiDataDepartmentMembershipVerifier::class),
            new HitobitoApiClient(new MockHttpClient(), ['midata' => 'https://db.scout.ch'], new NullLogger()),
            new PbsGroupTypeClassifier(),
            $this->cache,
        );
    }

    private function seedSnapshot(): void
    {
        $item = $this->cache->getItem('midata_group_import_user00000001_dept00000001');
        $item->set(['root' => '100', 'nodes' => [
            ['external_group_id' => '201', 'parent_external_group_id' => '100', 'name' => 'Pfadi', 'type' => 'Group::Pfadi'],
            ['external_group_id' => '301', 'parent_external_group_id' => '201', 'name' => 'Trupp', 'type' => 'Group::Pfadi'],
        ]]);
        $this->cache->save($item);
    }

    public function testImportCreatesHierarchyAndMappingsAndRejectsUnknownIds(): void
    {
        $this->seedSnapshot();
        $service = $this->importService();

        self::assertSame('invalid_selection', $service->import($this->user, $this->department, ['999'])['status']);

        $result = $service->import($this->user, $this->department, ['301']); // parent 201 is added
        self::assertSame(2, $result['imported']);
        $groups = array_values(array_filter($this->persisted, static fn (object $o): bool => $o instanceof Group));
        self::assertCount(2, $groups);
        self::assertNull($groups[0]->getParent());
        self::assertSame($groups[0], $groups[1]->getParent());
        self::assertSame($groups[1], $this->mapped['301']->getGroup());
        self::assertSame('midata', $this->mapped['301']->getProvider());
    }

    public function testSecondImportDoesNotCreateOrOverwrite(): void
    {
        $this->seedSnapshot();
        $service = $this->importService();
        $service->import($this->user, $this->department, ['201']);
        $mapping = $this->mapped['201'];
        $count = count($this->persisted);

        $result = $service->import($this->user, $this->department, ['201']);

        self::assertSame(0, $result['imported']);
        self::assertSame(1, $result['skipped']);
        self::assertCount($count, $this->persisted);
        self::assertSame($mapping, $this->mapped['201']);
    }

    public function testReopenedPreviewMarksImportedGroupsAsImported(): void
    {
        $this->seedSnapshot();
        $service = $this->importService();
        $service->import($this->user, $this->department, ['201']);

        $byId = [];
        foreach ($service->getTree($this->user, $this->department) as $node) {
            $byId[$node['external_group_id']] = $node['imported'];
        }

        self::assertSame(['201' => true, '301' => false], $byId);
    }

    public function testDeleteBlockedWithMemberOrChild(): void
    {
        $group = (new Group())->setId('grp000000001');
        $deletion = new GroupDeletionService($this->em, $this->mappings);

        $this->memberCount = 1;
        try {
            $deletion->delete($group);
            self::fail('expected exception');
        } catch (GroupNotDeletableException $e) {
            self::assertSame('has_members', $e->reason);
        }

        $this->memberCount = 0;
        $this->childCount = 1;
        try {
            $deletion->delete($group);
            self::fail('expected exception');
        } catch (GroupNotDeletableException $e) {
            self::assertSame('has_children', $e->reason);
        }
        self::assertSame([], $this->removed);
    }

    public function testEmptyImportedGroupIsDeletedWithItsMapping(): void
    {
        $this->seedSnapshot();
        $this->importService()->import($this->user, $this->department, ['201']);
        $mapping = $this->mapped['201'];
        $group = $mapping->getGroup();

        (new GroupDeletionService($this->em, $this->mappings))->delete($group);

        self::assertContains($mapping, $this->removed);
        self::assertContains($group, $this->removed);
    }

    public function testCallbackSnapshotIsFoundByLaterPreview(): void
    {
        $child = static fn (string $id, string $parent, string $type, string $name): array => [
            'id' => $id,
            'type' => 'groups',
            'attributes' => ['name' => $name, 'group_type' => $type, 'parent_id' => $parent],
        ];
        $http = new MockHttpClient(static fn (string $method, string $url): \Symfony\Component\HttpClient\Response\MockResponse => new \Symfony\Component\HttpClient\Response\MockResponse(
            json_encode(['data' => str_contains($url, '100')
                ? [$child('201', '100', 'Group::Pfadi', 'Pfadi'), $child('202', '100', 'Group::Elternrat', 'Elternrat')]
                : []], JSON_THROW_ON_ERROR),
            ['response_headers' => ['content-type: application/vnd.api+json']],
        ));
        $verifier = $this->createMock(MiDataDepartmentMembershipVerifier::class);
        $verifier->method('verify')->willReturn(new \App\Service\Auth\MiDataDepartmentVerificationResult(
            \App\Service\Auth\MiDataDepartmentVerificationStatus::CONFIRMED,
        ));
        // Two service instances over one real filesystem pool: write in the "callback", read in a later "request".
        $pool = new \Symfony\Component\Cache\Adapter\FilesystemAdapter('midata_test', 0, sys_get_temp_dir() . '/midata_import_' . uniqid());
        $make = fn (): MiDataGroupImportService => new MiDataGroupImportService(
            $this->em,
            $this->mappings,
            $this->identities,
            $verifier,
            new HitobitoApiClient($http, ['midata' => 'https://db.scout.ch'], new NullLogger()),
            new PbsGroupTypeClassifier(),
            $pool,
        );
        $session = new \App\Service\Auth\HitobitoOAuthSession('midata', 'token', new \App\Service\Auth\MiDataOAuthUserInfo('1', null, false, null, null));

        self::assertSame('ok', $make()->loadSnapshotFromOAuthCallback($this->user, $this->department, $session));
        $tree = $make()->getTree($this->user, $this->department);

        self::assertNotNull($tree);
        self::assertSame(['201'], array_column($tree, 'external_group_id'));
    }
}
