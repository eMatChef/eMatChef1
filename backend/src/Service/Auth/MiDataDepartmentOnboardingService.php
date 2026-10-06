<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\MiDataDepartmentOnboarding;
use App\Entity\MiDataMembershipCandidate;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Repository\ExternalStructureIdentityRepository;
use App\Repository\MiDataDepartmentOnboardingRepository;
use App\Repository\MiDataMembershipCandidateRepository;
use App\Service\Support\UnassignedUserSupportQueue;
use App\Util\IdGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Offers verified MiData Abteilungen (Materialwart → mw, Abteilungsleitung → dc) to set up or join, also when the
 * user already has memberships.
 *
 * The OAuth callback stores scoped offers (verified) and, for the search mode, unverified candidates taken from
 * userinfo, one per Abteilung with its best supported role. Setting up an Abteilung always happens in a later OAuth
 * callback with a fresh access token, so role and structure are verified again. /api/roles is loaded at most once
 * per callback.
 */
final class MiDataDepartmentOnboardingService
{
    public const OFFER_TTL_SECONDS = 86400;
    /** Up to this many relevant Abteilungen are verified at login; more switch to the search mode. */
    public const DIRECT_OFFER_LIMIT = 10;
    public const SEARCH_RESULT_LIMIT = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MiDataDepartmentOnboardingRepository $onboardings,
        private readonly MiDataMembershipCandidateRepository $candidates,
        private readonly ExternalIdentityRepository $externalIdentities,
        private readonly ExternalStructureIdentityRepository $structureIdentities,
        private readonly MiDataMaterialwartVerifier $verifier,
        private readonly MiDataDepartmentStructureProvisioner $provisioner,
        private readonly UnassignedUserSupportQueue $supportQueue,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return int number of verified offers created or refreshed
     */
    public function offerFromOAuthCallback(User $user, HitobitoOAuthSession $session): int
    {
        if ($session->provider !== 'midata' || $session->userInfo->subject === '') {
            return 0;
        }

        // Userinfo only nominates candidates (one per Abteilung, best supported role); the verifier decides with the JSON:API.
        $personId = $session->userInfo->subject;
        $candidates = [];
        foreach ($session->userInfo->roles as $role) {
            if ($role->personId !== $personId || !MiDataSupportedRoleCatalog::isSupported($role->type)) {
                continue;
            }
            $current = $candidates[$role->groupId] ?? null;
            if ($current === null && $this->isMemberOfMappedDepartment($user, $role->groupId)) {
                continue;
            }
            if ($current === null || MiDataSupportedRoleCatalog::outranks($role->type, $current['role_class'])) {
                $candidates[$role->groupId] = ['role_class' => $role->type, 'name' => (string) $role->groupName];
            }
        }
        $this->refreshCandidates($user, $candidates);

        // Up to the limit every Abteilung is verified. Beyond it the search mode applies; only a manageable number
        // of top-priority (mw) Abteilungen is still verified directly, never the long tail of dc roles.
        $direct = array_keys($candidates);
        if (count($candidates) > self::DIRECT_OFFER_LIMIT) {
            $direct = array_keys(array_filter(
                $candidates,
                static fn (array $candidate): bool => MiDataSupportedRoleCatalog::membershipRole($candidate['role_class']) === 'mw',
            ));
            if (count($direct) > self::DIRECT_OFFER_LIMIT) {
                $direct = [];
            }
        }

        $offered = 0;
        $verifiedOffers = [];
        $roles = null;
        if ($direct !== []) {
            try {
                $roles = $this->verifier->loadRoles($session, $personId);
            } catch (HitobitoApiException) {
                $this->logOfferSkipped($user, '*', 'roles_unavailable');
            }
        }
        foreach ($roles === null ? [] : $direct as $groupId) {
            $verification = $this->verifier->verify($session, $personId, (string) $groupId, null, $roles);
            $blocker = $this->verifiedOfferBlocker($verification);
            if ($blocker !== null) {
                $this->logOfferSkipped($user, (string) $groupId, $blocker);
                continue;
            }

            $verifiedOffers[] = [$this->upsertOffer($user, $verification), $verification];
            $offered++;
        }
        $this->entityManager->flush();
        $this->autoAssignUnassignedUser($user, $session, $verifiedOffers);

        return $offered;
    }

