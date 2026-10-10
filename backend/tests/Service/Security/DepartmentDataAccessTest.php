<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Controller\CategoryController;
use App\Controller\DepartmentController;
use App\Controller\DepartmentSettingController;
use App\Controller\TemplateController;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\MaterialTemplate;
use App\Entity\Organisation;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\DepartmentRepository;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Admin\AdminCapabilityDepartmentScope;
use App\Service\PrintCatalog\PrintCatalogService;
use App\Service\Security\DepartmentAccessGuard;
use App\Service\Security\UserPickerScope;
use App\Service\TemplateImportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Lesen und Schreiben von Department-Daten werden getrennt autorisiert, und zentrale Inhalte ändert nur der Superadmin.
 * Kantonalverband → (Materialverwaltung | Abteilung Süd → Aussenstelle); Org 2 mit einem fremden Department.
 */
final class DepartmentDataAccessTest extends TestCase
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

    protected function setUp(): void
    {
        $org1 = (new Organisation())->setId('orga00000001');
        $org2 = (new Organisation())->setId('orga00000002');
        $this->departments = [
            self::KV => $this->department(self::KV, 'Demo Kantonalverband', $org1),
            self::MAT => $this->department(self::MAT, 'Demo Materialverwaltung', $org1, self::KV),
            self::SUED => $this->department(self::SUED, 'Demo Abteilung Süd', $org1, self::KV),
            self::AUSSEN => $this->department(self::AUSSEN, 'Demo Aussenstelle', $org1, self::SUED),
            self::FOREIGN => $this->department(self::FOREIGN, 'Echtes Department', $org2),
        ];
    }

    // ── Rollenmatrix: Lesen und Schreiben getrennt ────────────────────────────────────────────────────────────────

    /** @return iterable<string, array{string, bool, bool}> */
    public static function memberRoles(): iterable
    {
        yield 'u' => ['u', true, false];
        yield 'l1' => ['l1', true, false];
        yield 'l2' => ['l2', true, false];
        yield 'l3' => ['l3', true, false];
        yield 'mw' => ['mw', true, true];
        yield 'cmw' => ['cmw', true, true];
        yield 'dc' => ['dc', true, true];
        yield 'bl' => ['bl', true, true];
        yield 'lw' => ['lw', true, true];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('memberRoles')]
    public function testMembersReadWithEveryRoleButOnlyNonBasicRolesWrite(string $role, bool $read, bool $write): void
    {
        $guard = $this->guard();
        $member = $this->user('m' . $role, ['ROLE_USER']);
        $this->memberships[] = $this->membership($member, self::MAT, $role);

        self::assertSame($read, $guard->canAccess($member, self::MAT), "$role liest");
        self::assertSame($write, $guard->canManage($member, self::MAT), "$role schreibt");
        self::assertSame($write ? null : 403, $guard->denyManage($member, self::MAT)?->getStatusCode());
        // die Rolle in Department A gilt nirgends sonst
        self::assertFalse($guard->canAccess($member, self::SUED));
        self::assertFalse($guard->canManage($member, self::FOREIGN));
    }

    public function testAdministrationFollowsTheScopeTreeForReadAndWrite(): void
    {
        $guard = $this->guard();
        $sub = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);
        $org = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [], ['orga00000001']);
        $none = $this->user('nos', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']);
        $sa = $this->user('sa', ['ROLE_USER', 'ROLE_SUPERADMIN']);

        self::assertTrue($guard->canManage($sub, self::AUSSEN));
        self::assertFalse($guard->canAccess($sub, self::MAT), 'Geschwisterzweig');
        self::assertFalse($guard->canAccess($sub, self::KV), 'Parent');
        self::assertTrue($guard->canManage($org, self::MAT));
        self::assertFalse($guard->canAccess($org, self::FOREIGN));
        self::assertFalse($guard->canAccess($none, self::MAT), 'ohne Zuweisung keine Verwaltung');
        self::assertTrue($guard->canManage($sa, self::FOREIGN));
        self::assertFalse($guard->canManage(null, self::MAT));
    }

    // ── Endpunkte: Einstellungen, Kategorien ──────────────────────────────────────────────────────────────────────

    public function testSettingsEndpointsRejectOutsidersAndBasicMembersForWritesOnly(): void
    {
        $u = $this->user('u', ['ROLE_USER']);
        $mw = $this->user('mw', ['ROLE_USER']);
        $this->memberships[] = $this->membership($u, self::MAT, 'u');
        $this->memberships[] = $this->membership($mw, self::MAT, 'mw');
        $controller = $this->controller(DepartmentSettingController::class, [$this->guard()]);

        // Fremde ohne Mitgliedschaft: weder lesen noch schreiben
        $outsider = $this->user('out', ['ROLE_USER']);
        $this->login($controller, $outsider);
        self::assertSame(403, $controller->list(self::MAT)->getStatusCode());
        self::assertSame(403, $controller->update(self::MAT, $this->json(['general.timezone' => 'UTC']))->getStatusCode());

        // Normales Mitglied: lesen ja (Zeitzone, Standardwerte), schreiben nein
        $this->login($controller, $u);
        self::assertSame(403, $controller->update(self::MAT, $this->json(['general.timezone' => 'UTC']))->getStatusCode());
        self::assertSame(200, $controller->list(self::MAT)->getStatusCode());

        // MW darf schreiben: die Anfrage passiert die Autorisierung und erreicht die Fachlogik (die Stub-Dienste dort fehlen)
        $this->login($controller, $mw);
        self::assertTrue($this->passesAuthorization(fn () => $controller->update(self::MAT, $this->json(['general.timezone' => 'UTC']))));
    }

    public function testCategoryEndpointsAreTenantSeparatedAndWritesNeedANonBasicRole(): void
    {
        $u = $this->user('u', ['ROLE_USER']);
        $mw = $this->user('mw', ['ROLE_USER']);
        $this->memberships[] = $this->membership($u, self::MAT, 'u');
        $this->memberships[] = $this->membership($mw, self::MAT, 'mw');
        $controller = $this->controller(CategoryController::class, [$this->guard()]);
        $create = fn (string $dept): Request => $this->json(['department_id' => $dept, 'name' => 'Zelte']);

        $this->login($controller, $mw);
        self::assertSame(403, $controller->create($create(self::SUED))->getStatusCode(), 'MW von A schreibt nicht in B');
        $request = new Request(['department_id' => self::SUED]);
        self::assertSame(403, $controller->list($request)->getStatusCode(), 'und liest nicht aus B');

        $this->login($controller, $u);
        self::assertSame(403, $controller->create($create(self::MAT))->getStatusCode(), 'Rolle u schreibt nicht');
    }

    // ── Zentrale Vorlagen: nur Superadmin ─────────────────────────────────────────────────────────────────────────

    public function testGlobalTemplatesAreWritableBySuperadminOnly(): void
    {
        $service = (new \ReflectionClass(TemplateImportExportService::class))->newInstanceWithoutConstructor();
        $sa = $this->user('sa', ['ROLE_USER', 'ROLE_SUPERADMIN']);
        $org = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::KV]);
        $sub = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [], ['orga00000001']);

        self::assertNull($service->assertCanImport(null, 'global', $sa));
        self::assertNotNull($service->assertCanImport(null, 'global', $org));
        self::assertNotNull($service->assertCanImport(null, 'global', $sub));
        self::assertNotNull($service->assertCanExport(null, 'global', $org));
    }

    public function testTemplateEndpointsKeepGlobalWritesAndForeignDepartmentTemplatesClosed(): void
    {
        $org = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [self::KV]);
        $global = (new MaterialTemplate())->setId('tmplglobal001')->setName('Zentral');
        $foreign = (new MaterialTemplate())->setId('tmplforeign01')->setName('Fremd')->setDepartment($this->departments[self::FOREIGN]);
        $mine = (new MaterialTemplate())->setId('tmplmine00001')->setName('Eigen')->setDepartment($this->departments[self::MAT]);
        $controller = $this->controller(TemplateController::class, [$this->guard()], [MaterialTemplate::class => [$global, $foreign, $mine]]);

        $this->login($controller, $org, ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']);
        // zentral: weder lesen der Verwaltungsliste noch schreiben
        self::assertSame(403, $controller->list(new Request(['scope' => 'global']))->getStatusCode());
        self::assertSame(403, $controller->create($this->json(['name' => 'X', 'scope' => 'global']))->getStatusCode());
        self::assertSame(403, $controller->update('tmplglobal001', $this->json(['name' => 'Y']))->getStatusCode());
        self::assertSame(403, $controller->delete('tmplglobal001')->getStatusCode());
        // Department-Vorlage ausserhalb des Scopes
        self::assertSame(403, $controller->update('tmplforeign01', $this->json(['name' => 'Y']))->getStatusCode());
        self::assertSame(403, $controller->delete('tmplforeign01')->getStatusCode());
        self::assertSame(403, $controller->get('tmplforeign01', new Request())->getStatusCode());
        // Department-Liste eines fremden Departments
        self::assertSame(403, $controller->list(new Request(['department_id' => self::FOREIGN]))->getStatusCode());
        // Vorlage im eigenen Verwaltungsbaum: lesbar
        self::assertSame(200, $controller->get('tmplmine00001', new Request())->getStatusCode());
        // Superadmin: Schreibzugriff auf Zentrales nicht am Guard blockiert
        $sa = $this->user('sa', ['ROLE_USER', 'ROLE_SUPERADMIN']);
        $this->login($controller, $sa, ['ROLE_USER', 'ROLE_SUPERADMIN']);
        self::assertTrue($this->passesAuthorization(fn () => $controller->update('tmplglobal001', $this->json(['name' => 'Y']))));
    }

    // ── Druckkatalog ──────────────────────────────────────────────────────────────────────────────────────────────

    public function testPrintCatalogReviewNeverLetsOrgOrSuborgChiefPublishGlobally(): void
    {
        $catalog = $this->catalog();
        $sa = $this->user('sa', ['ROLE_USER', 'ROLE_SUPERADMIN']);
        $orgWithOrg = $this->user('org', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [], ['orga00000001']);
        $deptOnly = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);
        $noScope = $this->user('nos', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']);
        $member = $this->user('mw', ['ROLE_USER']);

        // Superadmin: alles
        foreach (['approve', 'reject', 'promote_global'] as $action) {
            $catalog->assertCanReview($sa, 'orga00000002', $action);
        }
        $this->addToAssertionCount(3);

        // Organisations-Zuweisung: nur ablehnen, nur in der eigenen Organisation
        $catalog->assertCanReview($orgWithOrg, 'orga00000001', 'reject');
        $this->addToAssertionCount(1);
        foreach (['approve', 'promote_global', 'unbekannt'] as $action) {
            $this->assertRuntimeDenied(fn () => $catalog->assertCanReview($orgWithOrg, 'orga00000001', $action), 'Nur Superadmin');
        }
        $this->assertRuntimeDenied(fn () => $catalog->assertCanReview($orgWithOrg, 'orga00000002', 'reject'), 'diese Organisation');

        // Department-Zuweisung allein ist keine Organisationsebene; ohne Zuweisung und als Mitglied ebenso wenig
        $this->assertRuntimeDenied(fn () => $catalog->assertCanReview($deptOnly, 'orga00000001', 'reject'), 'diese Organisation');
        $this->assertRuntimeDenied(fn () => $catalog->assertCanReview($noScope, 'orga00000001', 'reject'), 'diese Organisation');
        $this->assertRuntimeDenied(fn () => $catalog->assertCanReview($member, 'orga00000001', 'reject'), 'Prüfung');
    }

    public function testPrintCatalogDepartmentManagementFollowsMembershipAndScope(): void
    {
        $catalog = $this->catalog();
        $mw = $this->user('mw', ['ROLE_USER']);
        $u = $this->user('u', ['ROLE_USER']);
        $this->memberships[] = $this->membership($mw, self::MAT, 'mw');
        $this->memberships[] = $this->membership($u, self::MAT, 'u');
        $sub = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);
        $noScope = $this->user('nos', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']);

        self::assertTrue($catalog->canManageDepartment($mw, self::MAT));
        self::assertFalse($catalog->canManageDepartment($mw, self::SUED));
        self::assertFalse($catalog->canManageDepartment($u, self::MAT));
        self::assertTrue($catalog->canManageDepartment($sub, self::AUSSEN));
        self::assertFalse($catalog->canManageDepartment($sub, self::MAT));
        self::assertFalse($catalog->canManageDepartment($noScope, self::MAT), 'Orgchef-Rolle allein reicht nicht');
        self::assertFalse($catalog->isDepartmentMember($noScope, self::MAT));
        self::assertTrue($catalog->isDepartmentMember($u, self::MAT));
        self::assertFalse($catalog->isPrintManager($noScope));
        self::assertTrue($catalog->isPrintManager($sub));
        self::assertFalse($catalog->canSeeAllOrganisations($noScope), 'nur der Superadmin sieht alle Organisationen');
    }

    // ── Benutzersuche und Mitgliederlisten ────────────────────────────────────────────────────────────────────────

    public function testUserPickerSeesOnlyOwnTreeOrganisationAndUnassignedUsers(): void
    {
        $scope = new UserPickerScope($this->entityManager(), $this->checker());
        $sa = $this->user('sa', ['ROLE_USER', 'ROLE_SUPERADMIN']);
        $mw = $this->user('mw', ['ROLE_USER']);
        $this->memberships[] = $this->membership($mw, self::MAT, 'mw');
        $sub = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);
        $noScope = $this->user('nos', ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']);

        self::assertNull($scope->visibleDepartmentIds($sa), 'Superadmin: unbeschränkt');
        // Verwaltung (Wizard): nur der Scope-Baum
        self::assertEqualsCanonicalizing([self::SUED, self::AUSSEN], $scope->visibleDepartmentIds($sub));
        // Mitglieder-Auswahl: eigene Departments plus Organisation des Ziel-Departments, nie die fremde Organisation
        self::assertEqualsCanonicalizing([self::KV, self::MAT, self::SUED, self::AUSSEN], $scope->visibleDepartmentIds($mw, 'orga00000001'));
        self::assertSame([self::MAT], $scope->visibleDepartmentIds($mw));
        // Orgchef ohne Zuweisung sieht nichts ausser unzugeordneten Kandidaten
        self::assertSame([], $scope->visibleDepartmentIds($noScope));

        self::assertTrue($scope->isDepartmentVisible(null, self::FOREIGN));
        self::assertFalse($scope->isDepartmentVisible([self::MAT], self::FOREIGN));
    }

    public function testUserPickerQueryIsRestrictedToVisibleDepartmentsOrUnassignedUsers(): void
    {
        $scope = new UserPickerScope($this->entityManager(), $this->checker());

        $unrestricted = $this->createMock(QueryBuilder::class);
        $unrestricted->expects(self::never())->method('andWhere');
        $scope->restrict($unrestricted, null);

        $restricted = $this->createMock(QueryBuilder::class);
        $restricted->expects(self::once())->method('andWhere')
            ->with(self::stringContains('NOT EXISTS'))
            ->willReturnSelf();
        $restricted->expects(self::once())->method('setParameter')->with('visibleDeptIds', [self::MAT])->willReturnSelf();
        $scope->restrict($restricted, [self::MAT]);

        $empty = $this->createMock(QueryBuilder::class);
        $empty->method('andWhere')->willReturnSelf();
        $empty->expects(self::once())->method('setParameter')->with('visibleDeptIds', ['-'])->willReturnSelf();
        $scope->restrict($empty, []);
    }

    public function testMemberListAndPickerEndpointsRejectOutsidersAndNonInviters(): void
    {
        $mw = $this->user('mw', ['ROLE_USER']);
        $u = $this->user('u', ['ROLE_USER']);
        $l3 = $this->user('l3', ['ROLE_USER']);
        $this->memberships[] = $this->membership($mw, self::MAT, 'mw');
        $this->memberships[] = $this->membership($u, self::MAT, 'u');
        $this->memberships[] = $this->membership($l3, self::MAT, 'l3');
        $departmentRepository = $this->createMock(DepartmentRepository::class);
        $departmentRepository->method('find')->willReturnCallback(fn (string $id): ?Department => $this->departments[$id] ?? null);
        $checker = $this->checker();
        $controller = $this->departmentController($departmentRepository, $checker);
        $outsider = $this->user('out', ['ROLE_USER']);
        $suborg = $this->user('sub', ['ROLE_USER', 'ROLE_SUBORGCHEF'], [self::SUED]);

        // Mitgliederliste: Fremde und andere Zweige bekommen nichts (E-Mail-Adressen!)
        $this->login($controller, $outsider);
        self::assertSame(403, $controller->listMembers(self::MAT)->getStatusCode());
        $this->login($controller, $suborg, ['ROLE_USER', 'ROLE_SUBORGCHEF']);
        self::assertSame(403, $controller->listMembers(self::MAT)->getStatusCode(), 'Geschwisterzweig');

        // Department-Detail: Stammdaten ja (Breadcrumb), Mitglieder nur für Berechtigte
        $this->login($controller, $outsider);
        $payload = json_decode((string) $controller->get(self::MAT)->getContent(), true);
        self::assertSame(self::MAT, $payload['id']);
        self::assertSame([], $payload['users']);

        // Mitglieder-Auswahl: nur wer Mitglieder hinzufügen darf (Rolle u und Fremde nicht, Leader 3 nur nach Rang)
        foreach ([[$outsider, 403], [$u, 403], [$l3, 200], [$mw, 200]] as [$user, $status]) {
            $this->login($controller, $user);
            self::assertSame($status, $controller->availableUsers(self::MAT, new Request(['q' => '']))->getStatusCode(), $user->getId());
        }
        // Verwaltung im Baum darf, ausserhalb nicht
        $this->login($controller, $suborg, ['ROLE_USER', 'ROLE_SUBORGCHEF']);
        self::assertSame(200, $controller->availableUsers(self::AUSSEN, new Request(['q' => '']))->getStatusCode());
        self::assertSame(403, $controller->availableUsers(self::MAT, new Request(['q' => '']))->getStatusCode());
        // Wizard-Suche: ohne Organisation leer, fremde Organisation verboten, zu kurze Suche leer
        self::assertSame('[]', $controller->grossanlassAvailableUsers(new Request(['q' => 'ab']))->getContent());
        self::assertSame(403, $controller->grossanlassAvailableUsers(new Request(['q' => 'abc', 'organisation_id' => 'orga00000002']))->getStatusCode());
        $this->login($controller, $mw);
        self::assertSame(403, $controller->grossanlassAvailableUsers(new Request(['q' => 'abc', 'organisation_id' => 'orga00000001']))->getStatusCode(), 'nur globale Admins');
    }

    // ── Hilfen ────────────────────────────────────────────────────────────────────────────────────────────────────

    private function assertRuntimeDenied(callable $call, string $messagePart): void
    {
        try {
            $call();
            self::fail('Erwartet: Zugriff verweigert («' . $messagePart . '»)');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    /** true, wenn der Aufruf nicht mit 403 endet (auch ein Fehler in der danach folgenden Fachlogik zählt als «durchgelassen»). */
    private function passesAuthorization(callable $call): bool
    {
        try {
            $response = $call();
        } catch (\Throwable) {
            return true;
        }

        return !$response instanceof JsonResponse || $response->getStatusCode() !== 403;
    }

    private function json(array $body): Request
    {
        return new Request([], [], [], [], [], [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param class-string<object>                                   $class
     * @param list<object>                                           $tail   letzte Konstruktor-Argumente (Guard)
     * @param array<class-string, list<object>>                      $entities
     */
    private function controller(string $class, array $tail, array $entities = []): object
    {
        $reflection = new \ReflectionClass($class);
        $parameters = $reflection->getConstructor()?->getParameters() ?? [];
        $arguments = [];
        $tailCount = \count($tail);
        foreach ($parameters as $index => $parameter) {
            if ($index >= \count($parameters) - $tailCount) {
                $arguments[] = array_shift($tail);
                continue;
            }
            $type = $parameter->getType();
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $arguments[] = $name === EntityManagerInterface::class
                ? $this->entityManager($entities)
                : (new \ReflectionClass((string) $name))->newInstanceWithoutConstructor();
        }

        return new $class(...$arguments);
    }

    private function departmentController(DepartmentRepository $departments, AdminCapabilityChecker $checker): DepartmentController
    {
        $reflection = new \ReflectionClass(DepartmentController::class);
        $arguments = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $name = $parameter->getType() instanceof \ReflectionNamedType ? $parameter->getType()->getName() : 'string';
            $arguments[] = match (true) {
                $name === DepartmentRepository::class => $departments,
                $name === EntityManagerInterface::class => $this->entityManager(),
                $name === AdminCapabilityChecker::class => $checker,
                $name === DepartmentAccessGuard::class => $this->guard(),
                $name === UserPickerScope::class => new UserPickerScope($this->entityManager(), $checker),
                $name === 'string' => 'secret',
                default => (new \ReflectionClass($name))->newInstanceWithoutConstructor(),
            };
        }

        return new DepartmentController(...$arguments);
    }

    /** @param list<string> $roles */
    private function login(object $controller, User $user, array $roles = ['ROLE_USER']): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'api', $roles));
        $checker = new class($roles) {
            /** @param list<string> $roles */
            public function __construct(private array $roles)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null): bool
            {
                return \in_array($attribute, $this->roles, true);
            }
        };
        $container = new Container();
        $container->set('security.token_storage', $storage);
        $container->set('security.authorization_checker', $checker);
        $controller->setContainer($container);
    }

    private function guard(): DepartmentAccessGuard
    {
        return new DepartmentAccessGuard($this->entityManager(), $this->checker());
    }

    private function catalog(): PrintCatalogService
    {
        return new PrintCatalogService($this->entityManager(), $this->checker());
    }

    private function checker(): AdminCapabilityChecker
    {
        $em = $this->entityManager();

        return new AdminCapabilityChecker($em, new AdminCapabilityDepartmentScope($em));
    }

    /** @param array<class-string, list<object>> $entities */
    private function entityManager(array $entities = []): EntityManagerInterface
    {
        $byId = static function (array $objects, string $id): ?object {
            foreach ($objects as $object) {
                if (method_exists($object, 'getId') && $object->getId() === $id) {
                    return $object;
                }
            }

            return null;
        };
        $departments = new class($this->departments) extends EntityRepository {
            /** @param array<string, Department> $departments */
            public function __construct(private array $departments)
            {
            }

            public function find(mixed $id, $lockMode = null, $lockVersion = null): ?object
            {
                return $this->departments[$id] ?? null;
            }

            public function findAll(): array
            {
                return array_values($this->departments);
            }

            public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
            {
                $ids = isset($criteria['id']) ? (array) $criteria['id'] : null;
                $organisation = $criteria['organisationId'] ?? null;

                return array_values(array_filter(
                    $this->departments,
                    static fn (Department $d): bool => ($ids === null || \in_array($d->getId(), $ids, true))
                        && ($organisation === null || $d->getOrganisationId() === $organisation),
                ));
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
        $generic = $this->createMock(EntityRepository::class);
        $generic->method('find')->willReturn(null);
        $generic->method('findBy')->willReturn([]);
        $generic->method('findAll')->willReturn([]);
        $others = [];
        foreach ($entities as $class => $objects) {
            $repository = $this->createMock(EntityRepository::class);
            $repository->method('find')->willReturnCallback(static fn (mixed $id): ?object => $byId($objects, (string) $id));
            $repository->method('findBy')->willReturn($objects);
            $builder = $this->createMock(QueryBuilder::class);
            foreach (['leftJoin', 'addSelect', 'where', 'andWhere', 'setParameter', 'orderBy', 'addOrderBy'] as $method) {
                $builder->method($method)->willReturnSelf();
            }
            $repository->method('createQueryBuilder')->willReturn($builder);
            $others[$class] = $repository;
        }

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn (string $class): object => match (true) {
            $class === Department::class => $departments,
            $class === Organisation::class => $organisations,
            $class === Membership::class => $memberships,
            isset($others[$class]) => $others[$class],
            default => $generic,
        });

        return $em;
    }

    /**
     * @param list<string> $roots
     * @param list<string> $organisations
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

    private function department(string $id, string $name, Organisation $organisation, ?string $parentId = null): Department
    {
        $department = (new Department())->setId($id)->setName($name)->setOrganisation($organisation);
        $department->setParentId($parentId);

        return $department;
    }
}
