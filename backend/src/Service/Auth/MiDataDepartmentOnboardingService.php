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
 * Offers verified MiData Materialwarte to set up or join their Abteilung, also when they already have memberships.
 *
 * The OAuth callback stores scoped offers (verified) and, for the search mode, unverified candidates taken from
 * userinfo. Setting up an Abteilung always happens in a later OAuth callback with a fresh access token, so role
 * and structure are verified again. /api/roles is loaded at most once per callback.
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

        // Userinfo only nominates candidates; the verifier decides with the JSON:API.
        $personId = $session->userInfo->subject;
        $candidateNames = [];
        foreach ($session->userInfo->roles as $role) {
            if (
                $role->personId === $personId
                && MiDataMaterialwartVerifier::isMaterialwartRole($role)
                && !isset($candidateNames[$role->groupId])
                && !$this->isMemberOfMappedDepartment($user, $role->groupId)
            ) {
                $candidateNames[$role->groupId] = (string) $role->groupName;
            }
        }
        $this->refreshCandidates($user, $candidateNames);

        if (count($candidateNames) > self::DIRECT_OFFER_LIMIT) {
            // Search mode: only the candidate the user selects is verified, in its own MiData login.
            $this->entityManager->flush();

            return 0;
        }

        $offered = 0;
        $roles = null;
        if ($candidateNames !== []) {
            try {
                $roles = $this->verifier->loadRoles($session, $personId);
            } catch (HitobitoApiException) {
                $this->logOfferSkipped($user, '*', 'roles_unavailable');
            }
        }
        foreach ($roles === null ? [] : array_keys($candidateNames) as $groupId) {
            $verification = $this->verifier->verify($session, $personId, (string) $groupId, null, $roles);
            $blocker = $this->verifiedOfferBlocker($verification);
            if ($blocker !== null) {
                $this->logOfferSkipped($user, (string) $groupId, $blocker);
                continue;
            }

            $this->upsertOffer($user, $verification);
            $offered++;
        }
        $this->entityManager->flush();

        return $offered;
    }

    /**
     * Verified offers the user may still act on; departments the user already belongs to are left out.
     *
     * @return list<MiDataDepartmentOnboarding>
     */
    public function listOpenOffers(User $user): array
    {
        return array_values(array_filter(
            $this->onboardings->findOpenForUser($user, new \DateTime()),
            fn (MiDataDepartmentOnboarding $offer): bool => !$this->isMemberOfMappedDepartment(
                $user,
                $offer->getExternalDepartmentGroupId(),
            ),
        ));
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
        usort($hits, static fn (MiDataMembershipCandidate $a, MiDataMembershipCandidate $b): int => strcmp(
            self::normalizeForSearch($a->getDisplayName()),
            self::normalizeForSearch($b->getDisplayName()),
        ));

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
            || $candidate->getExternalRoleClass() !== MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS
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

        $verification = $this->verifier->verify($session, $personId, $candidate->getExternalGroupId());
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
            $this->logger->warning('MiData Materialwart onboarding stopped by a structure conflict', [
                'user_id' => $user->getId(),
                'reason' => $exception->reason,
                'external_group_id' => $exception->externalGroupId,
            ]);

            return new MiDataDepartmentOnboardingOutcome(
                MiDataDepartmentOnboardingOutcomeStatus::CONFLICT,
                reason: $exception->reason,
            );
        } catch (UniqueConstraintViolationException) {
            $this->logger->warning('MiData Materialwart onboarding lost a concurrent structure change', [
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

        $this->logger->info('MiData Materialwart onboarding completed', [
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
     * @param array<string, string> $displayNamesByGroupId
     */
    private function refreshCandidates(User $user, array $displayNamesByGroupId): void
    {
        $existingByGroupId = [];
        foreach ($this->candidates->findAllForUser($user) as $candidate) {
            if (!array_key_exists($candidate->getExternalGroupId(), $displayNamesByGroupId)) {
                $this->entityManager->remove($candidate);
                continue;
            }
            $existingByGroupId[$candidate->getExternalGroupId()] = $candidate;
        }

        $expiresAt = new \DateTime('+' . self::OFFER_TTL_SECONDS . ' seconds');
        foreach ($displayNamesByGroupId as $groupId => $displayName) {
            $candidate = $existingByGroupId[(string) $groupId] ?? null;
            if (!$candidate instanceof MiDataMembershipCandidate) {
                $candidate = new MiDataMembershipCandidate();
                $candidate->setId(IdGenerator::generateUnique($this->entityManager, MiDataMembershipCandidate::class));
                $candidate->setUser($user);
                $candidate->setProvider('midata');
                $candidate->setExternalGroupId((string) $groupId);
                $this->entityManager->persist($candidate);
            }
            $candidate->setExternalRoleClass(MiDataMaterialwartVerifier::MATERIALWART_ROLE_CLASS);
            $candidate->setDisplayName(mb_substr(trim($displayName), 0, 255));
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
        $this->logger->info('MiData Materialwart onboarding offer skipped', [
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
