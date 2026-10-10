<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MembershipNotificationEmailController;
use App\Controller\ProfileEmailController;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserEmailAlias;
use App\Service\AuditLogger;
use App\Service\MembershipNotificationEmailResolver;
use App\Service\UserEmailAliasService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Benachrichtigungsadresse je eigener Mitgliedschaft: Lesen im E-Mail-Accordion, Ändern über die bestehende
 * Department-API (dasselbe Speicherfeld Membership.notificationEmail).
 */
final class ProfileEmailDepartmentAssignmentsTest extends TestCase
{
    private User $me;

    private User $other;

    /** @var list<UserEmailAlias> */
    private array $aliases = [];

    /** @var array<string, Membership> departmentId => eigene Membership */
    private array $memberships = [];

    /** @var list<array{string, array<string, mixed>}> */
    private array $audits = [];

    protected function setUp(): void
    {
        $this->me = $this->user('user00000001', 'anna@beispiel.ch');
        $this->other = $this->user('user00000002', 'bob@beispiel.ch');
        $this->aliases = [
            $this->alias('material@beispiel.ch', true),
            $this->alias('offen@beispiel.ch', false),
        ];
        $this->memberships = [];
        $this->audits = [];
    }

    public function testListsOnlyOwnMembershipsWithSelectionAndOnlyVerifiedOptions(): void
    {
        $a = $this->membership($this->me, 'dep000000001', 'Abteilung A', null);
        $b = $this->membership($this->me, 'dep000000002', 'Abteilung B', 'Region Nord');
        $b->setNotificationEmail('material@beispiel.ch');
        $this->membership($this->other, 'dep000000009', 'Fremde Abteilung', null);

        $body = $this->json($this->profileController()->departmentAssignments($this->me->getProfileId()));

        self::assertSame(['dep000000001', 'dep000000002'], array_column($body['assignments'], 'department_id'));
        self::assertSame([null, 'material@beispiel.ch'], array_column($body['assignments'], 'selected_email'));
        self::assertSame(['anna@beispiel.ch', 'material@beispiel.ch'], array_column($body['assignments'], 'effective_email'));
        self::assertSame('Region Nord', $body['assignments'][1]['parent_name']);
        self::assertSame(['anna@beispiel.ch', 'material@beispiel.ch'], $body['options'], 'unbestätigte Adressen nie wählbar');
        self::assertStringNotContainsString('Fremde', json_encode($body, JSON_THROW_ON_ERROR));
        self::assertSame($a->getDepartmentId(), 'dep000000001');
    }

    public function testDeletedOrInvalidAliasFallsBackToPrimaryInTheOverview(): void
    {
        $m = $this->membership($this->me, 'dep000000001', 'Abteilung A', null);
        $m->setNotificationEmail('offen@beispiel.ch'); // unbestätigt/ungültig
        $m2 = $this->membership($this->me, 'dep000000002', 'Abteilung B', null);
        $m2->setNotificationEmail('geloescht@beispiel.ch');

        $body = $this->json($this->profileController()->departmentAssignments($this->me->getProfileId()));

        self::assertSame(['anna@beispiel.ch', 'anna@beispiel.ch'], array_column($body['assignments'], 'effective_email'));
        self::assertSame([null, null], array_column($body['assignments'], 'selected_email'));
    }

    public function testForeignProfileIsForbidden(): void
    {
        $this->membership($this->other, 'dep000000009', 'Fremde Abteilung', null);

        self::assertSame(403, $this->profileController()->departmentAssignments($this->other->getProfileId())->getStatusCode());
    }

    public function testSettingUsesTheExistingDepartmentApiOnlyForOwnMembershipAndOwnVerifiedAddresses(): void
    {
        $this->membership($this->me, 'dep000000001', 'Abteilung A', null);
        $this->membership($this->me, 'dep000000002', 'Abteilung B', null);
        $this->membership($this->other, 'dep000000009', 'Fremde Abteilung', null);
        $controller = $this->notificationController();

        // eine Adresse für mehrere Departments
        foreach (['dep000000001', 'dep000000002'] as $dep) {
            $ok = $controller->set($dep, $this->put(['email' => 'material@beispiel.ch']));
            self::assertSame(200, $ok->getStatusCode());
        }
        self::assertSame('material@beispiel.ch', $this->memberships['dep000000001']->getNotificationEmail());
        self::assertSame('material@beispiel.ch', $this->memberships['dep000000002']->getNotificationEmail());
        self::assertCount(2, $this->audits);

        // unbestätigt, fremd und unbekannt werden abgelehnt, nichts ändert sich
        self::assertSame(400, $controller->set('dep000000001', $this->put(['email' => 'offen@beispiel.ch']))->getStatusCode());
        self::assertSame(400, $controller->set('dep000000001', $this->put(['email' => 'bob@beispiel.ch']))->getStatusCode());
        self::assertSame('material@beispiel.ch', $this->memberships['dep000000001']->getNotificationEmail());

        // fremde Mitgliedschaft: 404, die Adresse der fremden Membership bleibt unberührt
        self::assertSame(404, $controller->set('dep000000009', $this->put(['email' => 'material@beispiel.ch']))->getStatusCode());
        self::assertNull($this->memberships['dep000000009']->getNotificationEmail());

        // Standard (Hauptadresse)
        self::assertSame(200, $controller->set('dep000000001', $this->put(['email' => null]))->getStatusCode());
        self::assertNull($this->memberships['dep000000001']->getNotificationEmail());
    }

