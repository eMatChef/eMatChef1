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
use App\Service\Security\DepartmentAccessGuard;
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

    public function testMissingScopeMeansNoAdministrativeRightsAtAll(): void
    {
        $checker = $this->checker();
        foreach ([['org', 'ROLE_ORGANISATIONSCHEF'], ['sub', 'ROLE_SUBORGCHEF']] as [$id, $role]) {
            $admin = $this->user($id, ['ROLE_USER', $role]);
            foreach (array_keys($this->departments) as $department) {
                self::assertFalse($checker->canAdministerDepartment($admin, $department), "$id → $department");
                self::assertFalse($checker->canAccessDepartment($admin, $department), "$id sieht $department nicht");
            }
            self::assertSame([], $checker->getAccessibleDepartmentIds($admin));
            self::assertSame([], $checker->getAccessibleOrganisationIds($admin));
            self::assertFalse($checker->hasAdministrativeScope($admin));
            self::assertFalse($checker->canAdministerOrganisation($admin, 'orga00000001'));
        }
    }

    public function testOrganisationScopeCoversAllDepartmentsOfTheOrganisationOnly(): void
    {
        $checker = $this->checker();
        $orgchef = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [], ['orga00000001']);

        foreach ([self::KV, self::MAT, self::SUED, self::AUSSEN] as $id) {
            self::assertTrue($checker->canAdministerDepartment($orgchef, $id), $id);
        }
        self::assertFalse($checker->canAdministerDepartment($orgchef, self::FOREIGN), 'andere Organisation');
        self::assertTrue($checker->canAdministerOrganisation($orgchef, 'orga00000001'));
        self::assertFalse($checker->canAdministerOrganisation($orgchef, 'orga00000002'));
    }

    public function testDepartmentScopeIsNoOrganisationAdministrationAndSeesNoParentOrSibling(): void
    {
        $checker = $this->checker();
        $suborgchef = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);

        // Organisation sichtbar (zur Anzeige), aber nicht verwaltbar: keine Rechte auf Organisationsebene
        self::assertSame(['orga00000001'], $checker->getAccessibleOrganisationIds($suborgchef));
        self::assertTrue($checker->canAccessOrganisation($suborgchef, 'orga00000001'));
        self::assertFalse($checker->canAdministerOrganisation($suborgchef, 'orga00000001'));
        self::assertSame([], $checker->getAdministeredOrganisationIds($suborgchef));
        self::assertEqualsCanonicalizing([self::SUED, self::AUSSEN], $checker->getAccessibleDepartmentIds($suborgchef));
    }

    public function testSeveralScopesAreUnitedNotIntersected(): void
    {
        $checker = $this->checker();
        // Organisation 2 (nur das fremde Department) plus Department-Wurzel Abteilung Süd in Organisation 1
        $admin = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED], ['orga00000002']);

        self::assertEqualsCanonicalizing([self::SUED, self::AUSSEN, self::FOREIGN], $checker->getAccessibleDepartmentIds($admin));
        self::assertTrue($checker->canAdministerDepartment($admin, self::FOREIGN));
        self::assertTrue($checker->canAdministerDepartment($admin, self::AUSSEN));
        self::assertFalse($checker->canAdministerDepartment($admin, self::MAT), 'Geschwisterzweig der Wurzel');
        self::assertFalse($checker->canAdministerDepartment($admin, self::KV), 'Parent der Wurzel');
        self::assertEqualsCanonicalizing(['orga00000001', 'orga00000002'], $checker->getAccessibleOrganisationIds($admin));
        self::assertTrue($checker->canAdministerOrganisation($admin, 'orga00000002'));
        self::assertFalse($checker->canAdministerOrganisation($admin, 'orga00000001'));
    }

    public function testOrgchefAndSuborgchefKeepTheirDifferentCapabilitiesForTheSameScope(): void
    {
        $checker = $this->checker();
        $org = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::KV]);
        $sub = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::KV]);

        // gleicher Bereich …
        self::assertSame($checker->getAccessibleDepartmentIds($org), $checker->getAccessibleDepartmentIds($sub));
        // … unterschiedliche Verwaltungsaktionen aus dem bestehenden Modell
        self::assertTrue($checker->can($org, 'organisations.edit'));
        self::assertFalse($checker->can($sub, 'organisations.edit'));
        self::assertTrue($checker->can($org, 'organisations.create'));
        self::assertFalse($checker->can($sub, 'organisations.create'));
        self::assertTrue($checker->can($sub, 'departments.edit'));
        self::assertFalse($checker->can($org, 'users.global_manage'));
    }

    public function testAdministrationAndOperativeRoleInTheSameDepartmentStaySeparate(): void
    {
        $checker = $this->checker();
        $orgchef = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [], ['orga00000001']);
        $this->memberships[] = $this->membership($orgchef, self::MAT, 'u');
        $guard = new DepartmentAccessGuard($this->entityManager(), $checker);

        // beides gilt unabhängig: Verwaltung im Baum, ausdrücklich zugewiesene Rolle «u» in der Materialverwaltung
        self::assertTrue($checker->canAdministerDepartment($orgchef, self::MAT));
        self::assertSame('u', $this->memberships[0]->getRole());
        self::assertTrue($guard->canAccess($orgchef, self::MAT));
        // die Verwaltungsrolle erzeugt keine Mitgliedschaft: im Camp (ebenfalls Organisation 1 nicht) bleibt es bei der einen
        self::assertCount(1, $this->memberships);
        self::assertSame(self::MAT, $this->memberships[0]->getDepartmentId());
    }

    public function testDepartmentAccessGuardSeparatesDepartmentsByMembershipAndScope(): void
    {
        $checker = $this->checker();
        $guard = new DepartmentAccessGuard($this->entityManager(), $checker);
        $matwart = $this->user('mw', ['ROLE_USER']);
        $this->memberships[] = $this->membership($matwart, self::MAT, 'mw');
        $suborgchef = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);
        $noScope = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']);

        // eine operative Rolle in A öffnet B nicht
        self::assertTrue($guard->canAccess($matwart, self::MAT));
        self::assertFalse($guard->canAccess($matwart, self::SUED));
        self::assertFalse($guard->canAccess($matwart, self::FOREIGN));
        // Scope-Baum ja, Rest nein; ohne Scope nirgends
        self::assertTrue($guard->canAccess($suborgchef, self::AUSSEN));
        self::assertFalse($guard->canAccess($suborgchef, self::MAT));
        self::assertFalse($guard->canAccess($noScope, self::MAT));
        self::assertNull($guard->deny($matwart, self::MAT));
        self::assertSame(403, $guard->deny($matwart, self::SUED)?->getStatusCode());
        self::assertSame(401, $guard->deny(null, self::MAT)?->getStatusCode());
        self::assertFalse($guard->canAccess($matwart, ''));
    }

    public function testSymfonyRolesNeverCarryDepartmentRoles(): void
    {
        $user = $this->user('mw', ['ROLE_USER']);
        $this->memberships[] = $this->membership($user, self::MAT, 'mw');
        $user->getMemberships()->add($this->memberships[0]);

        self::assertSame(['ROLE_USER'], array_values($user->getRoles()), 'ROLE_MATWART aus Department A darf nirgends global wirken');
    }

    public function testAdminContextsListOrganisationAndDepartmentScopesAndTheGlobalContext(): void
    {
        $resolver = $this->resolver();

        $superadmin = $resolver->resolve($this->user('sa', ['ROLE_USER', 'ROLE_SUPERADMIN']));
        self::assertTrue($superadmin['global']);
        self::assertSame('superadmin', $superadmin['role']);
        self::assertSame([], $superadmin['scopes'], 'der globale Kontext ist kein Department');

        $orgchef = $resolver->resolve($this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::KV]));
        self::assertFalse($orgchef['global']);
        self::assertSame('org', $orgchef['role']);
        self::assertSame([['department', self::KV]], array_map(static fn (array $s): array => [$s['kind'], $s['department_id']], $orgchef['scopes']));

        $suborgchef = $resolver->resolve($this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]));
        self::assertSame('sub', $suborgchef['role']);
        self::assertSame(['Demo Abteilung Süd'], array_column($suborgchef['scopes'], 'name'));

        // Organisations- und Department-Scope kombiniert: Organisationen zuerst, je ein eigener Kontext
        $combined = $resolver->resolve($this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::SUED], ['orga00000002']));
        self::assertSame(['organisation', 'department'], array_column($combined['scopes'], 'kind'));
        self::assertNull($combined['scopes'][0]['department_id']);
        self::assertSame('orga00000002', $combined['scopes'][0]['organisation_id']);

        // ohne Zuweisung: Rolle bekannt, aber kein wählbarer Kontext
        $none = $resolver->resolve($this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']));
        self::assertSame(['global' => false, 'role' => 'org', 'scopes' => []], $none);

        $member = $resolver->resolve($this->user('mw', ['ROLE_USER']));
        self::assertSame(['global' => false, 'role' => 'none', 'scopes' => []], $member);
    }

    /**
     * @param list<string> $roots         Department-Wurzeln im Scope
     * @param list<string> $organisations Organisationen im Scope
     */
    private function user(string $id, array $roles, array $roots = [], array $organisations = []): User
    {
        $profile = (new Profile())->setId(str_pad('p' . $id, 12, '0'))->setRoles($roles);
        if ($roots !== [] || $organisations !== []) {
            $profile->setAdminCapabilities(['scope' => ['organisation_ids' => $organisations, 'department_root_ids' => $roots]]);
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
        $organisations = new class([(new Organisation())->setId('orga00000001')->setName('Org 1'), (new Organisation())->setId('orga00000002')->setName('Org 2')]) extends EntityRepository {
            /** @param list<Organisation> $organisations */
            public function __construct(private array $organisations)
            {
            }

            public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
            {
                $ids = (array) ($criteria['id'] ?? []);

                return array_values(array_filter($this->organisations, static fn (Organisation $o): bool => \in_array($o->getId(), $ids, true)));
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
        $em->method('getRepository')->willReturnCallback(static fn (string $class): object => match ($class) {
            Department::class => $departments,
            Organisation::class => $organisations,
            default => $memberships,
        });

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
