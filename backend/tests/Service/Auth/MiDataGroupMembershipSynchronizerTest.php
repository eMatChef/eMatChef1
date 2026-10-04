<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Repository\ExternalStructureIdentityRepository;
use App\Service\Auth\HitobitoRole;
use App\Service\Auth\MiDataDepartmentVerificationResult;
use App\Service\Auth\MiDataDepartmentVerificationStatus;
use App\Service\Auth\MiDataGroupMembershipSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class MiDataGroupMembershipSynchronizerTest extends TestCase
{
    public function testCreatesOnlyTheMappedDirectGroupAndSkipsTheDepartmentRole(): void
    {
        $department = $this->department('department-1');
        $directGroup = $this->group('grp-00000001', $department);
        $parentGroup = $this->group('grp-00000002', $department);
        $fixture = $this->synchronizer([
            '111' => $this->mapping('111', $directGroup),
            '110' => $this->mapping('110', $parentGroup),
            '100' => $this->mapping('100', $this->group('grp-00000003', $department)),
        ]);

        $result = $fixture['service']->sync(
            $this->user(),
            $department,
            $this->verification([$this->role('111'), $this->role('100')]),
        );

        self::assertSame([$directGroup->getId()], $result->created);
        self::assertSame([], $result->existing);
        self::assertSame([], $result->unmapped);
        self::assertSame([], $result->conflicts);
        self::assertCount(1, $fixture['persisted']);
        self::assertSame('member', $fixture['persisted'][0]->getRole());
        self::assertFalse($fixture['persisted'][0]->getCanProcure());
        self::assertFalse($fixture['persisted'][0]->getIsPrimary());
        self::assertSame($directGroup, $fixture['persisted'][0]->getGroup());
    }

    public function testMultipleGroupsDeduplicateExternalRolesAndRepeatedSyncIsIdempotent(): void
    {
        $department = $this->department('department-1');
        $firstGroup = $this->group('grp-00000001', $department);
        $secondGroup = $this->group('grp-00000002', $department);
        $fixture = $this->synchronizer([
            '111' => $this->mapping('111', $firstGroup),
            '222' => $this->mapping('222', $secondGroup),
        ]);
        $fixture['entityManager']->expects(self::once())->method('flush');
        $user = $this->user();
        $verification = $this->verification([
            $this->role('111'),
            $this->role('111'),
            $this->role('222'),
        ]);

        $firstResult = $fixture['service']->sync($user, $department, $verification);
        $secondResult = $fixture['service']->sync($user, $department, $verification);

        self::assertSame([$firstGroup->getId(), $secondGroup->getId()], $firstResult->created);
        self::assertSame([], $firstResult->existing);
        self::assertSame([], $secondResult->created);
        self::assertSame([$firstGroup->getId(), $secondGroup->getId()], $secondResult->existing);
        self::assertCount(2, $fixture['persisted']);
    }

    public function testUnmappedExternalGroupsAreReportedWithoutCreatingGroupsOrMemberships(): void
    {
        $department = $this->department('department-1');
        $sameNameGroup = $this->group('grp-00000001', $department);
        $sameNameGroup->setName('External group 111');
        $wrongProviderMapping = $this->mapping('111', $sameNameGroup)->setProvider('cevidb');
        $fixture = $this->synchronizer(['111' => $wrongProviderMapping]);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');

        $result = $fixture['service']->sync(
            $this->user(),
            $department,
            $this->verification([$this->role('111')]),
        );

        self::assertSame(['111'], $result->unmapped);
        self::assertSame([], $result->created);
        self::assertSame([], $result->conflicts);
    }

    public function testDepartmentTargetMappingIsNotTreatedAsAGroupMapping(): void
    {
        $department = $this->department('department-1');
        $departmentTargetMapping = (new ExternalStructureIdentity())
            ->setProvider('midata')
            ->setExternalGroupId('111')
            ->setDepartment($department);
        $fixture = $this->synchronizer(['111' => $departmentTargetMapping]);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');

        $result = $fixture['service']->sync(
            $this->user(),
            $department,
            $this->verification([$this->role('111')]),
        );

        self::assertSame(['111'], $result->unmapped);
        self::assertSame([], $result->created);
    }

    public function testCrossDepartmentGroupMappingIsReportedAndIgnored(): void
    {
        $department = $this->department('department-1');
        $otherDepartment = $this->department('department-2');
        $foreignGroup = $this->group('grp-00000002', $otherDepartment);
        $fixture = $this->synchronizer(['111' => $this->mapping('111', $foreignGroup)]);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');

        $result = $fixture['service']->sync(
            $this->user(),
            $department,
            $this->verification([$this->role('111')]),
        );

        self::assertSame(['111'], $result->conflicts);
        self::assertSame([], $result->created);
        self::assertSame([], $result->unmapped);
    }

    public function testExistingGroupMembershipPropertiesRemainUnchanged(): void
    {
        $department = $this->department('department-1');
        $memberGroup = $this->group('grp-00000001', $department);
        $leaderGroup = $this->group('grp-00000002', $department);
        $member = (new GroupMembership())
            ->setUser($this->user())
            ->setGroup($memberGroup)
            ->setRole('member')
            ->setCanProcure(true)
            ->setIsPrimary(true);
        $leader = (new GroupMembership())
            ->setUser($this->user())
            ->setGroup($leaderGroup)
            ->setRole('leader')
            ->setCanProcure(true)
            ->setIsPrimary(true);
        $fixture = $this->synchronizer(
            [
                '111' => $this->mapping('111', $memberGroup),
                '222' => $this->mapping('222', $leaderGroup),
            ],
            [$memberGroup->getId() => $member, $leaderGroup->getId() => $leader],
        );
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');

        $result = $fixture['service']->sync(
            $this->user(),
            $department,
            $this->verification([$this->role('111'), $this->role('222')]),
        );

        self::assertSame([$memberGroup->getId(), $leaderGroup->getId()], $result->existing);
        self::assertSame('member', $member->getRole());
        self::assertTrue($member->getCanProcure());
        self::assertTrue($member->getIsPrimary());
        self::assertSame('leader', $leader->getRole());
        self::assertTrue($leader->getCanProcure());
        self::assertTrue($leader->getIsPrimary());
    }

    public function testWrongPersonAndInactiveRolesAreNeverAdded(): void
    {
        $department = $this->department('department-1');
        $group = $this->group('grp-00000001', $department);
        $fixture = $this->synchronizer(['111' => $this->mapping('111', $group)]);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');
        $today = new \DateTimeImmutable('today');

        $result = $fixture['service']->sync(
            $this->user(),
            $department,
            $this->verification([
                $this->role('111', 'different-person'),
                $this->role('111', 'person-1', $today->modify('-2 days'), $today->modify('-1 day')),
            ]),
        );

        self::assertSame(['111'], $result->conflicts);
        self::assertSame([], $result->created);
    }

    #[DataProvider('unverifiedStatuses')]
    public function testUnverifiedDepartmentResultsDoNotChangeGroupMemberships(
        MiDataDepartmentVerificationStatus $status,
    ): void {
        $department = $this->department('department-1');
        $fixture = $this->synchronizer([]);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');

        $result = $fixture['service']->sync(
            $this->user(),
            $department,
            new MiDataDepartmentVerificationResult($status),
        );

        self::assertSame([], $result->created);
        self::assertSame([], $result->existing);
        self::assertSame([], $result->unmapped);
        self::assertSame([], $result->conflicts);
    }

    public static function unverifiedStatuses(): iterable
    {
        yield 'unavailable' => [MiDataDepartmentVerificationStatus::UNAVAILABLE];
        yield 'not confirmed' => [MiDataDepartmentVerificationStatus::NOT_CONFIRMED];
        yield 'not applicable' => [MiDataDepartmentVerificationStatus::NOT_APPLICABLE];
    }

    /**
     * @param array<string, ExternalStructureIdentity> $mappings
     * @param array<string, GroupMembership> $existingMemberships
     * @return array<string, mixed>
     */
    private function synchronizer(array $mappings, array $existingMemberships = []): array
    {
        $structureRepository = $this->createMock(ExternalStructureIdentityRepository::class);
        $structureRepository->method('findOneByProviderAndExternalGroupId')->willReturnCallback(
            static fn (string $provider, string $externalGroupId): ?ExternalStructureIdentity =>
                $provider === 'midata' ? ($mappings[$externalGroupId] ?? null) : null,
        );
        $groupMembershipRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $groupMembershipRepository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use (&$existingMemberships): ?GroupMembership {
                return $existingMemberships[$criteria['groupId']] ?? null;
            },
        );
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(GroupMembership::class)->willReturn($groupMembershipRepository);
        $persisted = [];
        $entityManager->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted, &$existingMemberships): void {
                if ($entity instanceof GroupMembership) {
                    $persisted[] = $entity;
                    $existingMemberships[$entity->getGroupId()] = $entity;
                }
            },
        );

        return [
            'service' => new MiDataGroupMembershipSynchronizer(
                $entityManager,
                $structureRepository,
                $this->createMock(LoggerInterface::class),
            ),
            'entityManager' => $entityManager,
            'persisted' => &$persisted,
        ];
    }

    private function verification(array $roles): MiDataDepartmentVerificationResult
    {
        return new MiDataDepartmentVerificationResult(
            MiDataDepartmentVerificationStatus::CONFIRMED,
            $roles[0] ?? null,
            $roles,
            '100',
            'person-1',
        );
    }

    private function mapping(string $externalGroupId, Group $group): ExternalStructureIdentity
    {
        return (new ExternalStructureIdentity())
            ->setProvider('midata')
            ->setExternalGroupId($externalGroupId)
            ->setGroup($group);
    }

    private function department(string $id): Department
    {
        return (new Department())->setId($id);
    }

    private function group(string $id, Department $department): Group
    {
        return (new Group())
            ->setId($id)
            ->setDepartment($department)
            ->setName('Group ' . $id);
    }

    private function user(): User
    {
        $user = new User();
        $user->setId('user-123456');

        return $user;
    }

    private function role(
        string $groupId,
        string $personId = 'person-1',
        ?\DateTimeImmutable $startOn = null,
        ?\DateTimeImmutable $endOn = null,
    ): HitobitoRole {
        return new HitobitoRole($personId, $groupId, 'Role::Member', $startOn, $endOn);
    }
}
