<?php

declare(strict_types=1);

namespace App\Service\Issue;

use App\Entity\Activity;
use App\Entity\ActivityIssueReport;
use App\Entity\Membership;
use App\Entity\SupplierCompany;
use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Repository\SupplierMembershipRepository;
use App\Service\ActivityAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Zugriff auf Schadenmeldungs-Fotos: Activity-Zugriff, Reporter, MW oder zugewiesener Lieferant.
 */
class IssuePhotoAccessService
{
    /** @var list<string> */
    private const MANAGER_ROLES = ['mw', 'dc', 'matwart', 'depchef', 'sa', 'org', 'sub'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private SupplierMembershipRepository $supplierMembershipRepository,
    ) {
    }

    public function assertCanViewPhoto(User $user, Activity $activity, ActivityIssueReport $report): void
    {
        if ($this->canViewPhoto($user, $activity, $report)) {
            return;
        }

        throw new AccessDeniedHttpException('Kein Zugriff auf diese Fotos');
    }

    public function assertCanUploadPhoto(User $user, Activity $activity, ActivityIssueReport $report): void
    {
        if ($this->canUploadPhoto($user, $activity, $report)) {
            return;
        }

        throw new AccessDeniedHttpException('Kein Zugriff zum Hochladen von Fotos');
    }

    public function canViewPhoto(User $user, Activity $activity, ActivityIssueReport $report): bool
    {
        if ($this->activityAccess->canUserViewActivity($user, $activity)) {
            return true;
        }

        return $this->isAssignedSupplierForIssueReport($user, $report);
    }

    public function canUploadPhoto(User $user, Activity $activity, ActivityIssueReport $report): bool
    {
        if (!$activity->canReportIssues()) {
            return false;
        }

        if (!$this->activityAccess->canUserViewActivity($user, $activity)) {
            return false;
        }

        if ((string) $report->getReportedByUserId() === (string) $user->getId()) {
            return true;
        }

        return $this->isDepartmentManagerForActivity($user, $activity);
    }

    public function requireIssueReport(string $activityId, string $issueId): ActivityIssueReport
    {
        $report = $this->entityManager->getRepository(ActivityIssueReport::class)->find($issueId);
        if (!$report instanceof ActivityIssueReport || $report->getActivityId() !== $activityId) {
            throw new \InvalidArgumentException('Meldung nicht gefunden');
        }

        return $report;
    }

    private function isDepartmentManagerForActivity(User $user, Activity $activity): bool
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $activity->getDepartmentId()]);
        if (!$membership instanceof Membership) {
            return false;
        }

        $role = strtolower(trim((string) ($membership->getRole() ?? '')));

        return \in_array($role, self::MANAGER_ROLES, true);
    }

    private function isAssignedSupplierForIssueReport(User $user, ActivityIssueReport $report): bool
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)
            ->findOneBy(['issueReportId' => $report->getId()]);
        if (!$ticket instanceof WorkshopTicket) {
            return false;
        }

        $companyId = $ticket->getAssignedToSupplierCompanyId();
        if ($companyId === null || $companyId === '') {
            return false;
        }

        $supplierMembership = $this->supplierMembershipRepository->findOneBy([
            'userId' => $user->getId(),
            'supplierCompanyId' => $companyId,
        ]);
        if (!$supplierMembership) {
            return false;
        }

        $company = $supplierMembership->getSupplierCompany();

        return $company->getStatus() === SupplierCompany::STATUS_ACTIVE
            && \in_array(SupplierCompany::CAPABILITY_REPAIRS, $company->getCapabilities(), true);
    }
}
