<?php

declare(strict_types=1);

namespace App\Tests\Service\Admin;

use App\Entity\Department;
use App\Entity\MaterialItem;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Admin\AdminCapabilityDepartmentScope;
use App\Service\ActivityAccessService;
use App\Service\Auth\AdminContextResolver;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Issue\IssuePhotoAccessService;
use App\Service\Material\MaterialPhotoAccessService;
use App\Service\Media\MediaFileAccessService;
use App\Service\Workshop\WorkshopPhotoAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Verwaltungszuständigkeit (Orgchef/Suborgchef) ist baumbasiert und getrennt von operativen Rollen:
 * Kantonalverband → (Materialverwaltung | Abteilung Süd → Aussenstelle), daneben ein fremdes Department.
 */
final class AdminDepartmentScopeAuthorizationTest extends TestCase
{
    private const KV = 'dept00000kv1';
    private const MAT = 'dept00000mat';
    private const SUED = 'dept0000sued';
    private const AUSSEN = 'dept0000auss';
    private const FOREIGN = 'dept0000fore';

    /** @var array<string, Department> */
    private array $departments = [];
    /** @var list<Membership> */
    private array $memberships = [];
    private string $materialDepartment = self::MAT;

    protected function setUp(): void
    {
        $organisation = (new Organisation())->setId('orga00000001');
        $other = (new Organisation())->setId('orga00000002');
        $this->departments = [
            self::KV => $this->department(self::KV, 'Demo Kantonalverband', $organisation),
            self::MAT => $this->department(self::MAT, 'Demo Materialverwaltung', $organisation, self::KV),
            self::SUED => $this->department(self::SUED, 'Demo Abteilung Süd', $organisation, self::KV),
            self::AUSSEN => $this->department(self::AUSSEN, 'Demo Abteilung Süd Aussenstelle', $organisation, self::SUED),
            self::FOREIGN => $this->department(self::FOREIGN, 'Echtes Department', $other),
        ];
    }

    public function testSuperadminAdministersEveryDepartment(): void
    {
        $checker = $this->checker();
        $superadmin = $this->user('sa', ['ROLE_USER', 'ROLE_SUPERADMIN']);

        foreach (array_keys($this->departments) as $id) {
            self::assertTrue($checker->canAdministerDepartment($superadmin, $id), $id);
        }
        self::assertFalse($checker->canAdministerDepartment($superadmin, ''), 'ohne Department keine Zuständigkeit');
        self::assertFalse($checker->canAdministerDepartment($superadmin, null));
    }

