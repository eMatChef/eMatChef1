<?php

namespace App\Service\Display;

use App\Entity\Activity;
use App\Entity\Department;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\WorkshopTicket;
use App\Service\ActivityAccessService;
use App\Service\Public\PublicCodeService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Read-only Infoscreen-Daten (Abteilungsweite Sicht, ohne User-JWT).
 */
final class DepartmentDisplayDataService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicCodeService $publicCodeService,
        private ActivityAccessService $activityAccess,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayloadForScreen(DepartmentDisplayScreen $screen): array
    {
        $departmentId = $screen->getDepartmentId();
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        $departmentName = $department?->getName() ?? '';

        $showActivities = $screen->isShowActivities();
        $showWorkshop = $screen->isShowWorkshop();

        return [
            'department_name' => $departmentName,
            'screen_name' => $screen->getName(),
            'subtitle_text' => $screen->getSubtitleText(),
            'show_activities' => $showActivities,
            'show_workshop' => $showWorkshop,
            'activities' => $showActivities ? $this->loadActivities($departmentId) : [],
            'workshop_tickets' => $showWorkshop ? $this->loadWorkshopTickets($departmentId) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadActivities(string $departmentId): array
    {
        $upcomingStatuses = [
            Activity::STATUS_DRAFT,
            Activity::STATUS_SUBMITTED,
            Activity::STATUS_APPROVED,
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
        ];

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Activity::class, 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('statuses', $upcomingStatuses)
            ->orderBy('a.usageStart', 'ASC');

        $own = $qb->getQuery()->getResult();

        $invitedQb = $this->entityManager->createQueryBuilder();
        $invitedQb->select('a')
            ->from(Activity::class, 'a')
            ->where('a.departmentId != :departmentId')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.type IN (:invitedTypes)')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('invitedTypes', ['camp', 'event'])
            ->setParameter('statuses', $upcomingStatuses);

        $invited = [];
        foreach ($invitedQb->getQuery()->getResult() as $candidate) {
            if (!$candidate instanceof Activity) {
                continue;
            }
            if (!$this->activityAccess->isDepartmentInviteAccepted($candidate, $departmentId)) {
                continue;
            }
            $invited[] = $candidate;
        }

        $merged = [];
        foreach (array_merge($own, $invited) as $activity) {
            if (!$activity instanceof Activity) {
                continue;
            }
            $merged[$activity->getId()] = $activity;
        }

        $rows = [];
        foreach ($merged as $activity) {
            $rows[] = $this->serializeActivity($activity);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadWorkshopTickets(string $departmentId): array
    {
        $tickets = $this->entityManager->getRepository(WorkshopTicket::class)->findBy(
            ['departmentId' => $departmentId],
            ['createdAt' => 'DESC'],
        );

        $rows = [];
        foreach ($tickets as $ticket) {
            if (!$ticket instanceof WorkshopTicket) {
                continue;
            }
            $rows[] = $this->serializeWorkshopTicket($ticket);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeActivity(Activity $activity): array
    {
        $activityPublicEntry = $this->publicCodeService->getActiveActivityPublicCode((string) $activity->getId());
        $activityPublicCode = $activityPublicEntry?->getPublicCode();

        return [
            'id' => $activity->getId(),
            'name' => $activity->getName(),
            'type' => $activity->getType(),
            'status' => $activity->getStatus(),
            'usage_start' => $activity->getUsageStart()?->format('c'),
            'usage_end' => $activity->getUsageEnd()?->format('c'),
            'planning_start' => $activity->getPlanningStart()?->format('c'),
            'planning_end' => $activity->getPlanningEnd()?->format('c'),
            'public_code' => $activityPublicCode,
            'public_url' => $activityPublicCode
                ? $this->publicCodeService->buildActivityPublicUrl($activityPublicCode)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWorkshopTicket(WorkshopTicket $ticket): array
    {
        $material = $ticket->getMaterialItem();
        $workshopPublicEntry = $this->publicCodeService->getActiveWorkshopPublicCode((string) $ticket->getId());
        $workshopPublicCode = $workshopPublicEntry?->getPublicCode();

        return [
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'priority' => $ticket->getPriority(),
            'priority_label' => $ticket->getPriorityLabel(),
            'status' => $ticket->getStatus(),
            'status_label' => $ticket->getStatusLabel(),
            'created_at' => $ticket->getCreatedAt()->format('c'),
            'material_item' => [
                'id' => $material->getId(),
                'name' => $material->getName(),
            ],
            'public_code' => $workshopPublicCode,
            'public_url' => $workshopPublicCode
                ? $this->publicCodeService->buildWorkshopPublicUrl($workshopPublicCode)
                : null,
        ];
    }
}