    /**
     * A user without any membership is assigned directly when exactly one verified offer has the best role priority
     * (one mw, or a single dc when no mw exists). Several equal offers stay a manual choice; the search mode and the
     * long tail of dc roles never reach this point because they are not verified at login.
     *
     * @param list<array{0: MiDataDepartmentOnboarding, 1: MiDataMaterialwartVerification}> $verifiedOffers
     */
    private function autoAssignUnassignedUser(User $user, HitobitoOAuthSession $session, array $verifiedOffers): void
    {
        if (
            $verifiedOffers === []
            || !$this->identityMatchesSession($user, $session, $session->userInfo->subject)
            || $this->entityManager->getRepository(Membership::class)->count(['userId' => $user->getId()]) > 0
        ) {
            return;
        }

        $best = min(array_map(
            static fn (array $offer): int => MiDataSupportedRoleCatalog::priority($offer[1]->role->type),
            $verifiedOffers,
        ));
        $top = array_values(array_filter(
            $verifiedOffers,
            static fn (array $offer): bool => MiDataSupportedRoleCatalog::priority($offer[1]->role->type) === $best,
        ));
        if (count($top) !== 1) {
            $this->logOfferSkipped($user, '*', 'auto_assign_ambiguous');

            return;
        }

        [$onboarding, $verification] = $top[0];
        $outcome = $this->provisionVerifiedOffer($user, $onboarding, $verification);
        $this->logger->info('MiData auto-assignment finished', [
            'user_id' => $user->getId(),
            'status' => $outcome->status->value,
            'reason' => $outcome->reason,
        ]);
    }

    /**
     * Verified offers the user may still act on, mw before dc and then by name; departments the user already
     * belongs to are left out.
     *
     * @return list<MiDataDepartmentOnboarding>
     */
    public function listOpenOffers(User $user): array
    {
        $offers = array_values(array_filter(
            $this->onboardings->findOpenForUser($user, new \DateTime()),
            fn (MiDataDepartmentOnboarding $offer): bool => !$this->isMemberOfMappedDepartment(
                $user,
                $offer->getExternalDepartmentGroupId(),
            ),
        ));
        usort($offers, static fn (MiDataDepartmentOnboarding $a, MiDataDepartmentOnboarding $b): int => [
            MiDataSupportedRoleCatalog::priority($a->getExternalRoleClass()),
            self::normalizeForSearch($a->getDepartmentName()),
        ] <=> [
            MiDataSupportedRoleCatalog::priority($b->getExternalRoleClass()),
            self::normalizeForSearch($b->getDepartmentName()),
        ]);

        return $offers;
    }

    public function isSearchRequired(User $user): bool
    {
        return count($this->listOpenCandidates($user)) > self::DIRECT_OFFER_LIMIT;
    }

    /**
     * Searches only the user's own unverified candidates. A hit authorizes nothing.
     *
     * @return list<MiDataMembershipCandidate>
     */
    public function searchCandidates(User $user, string $query): array
    {
        $needle = self::normalizeForSearch($query);
        if ($needle === '') {
            return [];
        }

        $hits = array_values(array_filter(
            $this->listOpenCandidates($user),
            static fn (MiDataMembershipCandidate $candidate): bool => str_contains(
                self::normalizeForSearch($candidate->getDisplayName()),
                $needle,
            ),
        ));
        usort($hits, static fn (MiDataMembershipCandidate $a, MiDataMembershipCandidate $b): int => [
            MiDataSupportedRoleCatalog::priority($a->getExternalRoleClass()),
            self::normalizeForSearch($a->getDisplayName()),
        ] <=> [
            MiDataSupportedRoleCatalog::priority($b->getExternalRoleClass()),
            self::normalizeForSearch($b->getDisplayName()),
        ]);

        return array_slice($hits, 0, self::SEARCH_RESULT_LIMIT);
    }