    public function testChangeIsAuditedWithoutChangingTheDepartmentOrAnyIdentity(): void
    {
        $this->membership($this->me, 'dep000000001', 'Abteilung A', null);

        $this->notificationController()->set('dep000000001', $this->put(['email' => 'material@beispiel.ch']));

        self::assertSame('membership_notification_email_changed', $this->audits[0][0]);
        self::assertSame(['notification_email' => ['old' => null, 'new' => 'material@beispiel.ch']], $this->audits[0][1]);
        self::assertCount(0, $this->me->getExternalIdentities(), 'beeinflusst keine OAuth-Identität');
        self::assertSame('anna@beispiel.ch', $this->me->getProfile()?->getEmail());
        self::assertContains('membership_notification_email_changed', \App\Service\Auth\SecurityActivityService::actions());
    }

    /** @param array<string, mixed> $data */
    private function put(array $data): Request
    {
        return Request::create('/x', 'PUT', [], [], [], [], (string) json_encode($data));
    }

    /** @return array<string, mixed> */
    private function json(\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true);
    }

    private function resolver(): MembershipNotificationEmailResolver
    {
        $service = $this->createMock(UserEmailAliasService::class);
        $service->method('userOwnsEmail')->willReturnCallback(function (User $user, string $email): bool {
            $email = strtolower(trim($email));
            if (strtolower((string) $user->getProfile()?->getEmail()) === $email) {
                return true;
            }
            if ($user->getId() !== $this->me->getId()) {
                return false;
            }
            foreach ($this->aliases as $alias) {
                if ($alias->getEmail() === $email && $alias->isVerified()) {
                    return true;
                }
            }

            return false;
        });
        $service->method('listForUser')->willReturnCallback(fn (User $user): array => $user->getId() === $this->me->getId() ? $this->aliases : []);

        return new MembershipNotificationEmailResolver($service);
    }

    private function profileController(): ProfileEmailController
    {
        $controller = new ProfileEmailController($this->createMock(UserEmailAliasService::class), $this->resolver());

        return $this->withUser($controller, $this->me);
    }

    private function notificationController(): MembershipNotificationEmailController
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturnCallback(function (array $criteria): ?Membership {
            $m = $this->memberships[$criteria['departmentId']] ?? null;

            return $m !== null && $m->getUserId() === $criteria['userId'] ? $m : null;
        });
        $em->method('getRepository')->willReturn($repo);
        $audit = $this->createMock(AuditLogger::class);
        $audit->method('log')->willReturnCallback(function (string $t, string $id, string $action, $a = null, $b = null, $c = null, array $changes = []): void {
            $this->audits[] = [$action, $changes];
        });

        return $this->withUser(new MembershipNotificationEmailController($em, $this->resolver(), $audit), $this->me);
    }

    private function withUser(object $controller, User $user): object
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'api', ['ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $storage);
        $controller->setContainer($container);

        return $controller;
    }

    private function user(string $id, string $email): User
    {
        $profile = (new Profile())->setId('p_' . $id)->setEmail($email);

        return (new User())->setId($id)->setProfileId('p_' . $id)->setProfile($profile);
    }

    private function alias(string $email, bool $verified): UserEmailAlias
    {
        $alias = new UserEmailAlias();
        $alias->setId('al' . substr(md5($email), 0, 10));
        $alias->setUser($this->me);
        $alias->setEmail($email);
        if ($verified) {
            $alias->markVerified();
        }

        return $alias;
    }

    private function membership(User $user, string $departmentId, string $name, ?string $parentName): Membership
    {
        $department = (new Department())->setId($departmentId)->setName($name);
        if ($parentName !== null) {
            $department->setParent((new Department())->setId('par' . substr($departmentId, 3))->setName($parentName));
        }
        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);
        $user->getMemberships()->add($membership);
        $this->memberships[$departmentId] = $membership;

        return $membership;
    }
}
