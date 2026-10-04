<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\AuditLogger;
use App\Service\JoinRequestNotifier;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class DepartmentJoinFlowService
{
    private const INVITE_CODE_SETTING_KEY = 'join.invite_code';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MiDataDepartmentMembershipVerifier $membershipVerifier,
        private readonly AuditLogger $auditLogger,
        private readonly JoinRequestNotifier $notifications,
        private readonly LoggerInterface $logger,
    ) {}

    public function submitJoinCode(
        User $user,
        string $joinCode,
        ?HitobitoOAuthSession $session = null,
        ?string $message = null,
    ): DepartmentJoinOutcome {
        $department = $this->resolveDepartment($joinCode);
        if (!$department instanceof Department) {
            return new DepartmentJoinOutcome(DepartmentJoinOutcomeStatus::DEPARTMENT_NOT_FOUND);
        }

        return $this->submitForDepartment($user, $department, $session, $message);
    }

    public function verifyJoinCodeForOAuthCallback(
        User $user,
        string $joinCode,
        HitobitoOAuthSession $session,
    ): DepartmentJoinOutcome {
        $department = $this->resolveDepartment($joinCode);
        if (!$department instanceof Department) {
            return new DepartmentJoinOutcome(DepartmentJoinOutcomeStatus::DEPARTMENT_NOT_FOUND);
        }

        return $this->submitToDepartment($user, $department, $session, null, false);
    }

    public function submitForDepartment(
        User $user,
        Department $department,
        ?HitobitoOAuthSession $session = null,
        ?string $message = null,
    ): DepartmentJoinOutcome {
        return $this->submitToDepartment($user, $department, $session, $message, true);
    }

    public function createManualRequest(
        User $user,
        Department $department,
        ?string $message = null,
    ): DepartmentJoinOutcome {
        if ($this->findMembership($user, $department) !== null) {
            return new DepartmentJoinOutcome(DepartmentJoinOutcomeStatus::ALREADY_MEMBER, $department);
        }

        $pendingRequest = $this->findPendingRequest($user, $department);
        if ($pendingRequest !== null) {
            return new DepartmentJoinOutcome(
                DepartmentJoinOutcomeStatus::REQUEST_ALREADY_PENDING,
                $department,
                $pendingRequest,
            );
        }

        $request = $this->newJoinRequest($user, $department, $message, 'pending');
        $this->entityManager->persist($request);
        $this->entityManager->flush();
        $this->notifyRequestCreated($request);

        return new DepartmentJoinOutcome(DepartmentJoinOutcomeStatus::REQUEST_CREATED, $department, $request);
    }

    private function submitToDepartment(
        User $user,
        Department $department,
        ?HitobitoOAuthSession $session,
        ?string $message,
        bool $createManualRequest,
    ): DepartmentJoinOutcome {
        $existingMembership = $this->findMembership($user, $department);
        if ($existingMembership !== null && $session === null) {
            return new DepartmentJoinOutcome(DepartmentJoinOutcomeStatus::ALREADY_MEMBER, $department);
        }

        $verification = $this->membershipVerifier->verify($user, $department, $session);
        if ($verification->status === MiDataDepartmentVerificationStatus::UNAVAILABLE) {
            return new DepartmentJoinOutcome(DepartmentJoinOutcomeStatus::VERIFICATION_UNAVAILABLE, $department);
        }
        if ($verification->status !== MiDataDepartmentVerificationStatus::CONFIRMED) {
            if ($existingMembership !== null) {
                return new DepartmentJoinOutcome(DepartmentJoinOutcomeStatus::ALREADY_MEMBER, $department);
            }

            if (!$createManualRequest) {
                $pendingRequest = $this->findPendingRequest($user, $department);
                if ($pendingRequest !== null) {
                    return new DepartmentJoinOutcome(
                        DepartmentJoinOutcomeStatus::REQUEST_ALREADY_PENDING,
                        $department,
                        $pendingRequest,
                    );
                }

                return new DepartmentJoinOutcome(DepartmentJoinOutcomeStatus::MANUAL_REQUEST_REQUIRED, $department);
            }

            return $this->createManualRequest($user, $department, $message);
        }

        $membership = $existingMembership;
        if ($membership === null) {
            $membership = new Membership();
            $membership->setUser($user);
            $membership->setDepartment($department);
            $membership->setRole('u');
            $membership->setIsPrimary($this->entityManager->getRepository(Membership::class)->count(['userId' => $user->getId()]) === 0);
            $this->auditLogger->log(
                'membership',
                AuditLogger::buildMembershipEntityId($user->getId(), $department->getId()),
                'membership_created',
                $user,
                $user,
                $department,
                [
                    'role' => ['old' => null, 'new' => 'u'],
                    'is_primary' => ['old' => null, 'new' => $membership->getIsPrimary()],
                ],
            );
            $this->entityManager->persist($membership);
        }

        $request = $this->findPendingRequest($user, $department);
        if ($request !== null) {
            $request->setStatus('approved');
            $request->setReviewedBy(null);
        } else {
            $request = $this->entityManager->getRepository(JoinRequest::class)->findOneBy([
                'userId' => $user->getId(),
                'departmentId' => $department->getId(),
                'status' => 'approved',
            ]);
            if (!$request instanceof JoinRequest) {
                $request = $this->newJoinRequest($user, $department, $message, 'approved');
                $this->entityManager->persist($request);
            }
        }
        $this->entityManager->flush();

        return new DepartmentJoinOutcome(
            $existingMembership === null
                ? DepartmentJoinOutcomeStatus::MEMBERSHIP_CONFIRMED
                : DepartmentJoinOutcomeStatus::ALREADY_MEMBER,
            $department,
            $request,
        );
    }

    private function resolveDepartment(string $joinCode): ?Department
    {
        $joinCode = trim($joinCode);
        if ($joinCode === '') {
            return null;
        }

        $department = $this->entityManager->getRepository(Department::class)->find($joinCode);
        if ($department instanceof Department) {
            return $department;
        }

        $normalizedCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $joinCode) ?? '');
        if ($normalizedCode === '') {
            return null;
        }
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'settingKey' => self::INVITE_CODE_SETTING_KEY,
            'settingValue' => $normalizedCode,
        ]);

        return $setting instanceof DepartmentSetting ? $setting->getDepartment() : null;
    }

    private function findMembership(User $user, Department $department): ?Membership
    {
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);

        return $membership instanceof Membership ? $membership : null;
    }

    private function findPendingRequest(User $user, Department $department): ?JoinRequest
    {
        $request = $this->entityManager->getRepository(JoinRequest::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
            'status' => 'pending',
        ]);

        return $request instanceof JoinRequest ? $request : null;
    }

    private function newJoinRequest(User $user, Department $department, ?string $message, string $status): JoinRequest
    {
        $request = new JoinRequest();
        $request->setId(IdGenerator::generateUnique($this->entityManager, JoinRequest::class));
        $request->setUser($user);
        $request->setDepartment($department);
        $request->setMessage($message !== null && trim($message) !== '' ? trim($message) : null);
        $request->setStatus($status);

        return $request;
    }

    private function notifyRequestCreated(JoinRequest $request): void
    {
        try {
            $this->notifications->notifyJoinRequestCreated($request);
        } catch (\Throwable $exception) {
            $this->logger->warning('Join-Request-Benachrichtigung fehlgeschlagen', [
                'join_request_id' => $request->getId(),
                'exception' => $exception,
            ]);
        }
    }
}
