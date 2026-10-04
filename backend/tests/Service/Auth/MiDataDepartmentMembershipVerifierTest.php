<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Department;
use App\Entity\ExternalIdentity;
use App\Entity\ExternalStructureIdentity;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ExternalStructureIdentityRepository;
use App\Service\Auth\HitobitoApiException;
use App\Service\Auth\HitobitoGroup;
use App\Service\Auth\HitobitoGroupLookup;
use App\Service\Auth\HitobitoParentChainResolver;
use App\Service\Auth\HitobitoRole;
use App\Service\Auth\HitobitoRoleLookup;
use App\Service\Auth\HitobitoOAuthSession;
use App\Service\Auth\MiDataDepartmentMembershipVerifier;
use App\Service\Auth\MiDataDepartmentVerificationStatus;
use App\Service\Auth\MiDataOAuthUserInfo;
use PHPUnit\Framework\TestCase;

final class MiDataDepartmentMembershipVerifierTest extends TestCase
{
    public function testMissingMiDataIdentityAndMissingMappingAreNotApplicable(): void
    {
        $user = $this->user();
        $department = $this->department();
        $roles = $this->createMock(HitobitoRoleLookup::class);
        $roles->expects(self::never())->method('getRolesForPerson');

        $noIdentity = $this->verifier($user, null, [$this->mapping($department)], $roles);
        self::assertSame(
            MiDataDepartmentVerificationStatus::NOT_APPLICABLE,
            $noIdentity->verify($user, $department, null)->status,
        );

        $identity = $this->identity($user);
        $noMapping = $this->verifier($user, $identity, [], $roles);
        self::assertSame(
            MiDataDepartmentVerificationStatus::NOT_APPLICABLE,
            $noMapping->verify($user, $department, null)->status,
        );
    }

    public function testAStoredIdentityWithoutTransientOAuthSessionIsUnavailable(): void
    {
        $user = $this->user();
        $department = $this->department();
        $verifier = $this->verifier(
            $user,
            $this->identity($user),
            [$this->mapping($department)],
            $this->createMock(HitobitoRoleLookup::class),
        );

        self::assertSame(
            MiDataDepartmentVerificationStatus::UNAVAILABLE,
            $verifier->verify($user, $department, null)->status,
        );
    }

    public function testDirectActiveRoleConfirmsAndReturnsTheRole(): void
    {
        $user = $this->user();
        $department = $this->department();
        $role = $this->role('100');
        $roleLookup = $this->createMock(HitobitoRoleLookup::class);
        $roleLookup->expects(self::once())
            ->method('getRolesForPerson')
            ->with('midata', 'access-token', 'person-1')
            ->willReturn([$role]);
        $groupLookup = $this->createMock(HitobitoGroupLookup::class);
        $groupLookup->expects(self::never())->method('getGroup');
        $verifier = $this->verifier(
            $user,
            $this->identity($user),
            [$this->mapping($department)],
            $roleLookup,
            $groupLookup,
        );

        $result = $verifier->verify($user, $department, $this->session());

        self::assertSame(MiDataDepartmentVerificationStatus::CONFIRMED, $result->status);
        self::assertSame($role, $result->role);
    }

    public function testActiveChildRoleConfirmsThroughTheParentChain(): void
    {
        $user = $this->user();
        $department = $this->department();
        $roleLookup = $this->createMock(HitobitoRoleLookup::class);
        $roleLookup->method('getRolesForPerson')->willReturn([$this->role('111')]);
        $groupLookup = $this->createMock(HitobitoGroupLookup::class);
        $groupLookup->expects(self::once())
            ->method('getGroup')
            ->with('midata', 'access-token', '111')
            ->willReturn(new HitobitoGroup('111', '100', 'Group::Woelfe', 'Untere Gruppe'));
        $verifier = $this->verifier(
            $user,
            $this->identity($user),
            [$this->mapping($department)],
            $roleLookup,
            $groupLookup,
        );

        self::assertSame(
            MiDataDepartmentVerificationStatus::CONFIRMED,
            $verifier->verify($user, $department, $this->session())->status,
        );
    }

    public function testDeeplyNestedActiveRoleConfirmsThroughTheParentChain(): void
    {
        $user = $this->user();
        $department = $this->department();
        $roleLookup = $this->createMock(HitobitoRoleLookup::class);
        $roleLookup->method('getRolesForPerson')->willReturn([$this->role('111')]);
        $groups = $this->createMock(HitobitoGroupLookup::class);
        $groups->method('getGroup')->willReturnCallback(
            static fn (string $provider, string $token, string $id): ?HitobitoGroup => match ($id) {
                '111' => new HitobitoGroup('111', '110', 'Group::Woelfe', 'Meute'),
                '110' => new HitobitoGroup('110', '100', 'Group::Abteilung', 'Abteilung'),
                default => null,
            },
        );
        $verifier = $this->verifier(
            $user,
            $this->identity($user),
            [$this->mapping($department)],
            $roleLookup,
            $groups,
        );

        self::assertSame(
            MiDataDepartmentVerificationStatus::CONFIRMED,
            $verifier->verify($user, $department, $this->session())->status,
        );
    }

