<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\Group;
use App\Entity\MiDataDepartmentOnboarding;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\User;
use App\Repository\DepartmentRepository;
use App\Repository\ExternalStructureIdentityRepository;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\AuditLogger;
use App\Service\Auth\HitobitoGroup;
use App\Service\Auth\HitobitoRole;
use App\Service\Auth\MiDataDepartmentStructureProvisioner;
use App\Service\Auth\MiDataMaterialwartVerification;
use App\Service\Auth\MiDataMaterialwartVerificationStatus;
use App\Service\Auth\MiDataMaterialwartVerifier;
use App\Service\Auth\MiDataStructureConflictException;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use App\Util\DepartmentNameMatcher;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class MiDataDepartmentStructureProvisionerTest extends TestCase
{
    /** @var array<string, ExternalStructureIdentity> */
    private array $mappings = [];
    /** @var list<Organisation> */
    private array $organisations = [];
    /** @var list<Department> */
    private array $departments = [];
    /** @var list<Membership> */
    private array $memberships = [];
    /** @var list<object> */
    private array $persisted = [];
    /** @var list<string> */
    private array $events = [];
    private User $user;
    private Organisation $bundOrganisation;

    protected function setUp(): void
    {
        $this->user = (new User())->setId('user00000001');
        $this->bundOrganisation = $this->existingOrganisation('Pfadibewegung Schweiz', '1');
        $this->existingDepartment($this->bundOrganisation, 'Pfadi Zürich', null);
    }

    public function testMissingSubstructureIsCreatedAsDepartmentsInTheBundOrganisation(): void
    {
        $onboarding = new MiDataDepartmentOnboarding();

        $result = $this->provisioner()->provision($this->user, $this->verification(), $onboarding);

        self::assertSame(['49', '50', '51'], $result->createdExternalGroupIds);
        self::assertTrue($result->departmentCreated);
        self::assertTrue($result->membershipCreated);
        self::assertCount(1, $this->organisations, 'no Organisation is created');
        self::assertSame([], array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof Organisation));

        $kantonalverband = $this->mappings['49']->getDepartment();
        $region = $this->mappings['50']->getDepartment();
        $abteilung = $this->mappings['51']->getDepartment();
        self::assertNotNull($kantonalverband);
        self::assertNotNull($region);
        self::assertSame($result->department, $abteilung);
        self::assertSame('Pfadi Luzern', $kantonalverband->getName());
        self::assertSame('Corps Musegg', $region->getName());
        self::assertSame('Pfadi Zytturm', $abteilung->getName());
        foreach ([$kantonalverband, $region, $abteilung] as $department) {
            self::assertSame($this->bundOrganisation->getId(), $department->getOrganisationId());
        }
        self::assertNull($kantonalverband->getParentId());
        self::assertSame($kantonalverband->getId(), $region->getParentId());
        self::assertSame($region->getId(), $abteilung->getParentId());
        self::assertSame('Group::Kantonalverband', $this->mappings['49']->getExternalType());
        self::assertSame('1', $this->mappings['49']->getExternalParentId());

        self::assertSame($this->bundOrganisation, $this->mappings['1']->getOrganisation(), 'Bund mapping is unchanged');
        self::assertArrayNotHasKey('1113', $this->mappings, 'technical Root is never mapped');
        self::assertCount(1, $this->memberships);
        self::assertSame('mw', $this->memberships[0]->getRole());
        self::assertTrue($this->memberships[0]->getIsPrimary());
        self::assertSame($abteilung->getId(), $this->memberships[0]->getDepartmentId());
        self::assertNotNull($onboarding->getCompletedAt());
    }

    public function testStructureLockIsTakenBeforeAnyMappingIsRead(): void
    {
        $this->provisioner()->provision($this->user, $this->verification());

        self::assertSame('lock:midata:49', $this->events[0]);
        self::assertSame('lookup:1', $this->events[1]);
    }

    public function testMissingBundMappingStopsWithoutAnyStructureChange(): void
    {
        unset($this->mappings['1']);

        $this->expectConflict('bund_not_mapped', '1');
        self::assertSame([], $this->persisted);
    }

    public function testBundMappedToADepartmentIsRefused(): void
    {
        $this->mappings['1'] = (new ExternalStructureIdentity())
            ->setProvider('midata')
            ->setExternalGroupId('1')
            ->setDepartment($this->departments[0]);

        $this->expectConflict('bund_mapping_target', '1');
        self::assertSame([], $this->persisted);
    }

    public function testExistingKantonalverbandMappingIsReused(): void
    {
        $kantonalverband = $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null, '49');

        $result = $this->provisioner()->provision($this->user, $this->verification());

        self::assertSame(['50', '51'], $result->createdExternalGroupIds);
        self::assertSame($kantonalverband->getId(), $this->mappings['50']->getDepartment()?->getParentId());
    }

    public function testExistingKantonalverbandAndRegionOnlyCreateTheAbteilung(): void
    {
        $kantonalverband = $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null, '49');
        $region = $this->existingDepartment($this->bundOrganisation, 'Corps Musegg', $kantonalverband, '50');

        $result = $this->provisioner()->provision($this->user, $this->verification());

        self::assertSame(['51'], $result->createdExternalGroupIds);
        self::assertSame($region->getId(), $result->department->getParentId());
    }

    public function testCorrectlyMappedAbteilungIsReusedAndRepeatedRunsAreIdempotent(): void
    {
        $kantonalverband = $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null, '49');
        $region = $this->existingDepartment($this->bundOrganisation, 'Corps Musegg', $kantonalverband, '50');
        $abteilung = $this->existingDepartment($this->bundOrganisation, 'Pfadi Zytturm', $region, '51');

        $first = $this->provisioner()->provision($this->user, $this->verification());
        $second = $this->provisioner()->provision($this->user, $this->verification());

        self::assertSame([], $first->createdExternalGroupIds);
        self::assertFalse($first->departmentCreated);
        self::assertTrue($first->membershipCreated);
        self::assertSame($abteilung, $first->department);
        self::assertFalse($second->membershipCreated);
        self::assertCount(4, $this->departments);
        self::assertCount(4, $this->mappings);
        self::assertCount(1, $this->memberships);
    }

    public function testDoubleSubmitOfMissingSubstructureCreatesNoDuplicates(): void
    {
        $this->provisioner()->provision($this->user, $this->verification());
        $second = $this->provisioner()->provision($this->user, $this->verification());

        self::assertSame([], $second->createdExternalGroupIds);
        self::assertFalse($second->membershipCreated);
        self::assertCount(4, $this->departments);
        self::assertCount(4, $this->mappings);
        self::assertCount(1, $this->memberships);
    }

    public function testAbteilungWithoutRegionIsCreatedBelowTheKantonalverband(): void
    {
        $result = $this->provisioner()->provision($this->user, $this->verification(regions: []));

        self::assertSame(['49', '51'], $result->createdExternalGroupIds);
        self::assertSame($this->mappings['49']->getDepartment()?->getId(), $result->department->getParentId());
    }

    public function testSeveralRegionLevelsFormTheParentChain(): void
    {
        $regions = [
            new HitobitoGroup('50', '49', 'Group::Region', 'Corps Musegg'),
            new HitobitoGroup('91', '50', 'Group::Region', 'Unterregion'),
        ];

        $result = $this->provisioner()->provision($this->user, $this->verification(regions: $regions));

        self::assertSame(['49', '50', '91', '51'], $result->createdExternalGroupIds);
        self::assertSame($this->mappings['49']->getDepartment()?->getId(), $this->mappings['50']->getDepartment()?->getParentId());
        self::assertSame($this->mappings['50']->getDepartment()?->getId(), $this->mappings['91']->getDepartment()?->getParentId());
        self::assertSame($this->mappings['91']->getDepartment()?->getId(), $result->department->getParentId());
    }

    public function testExistingMembershipRoleIsNeverChanged(): void
    {
        $kantonalverband = $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null, '49');
        $region = $this->existingDepartment($this->bundOrganisation, 'Corps Musegg', $kantonalverband, '50');
        $abteilung = $this->existingDepartment($this->bundOrganisation, 'Pfadi Zytturm', $region, '51');
        $membership = (new Membership())->setUser($this->user)->setDepartment($abteilung)->setRole('dc');
        $this->memberships[] = $membership;

        $result = $this->provisioner()->provision($this->user, $this->verification());

        self::assertFalse($result->membershipCreated);
        self::assertSame('dc', $membership->getRole());
        self::assertCount(1, $this->memberships);
    }

    public function testVerifiedAbteilungsleitungCreatesDcMembership(): void
    {
        $verification = $this->verification();
        $dc = new MiDataMaterialwartVerification(
            MiDataMaterialwartVerificationStatus::CONFIRMED,
            '1131',
            $verification->department,
            $verification->regions,
            $verification->kantonalverband,
            $verification->bund,
            new HitobitoRole('1131', '51', 'Group::Abteilung::Abteilungsleitung', null, null),
        );

        $this->provisioner()->provision($this->user, $dc);

        self::assertSame('dc', $this->memberships[0]->getRole());
    }

    public function testAdditionalMembershipIsNotPrimary(): void
    {
        $this->memberships[] = (new Membership())->setUser($this->user)->setDepartment($this->departments[0])->setRole('u')->setIsPrimary(true);

        $this->provisioner()->provision($this->user, $this->verification());

        self::assertFalse($this->memberships[1]->getIsPrimary());
    }

    public function testDepartmentMappingInAnotherOrganisationIsAConflict(): void
    {
        $this->existingDepartment($this->existingOrganisation('Andere'), 'Pfadi Luzern', null, '49');

        $this->expectConflict('department_mapping_hierarchy', '49');
    }

    public function testKantonalverbandMappingWithAParentIsAConflict(): void
    {
        $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', $this->departments[0], '49');

        $this->expectConflict('department_mapping_hierarchy', '49');
    }

    public function testRegionMappedBelowTheWrongParentIsAConflict(): void
    {
        $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null, '49');
        $this->existingDepartment($this->bundOrganisation, 'Corps Musegg', null, '50');

        $this->expectConflict('department_mapping_hierarchy', '50');
    }

    public function testAbteilungMappedToAGroupIsAConflict(): void
    {
        $kantonalverband = $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null, '49');
        $this->existingDepartment($this->bundOrganisation, 'Corps Musegg', $kantonalverband, '50');
        $this->mappings['51'] = (new ExternalStructureIdentity())
            ->setProvider('midata')
            ->setExternalGroupId('51')
            ->setGroup(new Group());

        $this->expectConflict('department_mapping_hierarchy', '51');
    }

    public function testAbteilungMappedToAGrossanlassIsAConflict(): void
    {
        $kantonalverband = $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null, '49');
        $region = $this->existingDepartment($this->bundOrganisation, 'Corps Musegg', $kantonalverband, '50');
        $this->existingDepartment($this->bundOrganisation, 'Pfadi Zytturm', $region, '51')->setIsGrossanlass(true);

        $this->expectConflict('department_mapping_hierarchy', '51');
    }

    public function testSameNameBelowAnotherParentIsNoConflict(): void
    {
        $this->existingDepartment($this->bundOrganisation, 'Pfadi Zytturm', $this->departments[0]);
        $this->existingDepartment($this->bundOrganisation, 'Corps Musegg', $this->departments[0]);

        $result = $this->provisioner()->provision($this->user, $this->verification());

        self::assertSame(['49', '50', '51'], $result->createdExternalGroupIds);
    }

    public function testSameRootNameInAnotherOrganisationIsNoConflict(): void
    {
        $this->existingDepartment($this->existingOrganisation('Andere'), 'Pfadi Luzern', null);

        $result = $this->provisioner()->provision($this->user, $this->verification());

        self::assertSame(['49', '50', '51'], $result->createdExternalGroupIds);
    }

    public function testSameNameBelowTheSameParentIsAConflict(): void
    {
        $kantonalverband = $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null, '49');
        $region = $this->existingDepartment($this->bundOrganisation, 'Corps Musegg', $kantonalverband, '50');
        $this->existingDepartment($this->bundOrganisation, 'Pfadi Zytturm', $region);

        $this->expectConflict('department_name_conflict', '51');
    }

    public function testUnmappedRootDepartmentWithTheSameNameIsNeverLinkedByName(): void
    {
        $this->existingDepartment($this->bundOrganisation, 'Pfadi Luzern', null);

        $this->expectConflict('department_name_conflict', '49');
    }

    public function testUnconfirmedVerificationIsRejectedBeforeAnyWrite(): void
    {
        $this->expectException(\LogicException::class);

        try {
            $this->provisioner()->provision(
                $this->user,
                new MiDataMaterialwartVerification(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED),
            );
        } finally {
            self::assertSame([], $this->events);
        }
    }

    private function expectConflict(string $reason, string $externalGroupId): void
    {
        try {
            $this->provisioner()->provision($this->user, $this->verification());
            self::fail('Expected a structure conflict');
        } catch (MiDataStructureConflictException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertSame($externalGroupId, $exception->externalGroupId);
        }
        self::assertSame([], $this->memberships);
    }

    /**
     * @param list<HitobitoGroup>|null $regions
     */
    private function verification(?array $regions = null): MiDataMaterialwartVerification
    {
        $regions ??= [new HitobitoGroup('50', '49', 'Group::Region', 'Corps Musegg', '50')];
        $parentId = $regions !== [] ? $regions[array_key_last($regions)]->id : '49';

        return new MiDataMaterialwartVerification(
            MiDataMaterialwartVerificationStatus::CONFIRMED,
            '1131',
            new HitobitoGroup('51', $parentId, 'Group::Abteilung', 'Pfadi Zytturm', '51'),
            $regions,
            new HitobitoGroup('49', '1', 'Group::Kantonalverband', 'Pfadi Luzern', '49'),
            new HitobitoGroup('1', '1113', 'Group::Bund', 'Pfadibewegung Schweiz', '1'),
            new HitobitoRole('1131', '51', MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS, null, null),
        );
    }

    private function existingOrganisation(string $name, ?string $externalGroupId = null): Organisation
    {
        $organisation = (new Organisation())->setId(sprintf('org%09d', count($this->organisations) + 1));
        $organisation->setName($name);
        $this->organisations[] = $organisation;
        if ($externalGroupId !== null) {
            $this->mappings[$externalGroupId] = (new ExternalStructureIdentity())
                ->setProvider('midata')
                ->setExternalGroupId($externalGroupId)
                ->setOrganisation($organisation);
        }

        return $organisation;
    }

    private function existingDepartment(
        Organisation $organisation,
        string $name,
        ?Department $parent,
        ?string $externalGroupId = null,
    ): Department {
        $department = (new Department())->setId(sprintf('dep%09d', count($this->departments) + 1));
        $department->setName($name);
        $department->setOrganisation($organisation);
        $department->setParent($parent);
        $this->departments[] = $department;
        if ($externalGroupId !== null) {
            $this->mappings[$externalGroupId] = (new ExternalStructureIdentity())
                ->setProvider('midata')
                ->setExternalGroupId($externalGroupId)
                ->setDepartment($department);
        }

        return $department;
    }

    private function provisioner(): MiDataDepartmentStructureProvisioner
    {
        $genericRepository = $this->createMock(EntityRepository::class);
        $genericRepository->method('findOneBy')->willReturnCallback(function (array $criteria): ?object {
            if (isset($criteria['userId'], $criteria['departmentId'])) {
                foreach ($this->memberships as $membership) {
                    if (
                        $membership->getUserId() === $criteria['userId']
                        && $membership->getDepartmentId() === $criteria['departmentId']
                    ) {
                        return $membership;
                    }
                }
            }

            return null;
        });
        $genericRepository->method('count')->willReturnCallback(fn (array $criteria): int => count(array_filter(
            $this->memberships,
            static fn (Membership $membership): bool => $membership->getUserId() === $criteria['userId'],
        )));

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getRepository')->willReturn($genericRepository);
        $entityManager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback): mixed => $callback($entityManager),
        );
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
            match (true) {
                $entity instanceof ExternalStructureIdentity => $this->mappings[$entity->getExternalGroupId()] = $entity,
                $entity instanceof Organisation => $this->organisations[] = $entity,
                $entity instanceof Department => $this->departments[] = $entity,
                $entity instanceof Membership => $this->memberships[] = $entity,
                default => null,
            };
        });

        $structureIdentities = $this->createMock(ExternalStructureIdentityRepository::class);
        $structureIdentities->method('lockExternalGroupForStructureChange')->willReturnCallback(
            function (string $provider, string $externalGroupId): void {
                $this->events[] = 'lock:' . $provider . ':' . $externalGroupId;
            },
        );
        $structureIdentities->method('findOneByProviderAndExternalGroupId')->willReturnCallback(
            function (string $provider, string $externalGroupId): ?ExternalStructureIdentity {
                $this->events[] = 'lookup:' . $externalGroupId;

                return $provider === 'midata' ? ($this->mappings[$externalGroupId] ?? null) : null;
            },
        );

        $departmentRepository = $this->createMock(DepartmentRepository::class);
        $departmentRepository->method('findConflictingSiblingByName')->willReturnCallback(
            function (string $organisationId, ?string $parentId, string $name): ?Department {
                foreach ($this->departments as $department) {
                    if (
                        $department->getOrganisationId() === $organisationId
                        && $department->getParentId() === $parentId
                        && DepartmentNameMatcher::conflict($name, $department->getName())
                    ) {
                        return $department;
                    }
                }

                return null;
            },
        );

        return new MiDataDepartmentStructureProvisioner(
            $entityManager,
            $structureIdentities,
            $departmentRepository,
            $this->createMock(AccountingCostCenterBootstrapService::class),
            $this->createMock(WorkshopSparePartsCategoryBootstrapService::class),
            $this->createMock(AuditLogger::class),
        );
    }
}
