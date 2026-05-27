<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminJoinRequest;
use App\Entity\Department;
use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * E-Mail an MW/DC und Org-Admins bei offenen Join-/Support-Anfragen.
 */
final class JoinRequestManagerNotificationService
{
    private const MANAGER_ROLES = ['mw', 'dc'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private VerificationEmailService $verificationEmailService,
        private AdminCapabilityChecker $adminCapabilityChecker,
        private LoggerInterface $logger,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function notifyJoinRequestCreated(JoinRequest $joinRequest): void
    {
        $department = $joinRequest->getDepartment();
        if (!$department) {
            return;
        }

        $requesterProfile = $joinRequest->getUser()?->getProfile();
        $requesterName = $requesterProfile?->getDisplayName() ?? 'Unbekannt';
        $requesterEmail = $requesterProfile?->getEmail() ?? '';
        $reviewUrl = $this->buildDepartmentUsersSettingsUrl($department->getId());

        foreach ($this->collectDepartmentManagerRecipients($department) as $recipient) {
            try {
                $this->verificationEmailService->sendJoinRequestManagerNotification(
                    $recipient['email'],
                    $recipient['name'],
                    $requesterName,
                    $requesterEmail,
                    $department->getName(),
                    $department->getOrganisation()->getName(),
                    $joinRequest->getMessage(),
                    $reviewUrl,
                    $recipient['locale'] ?? null,
                );
            } catch (\Throwable $e) {
                $this->logger->warning('Join-Request-Benachrichtigung fehlgeschlagen', [
                    'recipient' => $recipient['email'],
                    'join_request_id' => $joinRequest->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function notifyAdminJoinRequestCreated(AdminJoinRequest $adminRequest): void
    {
        $requesterProfile = $adminRequest->getUser()?->getProfile();
        $requesterName = $requesterProfile?->getDisplayName() ?? 'Unbekannt';
        $requesterEmail = $requesterProfile?->getEmail() ?? '';

        $orgName = 'Unbekannt';
        $orgId = $adminRequest->getRequestedOrganisationId();
        if ($orgId !== null && $orgId !== '') {
            $org = $this->entityManager->getRepository(Organisation::class)->find($orgId);
            if ($org) {
                $orgName = $org->getName();
            }
        }

        $reviewUrl = rtrim($this->frontendUrl, '/') . '/admin-dashboard/verwaltung/support-requests';
        $parentDept = null;
        $parentDeptName = null;
        $parentDeptId = $adminRequest->getRequestedParentDepartmentId();
        if ($parentDeptId !== null && $parentDeptId !== '') {
            $parentDept = $this->entityManager->getRepository(Department::class)->find($parentDeptId);
            $parentDeptName = $parentDept?->getName();
        }

        $recipients = $this->collectSupportAdminRecipients($orgId);
        if ($parentDept instanceof Department) {
            foreach ($this->collectDepartmentManagerRecipients($parentDept) as $email => $recipient) {
                $recipients[$email] = $recipient;
            }
        }

        foreach ($recipients as $recipient) {
            try {
                $this->verificationEmailService->sendAdminJoinRequestManagerNotification(
                    $recipient['email'],
                    $recipient['name'],
                    $requesterName,
                    $requesterEmail,
                    $adminRequest->getRequestedDepartmentName(),
                    $orgName,
                    $parentDeptName,
                    $adminRequest->getMessage(),
                    $reviewUrl,
                    $recipient['locale'] ?? null,
                );
            } catch (\Throwable $e) {
                $this->logger->warning('Admin-Join-Request-Benachrichtigung fehlgeschlagen', [
                    'recipient' => $recipient['email'],
                    'admin_join_request_id' => $adminRequest->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, array{email: string, name: string, locale: ?string}>
     */
    private function collectDepartmentManagerRecipients(Department $department): array
    {
        $memberships = $this->entityManager->getRepository(Membership::class)->findBy([
            'departmentId' => $department->getId(),
        ]);

        $recipients = [];
        foreach ($memberships as $membership) {
            if (!\in_array($membership->getRole(), self::MANAGER_ROLES, true)) {
                continue;
            }
            $user = $membership->getUser();
            $profile = $user?->getProfile();
            if (!$profile || $profile->getEmail() === '') {
                continue;
            }
            $email = strtolower($profile->getEmail());
            $recipients[$email] = [
                'email' => $email,
                'name' => $profile->getDisplayName(),
                'locale' => $profile->getLanguage(),
            ];
        }

        return $recipients;
    }

    /**
     * @return array<string, array{email: string, name: string, locale: ?string}>
     */
    private function collectSupportAdminRecipients(?string $organisationId): array
    {
        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('p')
            ->where('u.state = :active')
            ->setParameter('active', 'active')
            ->getQuery()
            ->getResult();

        $recipients = [];
        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }
            if (!$this->adminCapabilityChecker->can($user, 'support_requests.assign')) {
                continue;
            }
            if (!$this->adminCapabilityChecker->canAccessOrganisation($user, $organisationId)) {
                continue;
            }
            $profile = $user->getProfile();
            if (!$profile || $profile->getEmail() === '') {
                continue;
            }
            $email = strtolower($profile->getEmail());
            $recipients[$email] = [
                'email' => $email,
                'name' => $profile->getDisplayName(),
                'locale' => $profile->getLanguage(),
            ];
        }

        return $recipients;
    }

    private function buildDepartmentUsersSettingsUrl(string $departmentId): string
    {
        return rtrim($this->frontendUrl, '/') . '/' . $departmentId . '/settings/users';
    }
}
