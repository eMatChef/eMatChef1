<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Department;
use App\Entity\ExternalIdentity;
use App\Entity\ExternalStructureIdentity;
use App\Entity\MiDataDepartmentOnboarding;
use App\Entity\MiDataMembershipCandidate;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ExternalStructureIdentityRepository;
use App\Repository\MiDataDepartmentOnboardingRepository;
use App\Repository\MiDataMembershipCandidateRepository;
use App\Service\Auth\HitobitoApiException;
use App\Service\Auth\HitobitoGroup;
use App\Service\Auth\HitobitoOAuthSession;
use App\Service\Auth\HitobitoRole;
use App\Service\Auth\MiDataDepartmentOnboardingOutcomeStatus;
use App\Service\Auth\MiDataDepartmentOnboardingService;
use App\Service\Auth\MiDataDepartmentProvisioningResult;
use App\Service\Auth\MiDataDepartmentStructureProvisioner;
use App\Service\Auth\MiDataMaterialwartVerification;
use App\Service\Auth\MiDataMaterialwartVerificationStatus;
use App\Service\Auth\MiDataMaterialwartVerifier;
use App\Service\Auth\MiDataOAuthUserInfo;
use App\Service\Auth\MiDataStructureConflictException;
use App\Service\Support\UnassignedUserSupportQueue;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class MiDataDepartmentOnboardingServiceTest extends TestCase
{
    private User $user;
    private EntityManagerInterface&MockObject $entityManager;
    private MiDataDepartmentOnboardingRepository&MockObject $onboardings;
    private MiDataMembershipCandidateRepository&MockObject $candidateRepository;
    private ExternalIdentityRepository&MockObject $externalIdentities;
    private ExternalStructureIdentityRepository&MockObject $structureIdentities;
    private MiDataMaterialwartVerifier&MockObject $verifier;
    private MiDataDepartmentStructureProvisioner&MockObject $provisioner;
    private UnassignedUserSupportQueue&MockObject $supportQueue;
    /** @var array<string, MiDataMembershipCandidate> keyed by ID */
    private array $candidates = [];
    /** @var array<string, string> external group ID => internal department ID */
    private array $mappedDepartments = [];
    /** @var list<string> department IDs the user belongs to */
    private array $memberOf = [];
    /** @var list<object> */
    private array $persisted = [];
    /** @var list<object> */
    private array $removed = [];
    private int $roleLoads = 0;
    /** @var list<string> */
    private array $verifiedGroups = [];

    protected function setUp(): void
    {
        $this->user = (new User())->setId('user00000001');
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $memberships = $this->createMock(EntityRepository::class);
        $memberships->method('findOneBy')->willReturnCallback(fn (array $criteria): ?Membership => in_array(
            $criteria['departmentId'] ?? null,
            $this->memberOf,
            true,
        ) ? new Membership() : null);
        $generic = $this->createMock(EntityRepository::class);
        $generic->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === Membership::class ? $memberships : $generic,
        );
        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
            if ($entity instanceof MiDataMembershipCandidate) {
                $this->candidates[(string) $entity->getId()] = $entity;
            }
        });
        $this->entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
            if ($entity instanceof MiDataMembershipCandidate) {
                unset($this->candidates[(string) $entity->getId()]);
            }
        });

        $this->onboardings = $this->createMock(MiDataDepartmentOnboardingRepository::class);
        $this->candidateRepository = $this->createMock(MiDataMembershipCandidateRepository::class);
        $this->candidateRepository->method('findAllForUser')->willReturnCallback(fn (User $user): array => array_values(array_filter(
            $this->candidates,
            static fn (MiDataMembershipCandidate $candidate): bool => $candidate->getUserId() === $user->getId(),
        )));
        $this->candidateRepository->method('findOpenForUser')->willReturnCallback(fn (User $user, \DateTimeInterface $now): array => array_values(array_filter(
            $this->candidates,
            static fn (MiDataMembershipCandidate $candidate): bool => $candidate->getUserId() === $user->getId() && !$candidate->isExpired($now),
        )));
        $this->candidateRepository->method('find')->willReturnCallback(fn (string $id): ?MiDataMembershipCandidate => $this->candidates[$id] ?? null);

        $this->externalIdentities = $this->createMock(ExternalIdentityRepository::class);
        $this->structureIdentities = $this->createMock(ExternalStructureIdentityRepository::class);
        $this->structureIdentities->method('findOneByProviderAndExternalGroupId')->willReturnCallback(
            function (string $provider, string $groupId): ?ExternalStructureIdentity {
                if (!isset($this->mappedDepartments[$groupId])) {
                    return null;
                }

                return (new ExternalStructureIdentity())->setProvider('midata')->setExternalGroupId($groupId)
                    ->setDepartment((new Department())->setId($this->mappedDepartments[$groupId]));
            },
        );

        $this->verifier = $this->createMock(MiDataMaterialwartVerifier::class);
        $this->verifier->method('loadRoles')->willReturnCallback(function (): array {
            $this->roleLoads++;

            return [new HitobitoRole('1131', '51', MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS, null, null)];
        });
        $this->provisioner = $this->createMock(MiDataDepartmentStructureProvisioner::class);
        $this->supportQueue = $this->createMock(UnassignedUserSupportQueue::class);
    }

    public function testOneMaterialwartAbteilungReceivesOneScopedOffer(): void
    {
        $this->givenMappedBund();
        $this->givenVerifierConfirmsEveryGroup();
        $this->entityManager->expects(self::once())->method('flush');

        $offered = $this->service()->offerFromOAuthCallback(
            $this->user,
            $this->session(roles: [$this->userInfoRole('51', 'Group::Abteilung::StufenleitungPta'), $this->userInfoRole('51')]),
        );

        self::assertSame(1, $offered);
        $offers = $this->offers();
        self::assertCount(1, $offers);
        $offer = $offers[0];
        self::assertSame('user00000001', $offer->getUserId());
        self::assertSame('1131', $offer->getExternalPersonId());
        self::assertSame('51', $offer->getExternalDepartmentGroupId());
        self::assertSame('Group::Abteilung::Materialwart', $offer->getExternalRoleClass());
        self::assertSame('Abteilung 51', $offer->getDepartmentName());
        self::assertSame('Corps Musegg', $offer->getRegionName());
        self::assertSame('Pfadi Luzern', $offer->getKantonalverbandName());
        self::assertFalse($offer->isExpired(new \DateTime('+23 hours')));
        self::assertTrue($offer->isExpired(new \DateTime('+25 hours')));
    }

    public function testSeveralAbteilungenGetOneOfferEachWithASingleRolesLoad(): void
    {
        $this->givenMappedBund();
        $this->givenVerifierConfirmsEveryGroup();

        $offered = $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: [
            $this->userInfoRole('62', null, 'Pfadi Beta'),
            $this->userInfoRole('61', null, 'Pfadi Alpha'),
            $this->userInfoRole('61', null, 'Pfadi Alpha'),
        ]));

        self::assertSame(2, $offered);
        self::assertSame(['61', '62'], $this->sortedOfferGroups());
        self::assertSame(1, $this->roleLoads, '/api/roles is paginated once per callback');
        self::assertSame(['62', '61'], $this->verifiedGroups);
    }

    public function testUpToTenAbteilungenAreVerifiedDirectly(): void
    {
        $this->givenMappedBund();
        $this->givenVerifierConfirmsEveryGroup();

        $offered = $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: $this->materialwartRoles(10)));

        self::assertSame(10, $offered);
        self::assertCount(10, $this->verifiedGroups);
        self::assertSame(1, $this->roleLoads);
        self::assertFalse($this->service()->isSearchRequired($this->user));
    }

    public function testMoreThanTenAbteilungenSwitchToSearchModeWithoutVerification(): void
    {
        $this->givenMappedBund();
        $this->verifier->expects(self::never())->method('verify');

        $offered = $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: $this->materialwartRoles(11)));

        self::assertSame(0, $offered);
        self::assertSame([], $this->offers());
        self::assertSame(0, $this->roleLoads);
        self::assertCount(11, $this->candidates);
        self::assertTrue($this->service()->isSearchRequired($this->user));
    }

    public function testThirtyEightCandidatesCauseNoHierarchyVerification(): void
    {
        $this->verifier->expects(self::never())->method('verify');
        $this->verifier->expects(self::never())->method('loadRoles');

        $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: $this->materialwartRoles(38)));

        self::assertCount(38, $this->candidates);
    }

    public function testCandidatesStoreOnlyMinimalData(): void
    {
        $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: $this->materialwartRoles(11)));

        $candidate = array_values($this->candidates)[0];
        self::assertSame('user00000001', $candidate->getUserId());
        self::assertSame('midata', $candidate->getProvider());
        self::assertSame(MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS, $candidate->getExternalRoleClass());
        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new \ReflectionClass(MiDataMembershipCandidate::class))->getProperties(),
        );
        self::assertSame(
            ['id', 'userId', 'user', 'provider', 'externalGroupId', 'externalRoleClass', 'displayName', 'expiresAt', 'createdAt', 'updatedAt'],
            $properties,
            'no tokens, payloads, e-mail or unverified hierarchy',
        );
    }

    public function testRepeatedLoginRefreshesCandidatesAndDropsVanishedOnes(): void
    {
        $service = $this->service();
        $service->offerFromOAuthCallback($this->user, $this->session(roles: $this->materialwartRoles(12)));
        $foreign = (new MiDataMembershipCandidate())->setId('foreigncand1')->setUser((new User())->setId('user00000002'))
            ->setExternalGroupId('100')->setDisplayName('Fremd')->setExpiresAt(new \DateTime('+1 hour'));
        $this->candidates['foreigncand1'] = $foreign;
        $ids = array_keys($this->candidates);

        $service->offerFromOAuthCallback($this->user, $this->session(roles: array_slice($this->materialwartRoles(12), 1)));

        self::assertCount(12, $this->candidates, '11 own + 1 foreign');
        self::assertArrayHasKey('foreigncand1', $this->candidates);
        self::assertNotContains($foreign, $this->removed);
        self::assertSame(array_slice($ids, 1), array_keys($this->candidates), 'existing candidates are updated, not recreated');
    }

    public function testExistingMembershipOfTheMappedDepartmentSkipsTheOffer(): void
    {
        $this->givenMappedBund();
        $this->givenVerifierConfirmsEveryGroup();
        $this->mappedDepartments = ['61' => 'dept0000000a'];
        $this->memberOf = ['dept0000000a'];

        $offered = $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: [
            $this->userInfoRole('61', null, 'Pfadi Alpha'),
            $this->userInfoRole('62', null, 'Pfadi Beta'),
        ]));

        self::assertSame(1, $offered, 'membership in A does not block B');
        self::assertSame(['62'], $this->sortedOfferGroups());
        self::assertSame(['62'], $this->verifiedGroups);
    }

    public function testListLeavesOutOffersOfDepartmentsTheUserAlreadyBelongsTo(): void
    {
        $this->mappedDepartments = ['61' => 'dept0000000a'];
        $this->memberOf = ['dept0000000a'];
        $this->onboardings->method('findOpenForUser')->willReturn([
            $this->offer('offer0000061', '61'),
            $this->offer('offer0000062', '62'),
        ]);

        $ids = array_map(static fn (MiDataDepartmentOnboarding $offer): ?string => $offer->getId(), $this->service()->listOpenOffers($this->user));

        self::assertSame(['offer0000062'], $ids);
    }

    public function testUnmappedBundCreatesNoOfferAndNoStructure(): void
    {
        $this->givenVerifierConfirmsEveryGroup();
        $this->provisioner->method('findMappedBundOrganisation')
            ->willThrowException(new MiDataStructureConflictException('bund_not_mapped', '1'));
        $this->provisioner->expects(self::never())->method('provision');

        self::assertSame(0, $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: [$this->userInfoRole('51')])));
        self::assertSame([], $this->offers());
    }

    public function testNormalAndStufenleitungUsersReceiveNoOffer(): void
    {
        $this->verifier->expects(self::never())->method('verify');
        $this->verifier->expects(self::never())->method('loadRoles');

        self::assertSame(0, $this->service()->offerFromOAuthCallback($this->user, $this->session()));
        self::assertSame(0, $this->service()->offerFromOAuthCallback(
            $this->user,
            $this->session(roles: [$this->userInfoRole('51', 'Group::Abteilung::StufenleitungPta')]),
        ));
        self::assertSame([], $this->offers());
    }

    public function testUnavailableRolesCreateNoOffer(): void
    {
        $verifier = $this->createMock(MiDataMaterialwartVerifier::class);
        $verifier->method('loadRoles')->willThrowException(new HitobitoApiException('transport_error', 'down'));
        $verifier->expects(self::never())->method('verify');
        $this->verifier = $verifier;

        self::assertSame(0, $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: [$this->userInfoRole('51')])));
    }

    public function testCandidateSearchIsCaseAndAccentInsensitiveAndLimitedToOwnCandidates(): void
    {
        $this->candidate('cand00000001', 'Pfadi Zürich Nord');
        $this->candidate('cand00000002', 'Pfadi Zug');
        $this->candidate('cand00000003', 'Schneckenberg');
        $this->candidate('cand00000004', 'Pfadi Zürich Süd', (new User())->setId('user00000002'));
        $this->candidate('cand00000005', 'Pfadi Zürich Alt', expiresAt: new \DateTime('-1 minute'));

        $names = array_map(
            static fn (MiDataMembershipCandidate $candidate): string => $candidate->getDisplayName(),
            $this->service()->searchCandidates($this->user, 'zurich'),
        );

        self::assertSame(['Pfadi Zürich Nord'], $names);
        self::assertSame([], $this->service()->searchCandidates($this->user, '  '));
    }

    public function testCandidateSearchReturnsAtMostTenHits(): void
    {
        foreach (range(1, 14) as $index) {
            $this->candidate(sprintf('cand%08d', $index), sprintf('Pfadi %02d', $index));
        }

        self::assertCount(10, $this->service()->searchCandidates($this->user, 'pfadi'));
    }

    public function testSelectedCandidateIsVerifiedFreshlyAndSetUpDirectly(): void
    {
        $this->givenMappedBund();
        $this->givenIdentity('1131');
        $candidate = $this->candidate('cand00000051', 'Schneckenberg', groupId: '51');
        $department = (new Department())->setId('dep000000051');
        $verification = $this->confirmed('51');
        $this->verifier->expects(self::once())->method('verify')
            ->with(self::anything(), '1131', '51')
            ->willReturn($verification);
        $this->provisioner->expects(self::once())->method('provision')
            ->willReturnCallback(function (User $user, MiDataMaterialwartVerification $given, ?MiDataDepartmentOnboarding $offer) use ($verification, $department): MiDataDepartmentProvisioningResult {
                self::assertSame($verification, $given);
                self::assertSame('51', $offer?->getExternalDepartmentGroupId());

                return new MiDataDepartmentProvisioningResult($department, true, ['51'], true);
            });

        $outcome = $this->service()->completeCandidateFromOAuthCallback($this->user, (string) $candidate->getId(), $this->session());

        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::CREATED, $outcome->status);
        self::assertCount(1, $this->offers(), 'the verified selection becomes a real offer');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unusableCandidateIds(): iterable
    {
        yield 'foreign candidate' => ['cand0foreign'];
        yield 'manipulated id' => ['manipulated1'];
        yield 'technical role changed' => ['cand0badrole'];
    }

    #[DataProvider('unusableCandidateIds')]
    public function testForeignManipulatedOrUnsupportedCandidatesCreateNothing(string $candidateId): void
    {
        $this->givenIdentity('1131');
        $this->candidate('cand0foreign', 'Fremd', (new User())->setId('user00000002'));
        $this->candidate('cand0badrole', 'Rolle', roleClass: 'Group::Abteilung::Abteilungsleitung');
        $this->verifier->expects(self::never())->method('verify');
        $this->provisioner->expects(self::never())->method('provision');

        $outcome = $this->service()->completeCandidateFromOAuthCallback($this->user, $candidateId, $this->session());

        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::DENIED, $outcome->status);
        self::assertSame('candidate_not_found', $outcome->reason);
        self::assertSame([], $this->offers());
    }

    public function testExpiredCandidateIsRejected(): void
    {
        $this->candidate('cand0expired', 'Alt', expiresAt: new \DateTime('-1 second'));
        $this->verifier->expects(self::never())->method('verify');

        $outcome = $this->service()->completeCandidateFromOAuthCallback($this->user, 'cand0expired', $this->session());

        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::EXPIRED, $outcome->status);
    }

    /**
     * @return iterable<string, array{0: MiDataMaterialwartVerificationStatus, 1: MiDataDepartmentOnboardingOutcomeStatus}>
     */
    public static function failedCandidateVerifications(): iterable
    {
        yield 'role removed, inactive or wrong group type' => [MiDataMaterialwartVerificationStatus::NOT_CONFIRMED, MiDataDepartmentOnboardingOutcomeStatus::DENIED];
        yield 'unexpected structure' => [MiDataMaterialwartVerificationStatus::UNSUPPORTED_STRUCTURE, MiDataDepartmentOnboardingOutcomeStatus::UNSUPPORTED_STRUCTURE];
        yield 'MiData unavailable' => [MiDataMaterialwartVerificationStatus::UNAVAILABLE, MiDataDepartmentOnboardingOutcomeStatus::UNAVAILABLE];
    }

    #[DataProvider('failedCandidateVerifications')]
    public function testCandidateWhoseFreshVerificationFailsCreatesNoOffer(
        MiDataMaterialwartVerificationStatus $verificationStatus,
        MiDataDepartmentOnboardingOutcomeStatus $expected,
    ): void {
        $this->givenIdentity('1131');
        $this->candidate('cand00000051', 'Schneckenberg', groupId: '51');
        $this->verifier->method('verify')->willReturn(new MiDataMaterialwartVerification($verificationStatus));
        $this->provisioner->expects(self::never())->method('provision');

        $outcome = $this->service()->completeCandidateFromOAuthCallback($this->user, 'cand00000051', $this->session());

        self::assertSame($expected, $outcome->status);
        self::assertSame([], $this->offers());
    }

    public function testCandidateWithUnmappedBundIsAConflictWithoutOffer(): void
    {
        $this->givenIdentity('1131');
        $this->candidate('cand00000051', 'Schneckenberg', groupId: '51');
        $this->verifier->method('verify')->willReturn($this->confirmed('51'));
        $this->provisioner->method('findMappedBundOrganisation')
            ->willThrowException(new MiDataStructureConflictException('bund_not_mapped', '1'));
        $this->provisioner->expects(self::never())->method('provision');

        $outcome = $this->service()->completeCandidateFromOAuthCallback($this->user, 'cand00000051', $this->session());

        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::CONFLICT, $outcome->status);
        self::assertSame('bund_not_mapped', $outcome->reason);
        self::assertSame([], $this->offers());
    }

    public function testCandidateOfAnotherMiDataPersonIsDenied(): void
    {
        $this->givenIdentity('2222');
        $this->candidate('cand00000051', 'Schneckenberg', groupId: '51');
        $this->verifier->expects(self::never())->method('verify');

        $outcome = $this->service()->completeCandidateFromOAuthCallback($this->user, 'cand00000051', $this->session());

        self::assertSame('identity_mismatch', $outcome->reason);
    }

    public function testRepeatedLoginRefreshesTheExistingOffer(): void
    {
        $existing = $this->offer();
        $existing->setCompletedAt(new \DateTime('-1 day'));
        $existing->setExpiresAt(new \DateTime('-1 hour'));
        $this->givenMappedBund();
        $this->onboardings->method('findOneForUserAndExternalGroup')->willReturn($existing);
        $this->givenVerifierConfirmsEveryGroup();

        $this->service()->offerFromOAuthCallback($this->user, $this->session(roles: [$this->userInfoRole('51')]));

        self::assertSame([], $this->offers());
        self::assertNull($existing->getCompletedAt());
        self::assertFalse($existing->isExpired(new \DateTime()));
    }

    public function testCompletionCreatesTheStructureForTheStoredExternalGroup(): void
    {
        $offer = $this->offer();
        $this->givenOffer($offer);
        $this->givenIdentity('1131');
        $department = (new Department())->setId('dep000000051');
        $verification = $this->confirmed('51');
        $this->verifier->expects(self::once())->method('verify')
            ->with(self::anything(), '1131', '51')
            ->willReturn($verification);
        $this->provisioner->expects(self::once())->method('provision')
            ->with($this->user, $verification, $offer)
            ->willReturn(new MiDataDepartmentProvisioningResult($department, true, ['49', '50', '51'], true));

        $outcome = $this->service()->completeFromOAuthCallback($this->user, 'offer0000001', $this->session());

        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::CREATED, $outcome->status);
        self::assertSame($department, $outcome->department);
    }

    public function testCompletionForAnExistingAbteilungJoinsOrKeepsTheMembership(): void
    {
        $this->givenOffer($this->offer());
        $this->givenIdentity('1131');
        $department = (new Department())->setId('dep000000051');
        $this->verifier->method('verify')->willReturn($this->confirmed('51'));
        $this->provisioner->method('provision')->willReturnOnConsecutiveCalls(
            new MiDataDepartmentProvisioningResult($department, false, [], true),
            new MiDataDepartmentProvisioningResult($department, false, [], false),
        );

        $service = $this->service();
        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::JOINED, $service->completeFromOAuthCallback($this->user, 'offer0000001', $this->session())->status);
        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::ALREADY_MEMBER, $service->completeFromOAuthCallback($this->user, 'offer0000001', $this->session())->status);
    }

    public function testSuccessfulCompletionClosesTheLegacyAutomaticSupportRequest(): void
    {
        $this->givenOffer($this->offer());
        $this->givenIdentity('1131');
        $department = (new Department())->setId('dep000000051');
        $this->verifier->method('verify')->willReturn($this->confirmed('51'));
        $this->provisioner->method('provision')->willReturn(new MiDataDepartmentProvisioningResult($department, true, ['51'], true));
        $this->supportQueue->expects(self::once())->method('resolveForAssignedUser')
            ->with($this->user, $department, 'midata_onboarding')->willReturn(1);

        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::CREATED, $this->service()->completeFromOAuthCallback($this->user, 'offer0000001', $this->session())->status);
    }

    public function testFailingSupportCleanupDoesNotFailTheOnboarding(): void
    {
        $this->givenOffer($this->offer());
        $this->givenIdentity('1131');
        $department = (new Department())->setId('dep000000051');
        $this->verifier->method('verify')->willReturn($this->confirmed('51'));
        $this->provisioner->method('provision')->willReturn(new MiDataDepartmentProvisioningResult($department, false, [], true));
        $this->supportQueue->method('resolveForAssignedUser')->willThrowException(new \RuntimeException('db down'));

        $outcome = $this->service()->completeFromOAuthCallback($this->user, 'offer0000001', $this->session());

        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::JOINED, $outcome->status);
    }

    public function testDeniedCompletionDoesNotTouchSupportRequests(): void
    {
        $this->givenOffer($this->offer());
        $this->givenIdentity('1131');
        $this->verifier->method('verify')->willReturn(new MiDataMaterialwartVerification(MiDataMaterialwartVerificationStatus::NOT_CONFIRMED));
        $this->supportQueue->expects(self::never())->method('resolveForAssignedUser');
        $this->provisioner->expects(self::never())->method('provision');

        $outcome = $this->service()->completeFromOAuthCallback($this->user, 'offer0000001', $this->session());

        self::assertSame('role_not_confirmed', $outcome->reason);
    }

    public function testOfferOfAnotherUserOrUnknownIdIsDenied(): void
    {
        $this->onboardings->method('find')->willReturnCallback(fn (string $id): ?MiDataDepartmentOnboarding => $id === 'offer0000001'
            ? $this->offer(user: (new User())->setId('user00000002'))
            : null);
        $this->verifier->expects(self::never())->method('verify');

        self::assertSame('offer_not_found', $this->service()->completeFromOAuthCallback($this->user, 'offer0000001', $this->session())->reason);
        self::assertSame('offer_not_found', $this->service()->completeFromOAuthCallback($this->user, 'manipulated1', $this->session())->reason);
    }

    public function testExpiredOfferIsRejected(): void
    {
        $offer = $this->offer();
        $offer->setExpiresAt(new \DateTime('-1 second'));
        $this->givenOffer($offer);
        $this->verifier->expects(self::never())->method('verify');

        self::assertSame(MiDataDepartmentOnboardingOutcomeStatus::EXPIRED, $this->service()->completeFromOAuthCallback($this->user, 'offer0000001', $this->session())->status);
    }

    public function testMiDataSessionOfAnotherPersonIsDenied(): void
    {
        $this->givenOffer($this->offer());
        $this->givenIdentity('1131');
        $this->verifier->expects(self::never())->method('verify');

        self::assertSame('identity_mismatch', $this->service()->completeFromOAuthCallback($this->user, 'offer0000001', $this->session('2222'))->reason);
    }

    public function testConflictsAreReported(): void
    {
        $this->givenOffer($this->offer());
        $this->givenIdentity('1131');
        $this->verifier->method('verify')->willReturn($this->confirmed('51'));
        $this->provisioner->method('provision')->willReturnOnConsecutiveCalls(
            self::throwException(new MiDataStructureConflictException('department_mapping_hierarchy', '51')),
            self::throwException(new UniqueConstraintViolationException($this->createMock(DriverException::class), null)),
        );
        $service = $this->service();

        self::assertSame('department_mapping_hierarchy', $service->completeFromOAuthCallback($this->user, 'offer0000001', $this->session())->reason);
        self::assertSame('concurrent_change', $service->completeFromOAuthCallback($this->user, 'offer0000001', $this->session())->reason);
    }

    public function testSearchNormalizationHandlesCaseAndAccents(): void
    {
        self::assertSame('zurich', MiDataDepartmentOnboardingService::normalizeForSearch('  ZÜRICH '));
        self::assertSame('geneve', MiDataDepartmentOnboardingService::normalizeForSearch('Genève'));
    }

    private function service(): MiDataDepartmentOnboardingService
    {
        return new MiDataDepartmentOnboardingService(
            $this->entityManager,
            $this->onboardings,
            $this->candidateRepository,
            $this->externalIdentities,
            $this->structureIdentities,
            $this->verifier,
            $this->provisioner,
            $this->supportQueue,
            $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * @return list<MiDataDepartmentOnboarding>
     */
    private function offers(): array
    {
        return array_values(array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof MiDataDepartmentOnboarding));
    }

    /**
     * @return list<string>
     */
    private function sortedOfferGroups(): array
    {
        $groups = array_map(static fn (MiDataDepartmentOnboarding $offer): string => $offer->getExternalDepartmentGroupId(), $this->offers());
        sort($groups);

        return $groups;
    }

    private function givenVerifierConfirmsEveryGroup(): void
    {
        $this->verifier->method('verify')->willReturnCallback(
            function (HitobitoOAuthSession $session, string $personId, string $groupId, ?\DateTimeImmutable $today = null, ?array $roles = null): MiDataMaterialwartVerification {
                self::assertNotNull($roles, 'preloaded roles are reused');
                $this->verifiedGroups[] = $groupId;

                return $this->confirmed($groupId);
            },
        );
    }

    private function givenOffer(MiDataDepartmentOnboarding $offer): void
    {
        $this->onboardings->method('find')->with('offer0000001')->willReturn($offer);
    }

    private function givenIdentity(string $externalUserId): void
    {
        $this->externalIdentities->method('findOneBy')
            ->with(['user' => $this->user, 'provider' => 'midata'])
            ->willReturn((new ExternalIdentity())->setUser($this->user)->setProvider('midata')->setExternalUserId($externalUserId));
    }

    private function givenMappedBund(): void
    {
        $this->provisioner->method('findMappedBundOrganisation')->willReturn((new Organisation())->setId('org000000001'));
    }

    private function candidate(
        string $id,
        string $name,
        ?User $user = null,
        string $groupId = '0',
        string $roleClass = MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS,
        ?\DateTime $expiresAt = null,
    ): MiDataMembershipCandidate {
        $candidate = (new MiDataMembershipCandidate())
            ->setId($id)
            ->setUser($user ?? $this->user)
            ->setExternalGroupId($groupId === '0' ? substr($id, -4) : $groupId)
            ->setExternalRoleClass($roleClass)
            ->setDisplayName($name)
            ->setExpiresAt($expiresAt ?? new \DateTime('+1 hour'));

        return $this->candidates[$id] = $candidate;
    }

    private function offer(string $id = 'offer0000001', string $groupId = '51', ?User $user = null): MiDataDepartmentOnboarding
    {
        return (new MiDataDepartmentOnboarding())
            ->setId($id)
            ->setUser($user ?? $this->user)
            ->setExternalPersonId('1131')
            ->setExternalDepartmentGroupId($groupId)
            ->setExternalRoleClass(MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS)
            ->setExpiresAt(new \DateTime('+1 hour'));
    }

    private function confirmed(string $groupId): MiDataMaterialwartVerification
    {
        return new MiDataMaterialwartVerification(
            MiDataMaterialwartVerificationStatus::CONFIRMED,
            '1131',
            new HitobitoGroup($groupId, '50', 'Group::Abteilung', 'Abteilung ' . $groupId, $groupId),
            [new HitobitoGroup('50', '49', 'Group::Region', 'Corps Musegg', '50')],
            new HitobitoGroup('49', '1', 'Group::Kantonalverband', 'Pfadi Luzern', '49'),
            new HitobitoGroup('1', '1113', 'Group::Bund', 'Pfadibewegung Schweiz', '1'),
            new HitobitoRole('1131', $groupId, MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS, null, null),
        );
    }

    /**
     * @param list<HitobitoRole> $roles
     */
    private function session(string $subject = '1131', array $roles = []): HitobitoOAuthSession
    {
        return new HitobitoOAuthSession('midata', 'access-token', new MiDataOAuthUserInfo($subject, null, false, null, null, $roles));
    }

    /**
     * @return list<HitobitoRole>
     */
    private function materialwartRoles(int $count): array
    {
        return array_map(
            fn (int $index): HitobitoRole => $this->userInfoRole((string) (100 + $index), null, sprintf('Abteilung %02d', $index)),
            range(1, $count),
        );
    }

    private function userInfoRole(
        string $groupId,
        ?string $roleClass = null,
        string $groupName = 'Pfadi Zytturm',
    ): HitobitoRole {
        $roleClass ??= MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS;

        return new HitobitoRole('1131', $groupId, $roleClass, null, null, groupName: $groupName, roleClass: $roleClass);
    }
}
