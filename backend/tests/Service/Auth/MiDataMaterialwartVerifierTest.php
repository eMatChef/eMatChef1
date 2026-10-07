<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\HitobitoApiException;
use App\Service\Auth\HitobitoGroup;
use App\Service\Auth\HitobitoGroupLookup;
use App\Service\Auth\HitobitoOAuthSession;
use App\Service\Auth\HitobitoParentChainResolver;
use App\Service\Auth\HitobitoRole;
use App\Service\Auth\HitobitoRoleLookup;
use App\Service\Auth\MiDataMaterialwartVerificationStatus;
use App\Service\Auth\MiDataMaterialwartVerifier;
use App\Service\Auth\MiDataOAuthUserInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MiDataMaterialwartVerifierTest extends TestCase
{
    private const TODAY = '2026-10-04';

    public function testActiveMaterialwartOfTheAbteilungIsConfirmedWithFullPbsStructure(): void
    {
        $result = $this->verifier([$this->materialwart('51')])->verify($this->session(), '1131', '51', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::CONFIRMED, $result->status);
        self::assertTrue($result->isConfirmed());
        self::assertSame('1131', $result->externalPersonId);
        self::assertSame('51', $result->department?->id);
        self::assertSame(['50'], array_map(static fn (HitobitoGroup $group): string => $group->id, $result->regions));
        self::assertSame('49', $result->kantonalverband?->id);
        self::assertSame('Group::Kantonalverband', $result->kantonalverband?->type);
        self::assertSame('1', $result->bund?->id);
        self::assertSame('Group::Bund', $result->bund?->type);
    }

    public function testTechnicalRootIsNeverPartOfTheResult(): void
    {
        $result = $this->verifier([$this->materialwart('51')])->verify($this->session(), '1131', '51', $this->today());

        $ids = array_map(
            static fn (?HitobitoGroup $group): ?string => $group?->id,
            [$result->department, ...$result->regions, $result->kantonalverband, $result->bund],
        );
        self::assertNotContains('1113', $ids);
    }

    public function testSeveralRegionLevelsAreReturnedFromTopToBottom(): void
    {
        $groups = $this->groupLookup([
            new HitobitoGroup('91', '50', 'Group::Region', 'Unterregion'),
            new HitobitoGroup('71', '91', 'Group::Abteilung', 'Pfadi Tief'),
        ]);

        $result = $this->verifier([$this->materialwart('71')], $groups)
            ->verify($this->session(), '1131', '71', $this->today());

        self::assertTrue($result->isConfirmed());
        self::assertSame(['50', '91'], array_map(static fn (HitobitoGroup $group): string => $group->id, $result->regions));
        self::assertSame('49', $result->kantonalverband?->id);
    }

    public function testRoleDatesAreInclusive(): void
    {
        $today = $this->today();
        $verifier = $this->verifier([$this->materialwart('51', $today, $today)]);

        self::assertTrue($verifier->verify($this->session(), '1131', '51', $today)->isConfirmed());
    }

    /**
     * @return iterable<string, array{0: ?string, 1: ?string}>
     */
    public static function inactiveRoleDates(): iterable
    {
        yield 'expired' => [null, '2026-10-03'];
        yield 'future' => ['2026-10-05', null];
    }

    #[DataProvider('inactiveRoleDates')]
    public function testInactiveMaterialwartRoleIsNotConfirmed(?string $startOn, ?string $endOn): void
    {
        $role = $this->materialwart(
            '51',
            $startOn !== null ? new \DateTimeImmutable($startOn) : null,
            $endOn !== null ? new \DateTimeImmutable($endOn) : null,
        );

        $result = $this->verifier([$role])->verify($this->session(), '1131', '51', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, $result->status);
        self::assertFalse($result->isConfirmed());
    }

    public function testStufenleitungWithoutMaterialwartIsNotPrivileged(): void
    {
        $role = new HitobitoRole('1131', '51', 'Group::Abteilung::StufenleitungPta', null, null);

        $result = $this->verifier([$role])->verify($this->session(), '1131', '51', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, $result->status);
    }

    public function testMaterialwartRoleNameOrGroupNameIsNoAuthorization(): void
    {
        $role = new HitobitoRole(
            '1131',
            '51',
            'Group::Abteilung::Leitung',
            null,
            null,
            groupName: 'Pfadi Zytturm',
            roleName: 'Materialwart*in',
        );

        $result = $this->verifier([$role])->verify($this->session(), '1131', '51', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, $result->status);
    }

    public function testUserinfoMaterialwartClaimWithoutApiRoleHasNoEffect(): void
    {
        $session = $this->session(userInfoRoles: [$this->materialwart('51')]);

        $result = $this->verifier([])->verify($session, '1131', '51', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, $result->status);
    }

    public function testMaterialwartOfAnotherGroupCannotBeUsedForTheRequestedGroup(): void
    {
        $result = $this->verifier([$this->materialwart('51')])->verify($this->session(), '1131', '60', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, $result->status);
    }

    public function testMaterialwartRoleOnASubgroupIsNotAnAbteilung(): void
    {
        $result = $this->verifier([$this->materialwart('52')])->verify($this->session(), '1131', '52', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, $result->status);
    }

    public function testRoleOfAnotherPersonIsIgnored(): void
    {
        $role = new HitobitoRole('9999', '51', MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS, null, null);

        $result = $this->verifier([$role])->verify($this->session(), '1131', '51', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, $result->status);
    }

    public function testSessionOfAnotherPersonIsRejectedWithoutApiCalls(): void
    {
        $roles = $this->createMock(HitobitoRoleLookup::class);
        $roles->expects(self::never())->method('getRolesForPerson');
        $verifier = new MiDataMaterialwartVerifier(
            $roles,
            $this->groupLookup(),
            new HitobitoParentChainResolver($this->groupLookup()),
        );

        $result = $verifier->verify($this->session('2222'), '1131', '51', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, $result->status);
    }

    public function testApiFailureIsUnavailable(): void
    {
        $roles = $this->createMock(HitobitoRoleLookup::class);
        $roles->method('getRolesForPerson')->willThrowException(new HitobitoApiException('transport_error', 'down'));
        $verifier = new MiDataMaterialwartVerifier(
            $roles,
            $this->groupLookup(),
            new HitobitoParentChainResolver($this->groupLookup()),
        );

        $result = $verifier->verify($this->session(), '1131', '51', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::UNAVAILABLE, $result->status);
    }

    public function testAbteilungDirectlyBelowKantonalverbandHasNoRegion(): void
    {
        $groups = $this->groupLookup([new HitobitoGroup('70', '49', 'Group::Abteilung', 'Pfadi Direkt')]);

        $result = $this->verifier([$this->materialwart('70')], $groups)
            ->verify($this->session(), '1131', '70', $this->today());

        self::assertTrue($result->isConfirmed());
        self::assertSame([], $result->regions);
        self::assertSame('49', $result->kantonalverband?->id);
        self::assertSame('1', $result->bund?->id);
    }

    /**
     * @return iterable<string, array{0: list<HitobitoGroup>, 1: string}>
     */
    public static function unsupportedStructures(): iterable
    {
        yield 'Kantonalverband without Bund' => [[
            new HitobitoGroup('60', '1113', 'Group::Kantonalverband', 'KV ohne Bund'),
            new HitobitoGroup('61', '60', 'Group::Abteilung', 'Abteilung'),
        ], '61'];
        yield 'unknown level between Abteilung and Kantonalverband' => [[
            new HitobitoGroup('62', '49', 'Group::Pfadi', 'Stufe'),
            new HitobitoGroup('63', '62', 'Group::Abteilung', 'Abteilung'),
        ], '63'];
        yield 'unknown level above Bund' => [[
            new HitobitoGroup('64', '65', 'Group::Bund', 'Anderer Bund'),
            new HitobitoGroup('65', null, 'Group::Weltverband', 'Weltverband'),
            new HitobitoGroup('66', '64', 'Group::Kantonalverband', 'KV'),
            new HitobitoGroup('67', '66', 'Group::Abteilung', 'Abteilung'),
        ], '67'];
    }

    /**
     * @param list<HitobitoGroup> $extraGroups
     */
    #[DataProvider('unsupportedStructures')]
    public function testUnexpectedStructureIsUnsupported(array $extraGroups, string $groupId): void
    {
        $result = $this->verifier([$this->materialwart($groupId)], $this->groupLookup($extraGroups))
            ->verify($this->session(), '1131', $groupId, $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::UNSUPPORTED_STRUCTURE, $result->status);
    }

    public function testAbteilungWithoutKantonalverbandBelowBundIsUnsupported(): void
    {
        $groups = $this->groupLookup([new HitobitoGroup('80', '1', 'Group::Abteilung', 'Bundesabteilung')]);

        $result = $this->verifier([$this->materialwart('80')], $groups)
            ->verify($this->session(), '1131', '80', $this->today());

        self::assertSame(MiDataMaterialwartVerificationStatus::UNSUPPORTED_STRUCTURE, $result->status);
    }

    /**
     * @param list<HitobitoRole> $apiRoles
     */
    private function verifier(array $apiRoles, ?HitobitoGroupLookup $groups = null): MiDataMaterialwartVerifier
    {
        $roles = $this->createMock(HitobitoRoleLookup::class);
        $roles->method('getRolesForPerson')->with('midata', 'access-token', '1131')->willReturn($apiRoles);
        $groups ??= $this->groupLookup();

        return new MiDataMaterialwartVerifier($roles, $groups, new HitobitoParentChainResolver($groups));
    }

    /**
     * Live PBS structure verified during the MiData smoke test.
     *
     * @param list<HitobitoGroup> $extraGroups
     */
    private function groupLookup(array $extraGroups = []): HitobitoGroupLookup
    {
        $groups = [
            new HitobitoGroup('51', '50', 'Group::Abteilung', 'Pfadi Zytturm', '51'),
            new HitobitoGroup('52', '51', 'Group::Pfadi', 'Pfadistufe', '51'),
            new HitobitoGroup('50', '49', 'Group::Region', 'Corps Musegg', '50'),
            new HitobitoGroup('49', '1', 'Group::Kantonalverband', 'Pfadi Luzern', '49'),
            new HitobitoGroup('1', '1113', 'Group::Bund', 'Pfadibewegung Schweiz', '1'),
            new HitobitoGroup('1113', null, 'Group::Root', 'hitobito', '1113'),
            ...$extraGroups,
        ];
        $byId = [];
        foreach ($groups as $group) {
            $byId[$group->id] = $group;
        }

        return new class ($byId) implements HitobitoGroupLookup {
            /**
             * @param array<string, HitobitoGroup> $groups
             */
            public function __construct(private readonly array $groups) {}

            public function getGroup(string $provider, string $accessToken, string $groupId): ?HitobitoGroup
            {
                return $this->groups[$groupId] ?? null;
            }
        };
    }

    /**
     * @param list<HitobitoRole> $userInfoRoles
     */
    private function session(string $subject = '1131', array $userInfoRoles = []): HitobitoOAuthSession
    {
        return new HitobitoOAuthSession(
            'midata',
            'access-token',
            new MiDataOAuthUserInfo($subject, null, false, null, null, $userInfoRoles),
        );
    }

    private function materialwart(
        string $groupId,
        ?\DateTimeImmutable $startOn = null,
        ?\DateTimeImmutable $endOn = null,
    ): HitobitoRole {
        return new HitobitoRole('1131', $groupId, MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS, $startOn, $endOn);
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::TODAY);
    }
}
