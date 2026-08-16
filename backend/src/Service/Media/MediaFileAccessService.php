<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\ActivityIssueReport;
use App\Entity\ActivityJsOrder;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Issue\IssuePhotoAccessService;
use App\Service\Material\MaterialPhotoAccessService;
use App\Service\Workshop\WorkshopPhotoAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MediaFileAccessService
{
    /** @var list<string> */
    private const ACCOUNTING_ROLES = ['mw', 'dc', 'matwart', 'depchef'];

    /** @var list<string> */
    private const BASIC_ROLES = ['u', 'user', 'l1', 'l2', 'l3'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MaterialPhotoAccessService $materialPhotoAccess,
        private WorkshopPhotoAccessService $workshopPhotoAccess,
        private IssuePhotoAccessService $issuePhotoAccess,
        private ActivityAccessService $activityAccess,
        private GrossanlassAccessService $grossanlassAccess,
    ) {
    }

    public function assertCanView(User $user, string $context, string $departmentId, string $contextId): void
    {
        match ($context) {
            MediaStorageService::CONTEXT_MATERIAL_ITEM => $this->assertMaterial($user, $departmentId, $contextId),
            MediaStorageService::CONTEXT_WORKSHOP_TICKET => $this->assertWorkshop($user, $departmentId, $contextId),
            MediaStorageService::CONTEXT_ISSUE_REPORT => $this->assertIssue($user, $departmentId, $contextId),
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING => $this->assertAccountingBooking($user, $departmentId, $contextId),
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP => $this->assertAccountingFollowUp($user, $departmentId, $contextId),
            MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER => $this->assertJsOrder($user, $departmentId, $contextId),
            MediaStorageService::CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE => $this->assertGrossanlassQuote($user, $departmentId, $contextId),
            default => throw new \InvalidArgumentException('Ungültiger Medien-Kontext'),
        };
    }

    private function assertMaterial(User $user, string $departmentId, string $materialId): void
    {
        $material = $this->materialPhotoAccess->requireMaterialById($materialId);
        if ($material->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $this->materialPhotoAccess->assertCanViewPhoto($user, $material);
    }

    private function assertWorkshop(User $user, string $departmentId, string $ticketId): void
    {
        $ticket = $this->workshopPhotoAccess->requireTicketById($ticketId);
        if ($ticket->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $this->workshopPhotoAccess->assertCanViewTicketPhotos($user, $ticket);
    }

    private function assertIssue(User $user, string $departmentId, string $issueId): void
    {
        $report = $this->entityManager->getRepository(ActivityIssueReport::class)->find($issueId);
        if (!$report instanceof ActivityIssueReport) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $activity = $report->getActivity();
        if ($activity->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $this->issuePhotoAccess->assertCanViewPhoto($user, $activity, $report);
    }

    private function assertAccountingBooking(User $user, string $departmentId, string $bookingId): void
    {
        $this->assertAccountingMwOrDc($user, $departmentId);
        $booking = $this->entityManager->find(AccountingBooking::class, $bookingId);
        if (!$booking instanceof AccountingBooking || $booking->getDepartment()->getId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
    }

    private function assertAccountingFollowUp(User $user, string $departmentId, string $followUpId): void
    {
        $this->assertAccountingMwOrDc($user, $departmentId);
        $followUp = $this->entityManager->find(AccountingAcquisitionFollowUp::class, $followUpId);
        if (!$followUp instanceof AccountingAcquisitionFollowUp || $followUp->getDepartment()->getId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
    }

    private function assertJsOrder(User $user, string $departmentId, string $orderId): void
    {
        $order = $this->entityManager->find(ActivityJsOrder::class, $orderId);
        if (!$order instanceof ActivityJsOrder) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $activity = $order->getActivity();
        if ($activity->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        if (!$this->activityAccess->canUserViewActivity($user, $activity)) {
            throw new AccessDeniedHttpException('Kein Zugriff auf diese Datei');
        }
    }

    private function assertGrossanlassQuote(User $user, string $departmentId, string $quoteId): void
    {
        $department = $this->entityManager->find(Department::class, $departmentId);
        if (!$department instanceof Department) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        $this->grossanlassAccess->assertGrossanlassDepartment($department);
        if (!$this->grossanlassAccess->canManagePlanung($user, $department)) {
            throw new AccessDeniedHttpException('Kein Zugriff auf diese Datei');
        }
        $quote = $this->entityManager->find(ActivityGrossanlassProcurementQuote::class, $quoteId);
        if (!$quote instanceof ActivityGrossanlassProcurementQuote) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        if ($quote->getProcurementLine()->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
    }

    public function assertCanBrowseDepartmentMedia(User $user, string $departmentId): void
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$membership instanceof Membership) {
            throw new AccessDeniedHttpException('Kein Zugriff auf die Mediathek');
        }

        $role = strtolower(trim((string) ($membership->getRole() ?? '')));
        if (\in_array($role, self::BASIC_ROLES, true)) {
            throw new AccessDeniedHttpException('Kein Zugriff auf die Mediathek');
        }
    }

    private function assertAccountingMwOrDc(User $user, string $departmentId): void
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return;
        }
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$membership instanceof Membership) {
            throw new AccessDeniedHttpException('Kein Zugriff auf diese Datei');
        }
        $role = strtolower(trim((string) ($membership->getRole() ?? '')));
        if (!\in_array($role, self::ACCOUNTING_ROLES, true)) {
            throw new AccessDeniedHttpException('Kein Zugriff auf diese Datei');
        }
    }
}