    public function completeFromOAuthCallback(
        User $user,
        string $onboardingId,
        HitobitoOAuthSession $session,
    ): MiDataDepartmentOnboardingOutcome {
        $onboarding = $onboardingId !== '' ? $this->onboardings->find($onboardingId) : null;
        if (
            !$onboarding instanceof MiDataDepartmentOnboarding
            || $onboarding->getUserId() !== $user->getId()
            || $onboarding->getProvider() !== 'midata'
        ) {
            return $this->denied('offer_not_found');
        }
        if ($onboarding->isExpired(new \DateTime())) {
            return new MiDataDepartmentOnboardingOutcome(MiDataDepartmentOnboardingOutcomeStatus::EXPIRED);
        }
        if (!$this->identityMatchesSession($user, $session, $onboarding->getExternalPersonId())) {
            return $this->denied('identity_mismatch');
        }

        $verification = $this->verifier->verify(
            $session,
            $onboarding->getExternalPersonId(),
            $onboarding->getExternalDepartmentGroupId(),
        );
        $failure = $this->verificationFailure($verification);
        if ($failure !== null) {
            return $failure;
        }

        return $this->provisionVerifiedOffer($user, $onboarding, $verification);
    }

    /**
     * Search-mode selection: the candidate is only a reference; it becomes an offer after full verification
     * with the fresh token of this callback and is then set up directly.
     */
    public function completeCandidateFromOAuthCallback(
        User $user,
        string $candidateId,
        HitobitoOAuthSession $session,
    ): MiDataDepartmentOnboardingOutcome {
        $candidate = $candidateId !== '' ? $this->candidates->find($candidateId) : null;
        if (
            !$candidate instanceof MiDataMembershipCandidate
            || $candidate->getUserId() !== $user->getId()
            || $candidate->getProvider() !== 'midata'
            || !MiDataSupportedRoleCatalog::isSupported($candidate->getExternalRoleClass())
        ) {
            return $this->denied('candidate_not_found');
        }
        if ($candidate->isExpired(new \DateTime())) {
            return new MiDataDepartmentOnboardingOutcome(MiDataDepartmentOnboardingOutcomeStatus::EXPIRED);
        }
        $personId = $session->userInfo->subject;
        if (!$this->identityMatchesSession($user, $session, $personId)) {
            return $this->denied('identity_mismatch');
        }

        // The selected role must still be active; the verified (possibly higher) role then decides the membership.
        $verification = $this->verifier->verify(
            $session,
            $personId,
            $candidate->getExternalGroupId(),
            null,
            null,
            $candidate->getExternalRoleClass(),
        );
        $failure = $this->verificationFailure($verification);
        if ($failure !== null) {
            return $failure;
        }
        $blocker = $this->verifiedOfferBlocker($verification);
        if ($blocker !== null) {
            return new MiDataDepartmentOnboardingOutcome(MiDataDepartmentOnboardingOutcomeStatus::CONFLICT, reason: $blocker);
        }

        $onboarding = $this->upsertOffer($user, $verification);
        $this->entityManager->flush();

        return $this->provisionVerifiedOffer($user, $onboarding, $verification);
    }