    public function testOrgchefAdministersOnlyTheSubtreeOfTheScopeRoot(): void
    {
        $checker = $this->checker();
        $orgchef = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::KV]);

        foreach ([self::KV, self::MAT, self::SUED, self::AUSSEN] as $id) {
            self::assertTrue($checker->canAdministerDepartment($orgchef, $id), $id);
        }
        self::assertFalse($checker->canAdministerDepartment($orgchef, self::FOREIGN), 'Department ausserhalb des Scopes');
    }

    public function testSuborgchefAdministersOnlyTheSubordinateBranch(): void
    {
        $checker = $this->checker();
        $suborgchef = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);

        self::assertTrue($checker->canAdministerDepartment($suborgchef, self::SUED));
        self::assertTrue($checker->canAdministerDepartment($suborgchef, self::AUSSEN), 'mehrstufig: Child des Child');
        self::assertFalse($checker->canAdministerDepartment($suborgchef, self::KV), 'übergeordnete Ebene gehört nicht dazu');
        self::assertFalse($checker->canAdministerDepartment($suborgchef, self::MAT), 'Geschwister-Zweig');
        self::assertFalse($checker->canAdministerDepartment($suborgchef, self::FOREIGN));
    }

    public function testAMembershipAloneNeverGrantsAdministrationAndAdministrationIsNoMembership(): void
    {
        $checker = $this->checker();
        // Materialchef (operative Rolle) im Kantonalverband: keine Verwaltungszuständigkeit
        $matwart = $this->user('mw', ['ROLE_USER', 'ROLE_MATWART']);
        $this->memberships[] = $this->membership($matwart, self::KV, 'mw');
        self::assertFalse($checker->canAdministerDepartment($matwart, self::KV));

        // Orgchef mit normaler User-Rolle in einem fremden Department: Verwaltung bleibt im Scope, die Rolle dort ist nur «u»
        $orgchef = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::KV]);
        $this->memberships[] = $this->membership($orgchef, self::FOREIGN, 'u');
        self::assertFalse($checker->canAdministerDepartment($orgchef, self::FOREIGN), 'normale Mitgliedschaft ist keine Verwaltungszuständigkeit');
        self::assertTrue($checker->canAdministerDepartment($orgchef, self::MAT));
        // … und die Verwaltungszuständigkeit im Kantonalverband macht ihn dort nicht zum Mitglied oder Materialchef
        self::assertSame([self::FOREIGN], array_map(static fn (Membership $m): string => $m->getDepartmentId(), $this->memberships(['org'])));
    }

    public function testOrgchefWithoutScopeIsUnrestrictedAsBefore(): void
    {
        // Dokumentiert die bestehende Semantik: leerer Scope = bewusst unbeschränkt (Einschränkung läuft über den Scope).
        $orgchef = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']);

        self::assertTrue($this->checker()->canAdministerDepartment($orgchef, self::FOREIGN));
    }

    public function testAdminContextsListScopeRootsAndTheGlobalContext(): void
    {
        $resolver = $this->resolver();

        $superadmin = $resolver->resolve($this->user('sa', ['ROLE_USER', 'ROLE_SUPERADMIN']));
        self::assertTrue($superadmin['global']);
        self::assertSame('superadmin', $superadmin['role']);
        self::assertSame([], $superadmin['scopes'], 'der globale Kontext ist kein Department');

        $orgchef = $resolver->resolve($this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::KV]));
        self::assertFalse($orgchef['global']);
        self::assertSame('org', $orgchef['role']);
        self::assertFalse($orgchef['unrestricted']);
        self::assertSame([self::KV], array_column($orgchef['scopes'], 'department_id'));

        $suborgchef = $resolver->resolve($this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]));
        self::assertSame('sub', $suborgchef['role']);
        self::assertSame(['Demo Abteilung Süd'], array_column($suborgchef['scopes'], 'name'));

        $unrestricted = $resolver->resolve($this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']));
        self::assertTrue($unrestricted['unrestricted']);
        self::assertSame([], $unrestricted['scopes']);

        $member = $resolver->resolve($this->user('mw', ['ROLE_USER', 'ROLE_MATWART']));
        self::assertSame(['global' => false, 'role' => 'none', 'unrestricted' => false, 'scopes' => []], $member);
    }


    public function testBackendAccessFollowsTheEffectiveRoleOfEachDepartmentNotTheGlobalRole(): void
    {
        $material = $this->createMock(MaterialItem::class);
        $material->method('getDepartmentId')->willReturnCallback(fn (): string => $this->materialDepartment);
        $photos = new MaterialPhotoAccessService($this->entityManager(), $this->checker());

        $suborgchef = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);
        $orgchef = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::KV]);
        $matwart = $this->user('mw', ['ROLE_USER', 'ROLE_MATWART']);
        $this->memberships[] = $this->membership($matwart, self::MAT, 'mw');
        $this->memberships[] = $this->membership($suborgchef, self::MAT, 'u'); // normale Rolle in einem anderen Zweig

        // Verwaltungszuständigkeit gilt nur im eigenen Baum …
        foreach ([[$orgchef, self::MAT, true], [$orgchef, self::FOREIGN, false], [$suborgchef, self::AUSSEN, true], [$suborgchef, self::KV, false]] as [$user, $department, $expected]) {
            $this->materialDepartment = $department;
            self::assertSame($expected, $photos->canViewPhoto($user, $material), $user->getId() . ' → ' . $department);
        }
        // … und gibt nie eine operative Rolle: der Suborgchef ist in der Materialverwaltung nur «u» und darf dort nicht hochladen
        $this->materialDepartment = self::MAT;
        self::assertTrue($photos->canViewPhoto($suborgchef, $material));
        self::assertFalse($photos->canUploadPhoto($suborgchef, $material));
        self::assertTrue($photos->canUploadPhoto($matwart, $material));
        // Der Materialchef einer Materialverwaltung hat in einem fremden Department nichts
        $this->materialDepartment = self::FOREIGN;
        self::assertFalse($photos->canViewPhoto($matwart, $material));
    }

    public function testMediaLibraryUsesScopeForAdminsAndMembershipRoleForMembers(): void
    {
        $media = new MediaFileAccessService(
            $this->entityManager(),
            $this->createMock(MaterialPhotoAccessService::class),
            $this->createMock(WorkshopPhotoAccessService::class),
            $this->createMock(IssuePhotoAccessService::class),
            $this->createMock(ActivityAccessService::class),
            $this->createMock(GrossanlassAccessService::class),
            $this->checker(),
        );
        $allowed = static function (callable $call): bool {
            try {
                $call();

                return true;
            } catch (AccessDeniedHttpException) {
                return false;
            }
        };

        $suborgchef = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);
        $matwart = $this->user('mw', ['ROLE_USER', 'ROLE_MATWART']);
        $helper = $this->user('u', ['ROLE_USER']);
        $this->memberships[] = $this->membership($matwart, self::MAT, 'mw');
        $this->memberships[] = $this->membership($helper, self::MAT, 'u');

        self::assertTrue($allowed(fn () => $media->assertCanBrowseDepartmentMedia($suborgchef, self::AUSSEN)));
        self::assertFalse($allowed(fn () => $media->assertCanBrowseDepartmentMedia($suborgchef, self::MAT)), 'anderer Zweig');
        self::assertTrue($allowed(fn () => $media->assertCanBrowseDepartmentMedia($matwart, self::MAT)));
        self::assertFalse($allowed(fn () => $media->assertCanBrowseDepartmentMedia($matwart, self::SUED)));
        self::assertFalse($allowed(fn () => $media->assertCanBrowseDepartmentMedia($helper, self::MAT)), 'Rolle u: keine Mediathek');
    }

    /** @param list<string> $roots */
    private function user(string $id, array $roles, array $roots = []): User
    {
        $profile = (new Profile())->setId(str_pad('p' . $id, 12, '0'))->setRoles($roles);
        if ($roots !== []) {
            $profile->setAdminCapabilities(['scope' => ['organisation_ids' => [], 'department_root_ids' => $roots]]);
        }
        $user = (new User())->setId(str_pad($id, 12, '0'));
        $user->setProfile($profile);

        return $user;
    }

    private function membership(User $user, string $departmentId, string $role): Membership
    {
        $membership = (new Membership())->setUser($user)->setDepartment($this->departments[$departmentId])->setRole($role);
        $membership->setDepartmentId($departmentId);

        return $membership;
    }

    /** @param list<string> $ids */
    private function memberships(array $ids): array
    {
        return array_values(array_filter($this->memberships, static fn (Membership $m): bool => \in_array(rtrim($m->getUserId(), '0'), $ids, true)));
    }

    private function department(string $id, string $name, Organisation $organisation, ?string $parentId = null): Department
    {
        $department = (new Department())->setId($id)->setName($name)->setOrganisation($organisation);
        $department->setParentId($parentId);

        return $department;
    }

    private function entityManager(): EntityManagerInterface
    {
        $departments = new class($this->departments) extends EntityRepository {
            /** @param array<string, Department> $departments */
            public function __construct(private array $departments)
            {
            }

            public function findAll(): array
            {
                return array_values($this->departments);
            }

            public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
            {
                $ids = (array) ($criteria['id'] ?? []);

                return array_values(array_filter($this->departments, static fn (Department $d): bool => \in_array($d->getId(), $ids, true)));
            }
        };
        $memberships = $this->createMock(EntityRepository::class);
        $memberships->method('findBy')->willReturnCallback(fn (array $criteria): array => array_values(array_filter(
            $this->memberships,
            static fn (Membership $m): bool => $m->getUserId() === ($criteria['userId'] ?? null),
        )));

        $memberships->method('findOneBy')->willReturnCallback(function (array $criteria): ?Membership {
            foreach ($this->memberships as $membership) {
                if ($membership->getUserId() === ($criteria['userId'] ?? null) && $membership->getDepartmentId() === ($criteria['departmentId'] ?? null)) {
                    return $membership;
                }
            }

            return null;
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn (string $class): object => $class === Department::class ? $departments : $memberships);

        return $em;
    }

    private function checker(): AdminCapabilityChecker
    {
        $em = $this->entityManager();

        return new AdminCapabilityChecker($em, new AdminCapabilityDepartmentScope($em));
    }

    private function resolver(): AdminContextResolver
    {
        return new AdminContextResolver($this->checker(), $this->entityManager());
    }
}