    public function testInactiveRolesAndRolesInOtherGroupsAreNotConfirmed(): void
    {
        $user = $this->user();
        $department = $this->department();
        $today = new \DateTimeImmutable('today');
        $roles = $this->createMock(HitobitoRoleLookup::class);
        $roles->method('getRolesForPerson')->willReturn([
            $this->role('100', $today->modify('-2 days'), $today->modify('-1 day')),
            $this->role('100', $today->modify('+1 day'), null),
            $this->role('999'),
        ]);
        $groups = $this->createMock(HitobitoGroupLookup::class);
        $groups->expects(self::once())->method('getGroup')
            ->with('midata', 'access-token', '999')
            ->willReturn(new HitobitoGroup('999', null, 'Group::Abteilung', 'Andere Abteilung'));
        $verifier = $this->verifier(
            $user,
            $this->identity($user),
            [$this->mapping($department)],
            $roles,
            $groups,
        );

        self::assertSame(
            MiDataDepartmentVerificationStatus::NOT_CONFIRMED,
            $verifier->verify($user, $department, $this->session())->status,
        );
    }

    public function testRoleApiAndParentLookupErrorsAreUnavailable(): void
    {
        $user = $this->user();
        $department = $this->department();
        $roles = $this->createMock(HitobitoRoleLookup::class);
        $roles->method('getRolesForPerson')
            ->willThrowException(new HitobitoApiException('permission_denied', 'forbidden', 403));
        $verifier = $this->verifier($user, $this->identity($user), [$this->mapping($department)], $roles);

        self::assertSame(
            MiDataDepartmentVerificationStatus::UNAVAILABLE,
            $verifier->verify($user, $department, $this->session())->status,
        );

        $roles = $this->createMock(HitobitoRoleLookup::class);
        $roles->method('getRolesForPerson')->willReturn([$this->role('111')]);
        $groups = $this->createMock(HitobitoGroupLookup::class);
        $groups->method('getGroup')
            ->willThrowException(new HitobitoApiException('permission_denied', 'forbidden', 403));
        $verifier = $this->verifier(
            $user,
            $this->identity($user),
            [$this->mapping($department)],
            $roles,
            $groups,
        );

        self::assertSame(
            MiDataDepartmentVerificationStatus::UNAVAILABLE,
            $verifier->verify($user, $department, $this->session())->status,
        );
    }

    public function testMultipleDepartmentMappingsAreUnavailable(): void
    {
        $user = $this->user();
        $department = $this->department();
        $roleLookup = $this->createMock(HitobitoRoleLookup::class);
        $roleLookup->expects(self::never())->method('getRolesForPerson');
        $verifier = $this->verifier(
            $user,
            $this->identity($user),
            [$this->mapping($department, '100'), $this->mapping($department, '101')],
            $roleLookup,
        );

        self::assertSame(
            MiDataDepartmentVerificationStatus::UNAVAILABLE,
            $verifier->verify($user, $department, $this->session())->status,
        );
    }

    private function verifier(
        User $user,
        ?ExternalIdentity $identity,
        array $mappings,
        HitobitoRoleLookup $roleLookup,
        ?HitobitoGroupLookup $groupLookup = null,
    ): MiDataDepartmentMembershipVerifier {
        $identityRepository = $this->createMock(ExternalIdentityRepository::class);
        $identityRepository->method('findOneBy')->willReturn($identity);
        $mappingRepository = $this->createMock(ExternalStructureIdentityRepository::class);
        $mappingRepository->method('findByDepartment')->willReturn($mappings);

        return new MiDataDepartmentMembershipVerifier(
            $identityRepository,
            $mappingRepository,
            $roleLookup,
            new HitobitoParentChainResolver($groupLookup ?? $this->createMock(HitobitoGroupLookup::class)),
        );
    }

    private function user(): User
    {
        $user = new User();
        $user->setId('user-123456');

        return $user;
    }

    private function department(): Department
    {
        return (new Department())->setId('department-1');
    }

    private function identity(User $user): ExternalIdentity
    {
        return (new ExternalIdentity())
            ->setUser($user)
            ->setProvider('midata')
            ->setExternalUserId('person-1');
    }

    private function mapping(Department $department, string $externalGroupId = '100'): ExternalStructureIdentity
    {
        return (new ExternalStructureIdentity())
            ->setProvider('midata')
            ->setExternalGroupId($externalGroupId)
            ->setDepartment($department);
    }

    private function session(string $subject = 'person-1'): HitobitoOAuthSession
    {
        return new HitobitoOAuthSession(
            'midata',
            'access-token',
            new MiDataOAuthUserInfo($subject, null, false, null, null),
        );
    }

    private function role(
        string $groupId,
        ?\DateTimeImmutable $startOn = null,
        ?\DateTimeImmutable $endOn = null,
    ): HitobitoRole {
        return new HitobitoRole('person-1', $groupId, 'Role::Leader', $startOn, $endOn);
    }
}