    private function provisionVerifiedOffer(
        User $user,
        MiDataDepartmentOnboarding $onboarding,
        MiDataMaterialwartVerification $verification,
    ): MiDataDepartmentOnboardingOutcome {
        try {
            $result = $this->provisioner->provision($user, $verification, $onboarding);
        } catch (MiDataStructureConflictException $exception) {
            $this->logger->warning('MiData department onboarding stopped by a structure conflict', [
                'user_id' => $user->getId(),
                'reason' => $exception->reason,
                'external_group_id' => $exception->externalGroupId,
            ]);

            return new MiDataDepartmentOnboardingOutcome(
                MiDataDepartmentOnboardingOutcomeStatus::CONFLICT,
                reason: $exception->reason,
            );
        } catch (UniqueConstraintViolationException) {
            $this->logger->warning('MiData department onboarding lost a concurrent structure change', [
                'user_id' => $user->getId(),
                'external_group_id' => $onboarding->getExternalDepartmentGroupId(),
            ]);

            return new MiDataDepartmentOnboardingOutcome(
                MiDataDepartmentOnboardingOutcomeStatus::CONFLICT,
                reason: 'concurrent_change',
            );
        }

        $status = match (true) {
            !$result->membershipCreated => MiDataDepartmentOnboardingOutcomeStatus::ALREADY_MEMBER,
            $result->departmentCreated => MiDataDepartmentOnboardingOutcomeStatus::CREATED,
            default => MiDataDepartmentOnboardingOutcomeStatus::JOINED,
        };
        // A legacy automatic "Unbekannte Abteilung" request is answered by the verified department.
        try {
            $this->supportQueue->resolveForAssignedUser($user, $result->department, 'midata_onboarding');
        } catch (\Throwable $exception) {
            $this->logger->warning('MiData onboarding could not close the automatic support request', [
                'user_id' => $user->getId(),
                'exception' => $exception,
            ]);
        }

        $this->logger->info('MiData department onboarding completed', [
            'user_id' => $user->getId(),
            'department_id' => $result->department->getId(),
            'status' => $status->value,
            'created_external_group_ids' => $result->createdExternalGroupIds,
        ]);

        return new MiDataDepartmentOnboardingOutcome($status, $result->department);
    }

    private function verificationFailure(MiDataMaterialwartVerification $verification): ?MiDataDepartmentOnboardingOutcome
    {
        if ($verification->status === MiDataMaterialwartVerificationStatus::UNAVAILABLE) {
            return new MiDataDepartmentOnboardingOutcome(MiDataDepartmentOnboardingOutcomeStatus::UNAVAILABLE);
        }
        if ($verification->status === MiDataMaterialwartVerificationStatus::UNSUPPORTED_STRUCTURE) {
            return new MiDataDepartmentOnboardingOutcome(MiDataDepartmentOnboardingOutcomeStatus::UNSUPPORTED_STRUCTURE);
        }
        if (!$verification->isConfirmed()) {
            return $this->denied('role_not_confirmed');
        }

        return null;
    }

    /**
     * Why a verification must not become an offer: not confirmed or Bund not administratively mapped.
     */
    private function verifiedOfferBlocker(MiDataMaterialwartVerification $verification): ?string
    {
        if (!$verification->isConfirmed()) {
            return $verification->status->value;
        }
        try {
            $this->provisioner->findMappedBundOrganisation($verification->bund);
        } catch (MiDataStructureConflictException $exception) {
            return $exception->reason;
        }

        return null;
    }

    private function identityMatchesSession(User $user, HitobitoOAuthSession $session, string $expectedPersonId): bool
    {
        $identity = $this->externalIdentities->findOneBy(['user' => $user, 'provider' => 'midata']);

        return $identity !== null
            && $expectedPersonId !== ''
            && hash_equals($identity->getExternalUserId(), $expectedPersonId)
            && hash_equals($expectedPersonId, $session->userInfo->subject);
    }

    private function isMemberOfMappedDepartment(User $user, string $externalGroupId): bool
    {
        $mapping = $this->structureIdentities->findOneByProviderAndExternalGroupId('midata', $externalGroupId);
        $department = $mapping?->getDepartment();
        if (!$department instanceof Department) {
            return false;
        }

        return $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]) instanceof Membership;
    }

    /**
     * Replaces the user's candidates with the current userinfo nomination; other users are never touched.
     *
     * @param array<string, array{role_class: string, name: string}> $candidatesByGroupId
     */
    private function refreshCandidates(User $user, array $candidatesByGroupId): void
    {
        $existingByGroupId = [];
        foreach ($this->candidates->findAllForUser($user) as $candidate) {
            if (!array_key_exists($candidate->getExternalGroupId(), $candidatesByGroupId)) {
                $this->entityManager->remove($candidate);
                continue;
            }
            $existingByGroupId[$candidate->getExternalGroupId()] = $candidate;
        }

        $expiresAt = new \DateTime('+' . self::OFFER_TTL_SECONDS . ' seconds');
        foreach ($candidatesByGroupId as $groupId => $nominated) {
            $candidate = $existingByGroupId[(string) $groupId] ?? null;
            if (!$candidate instanceof MiDataMembershipCandidate) {
                $candidate = new MiDataMembershipCandidate();
                $candidate->setId(IdGenerator::generateUnique($this->entityManager, MiDataMembershipCandidate::class));
                $candidate->setUser($user);
                $candidate->setProvider('midata');
                $candidate->setExternalGroupId((string) $groupId);
                $this->entityManager->persist($candidate);
            }
            $candidate->setExternalRoleClass($nominated['role_class']);
            $candidate->setDisplayName(mb_substr(trim($nominated['name']), 0, 255));
            $candidate->setExpiresAt($expiresAt);
        }
    }

    /**
     * @return list<MiDataMembershipCandidate>
     */
    private function listOpenCandidates(User $user): array
    {
        return array_values(array_filter(
            $this->candidates->findOpenForUser($user, new \DateTime()),
            fn (MiDataMembershipCandidate $candidate): bool => !$this->isMemberOfMappedDepartment(
                $user,
                $candidate->getExternalGroupId(),
            ),
        ));
    }

    private function upsertOffer(User $user, MiDataMaterialwartVerification $verification): MiDataDepartmentOnboarding
    {
        $department = $verification->department;
        $onboarding = $this->onboardings->findOneForUserAndExternalGroup($user, $department->id);
        if (!$onboarding instanceof MiDataDepartmentOnboarding) {
            $onboarding = new MiDataDepartmentOnboarding();
            $onboarding->setId(IdGenerator::generateUnique($this->entityManager, MiDataDepartmentOnboarding::class));
            $onboarding->setUser($user);
            $onboarding->setProvider('midata');
            $onboarding->setExternalDepartmentGroupId($department->id);
            $this->entityManager->persist($onboarding);
        }

        $onboarding->setExternalPersonId((string) $verification->externalPersonId);
        $onboarding->setExternalRoleClass($verification->role->type);
        $onboarding->setDepartmentName(mb_substr($department->name, 0, 255));
        $regionNames = array_map(static fn (HitobitoGroup $region): string => $region->name, $verification->regions);
        $onboarding->setRegionName($regionNames !== [] ? mb_substr(implode(' › ', $regionNames), 0, 255) : null);
        $onboarding->setKantonalverbandName(mb_substr($verification->kantonalverband->name, 0, 255));
        $onboarding->setExpiresAt(new \DateTime('+' . self::OFFER_TTL_SECONDS . ' seconds'));
        $onboarding->setCompletedAt(null);

        return $onboarding;
    }

    /**
     * Case-insensitive, accent-tolerant form for searching display names ("Zurich" finds "Zürich").
     */
    public static function normalizeForSearch(string $value): string
    {
        return strtr(mb_strtolower(trim($value)), [
            'ä' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'ö' => 'o', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ü' => 'u', 'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
        ]);
    }

    private function logOfferSkipped(User $user, string $externalGroupId, string $reason): void
    {
        $this->logger->info('MiData department onboarding offer skipped', [
            'user_id' => $user->getId(),
            'external_group_id' => $externalGroupId,
            'reason' => $reason,
        ]);
    }

    private function denied(string $reason): MiDataDepartmentOnboardingOutcome
    {
        return new MiDataDepartmentOnboardingOutcome(MiDataDepartmentOnboardingOutcomeStatus::DENIED, reason: $reason);
    }
}
