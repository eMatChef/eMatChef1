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
        private DepartmentDisplayScreenService $displayScreenService,
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
        $showStatistics = $screen->isShowStatistics();
        $activityTypes = $this->displayScreenService->normalizeActivityTypes($screen->getActivityTypes());
        $activityStatuses = $this->displayScreenService->normalizeActivityStatuses($screen->getActivityStatuses());
        $workshopStatuses = $this->displayScreenService->normalizeWorkshopStatuses($screen->getWorkshopStatuses());

        return [
            'department_name' => $departmentName,
            'screen_name' => $screen->getName(),
            'subtitle_text' => $screen->getSubtitleText(),
            'show_activities' => $showActivities,
            'show_workshop' => $showWorkshop,
            'show_statistics' => $showStatistics,
            'activity_types' => $activityTypes,
            'activity_statuses' => $activityStatuses,
            'workshop_statuses' => $workshopStatuses,
            'activities' => $showActivities
                ? $this->loadActivities($departmentId, $activityTypes, $activityStatuses)
                : [],
            'workshop_tickets' => $showWorkshop
                ? $this->loadWorkshopTickets($departmentId, $workshopStatuses)
                : [],
            'statistics' => $showStatistics
                ? $this->buildStatistics($departmentId, $activityTypes, $activityStatuses, $workshopStatuses)
                : null,
        ];
    }

    /**
     * @param list<string> $allowedTypes
     * @param list<string> $allowedStatuses
     *
     * @return list<array<string, mixed>>
     */
    private function loadActivities(string $departmentId, array $allowedTypes, array $allowedStatuses): array
    {
        if ($allowedTypes === [] || $allowedStatuses === []) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Activity::class, 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.type IN (:allowedTypes)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('statuses', $allowedStatuses)
            ->setParameter('allowedTypes', $allowedTypes)
            ->orderBy('a.usageStart', 'ASC');

        $own = $qb->getQuery()->getResult();

        $invitedTypes = array_values(array_intersect(['camp', 'event'], $allowedTypes));
        $invited = [];
        if ($invitedTypes !== []) {
            $invitedQb = $this->entityManager->createQueryBuilder();
            $invitedQb->select('a')
                ->from(Activity::class, 'a')
                ->where('a.departmentId != :departmentId')
                ->andWhere('a.deletedAt IS NULL')
                ->andWhere('a.type IN (:invitedTypes)')
                ->andWhere('a.status IN (:statuses)')
                ->setParameter('departmentId', $departmentId)
                ->setParameter('invitedTypes', $invitedTypes)
                ->setParameter('statuses', $allowedStatuses);

            foreach ($invitedQb->getQuery()->getResult() as $candidate) {
                if (!$candidate instanceof Activity) {
                    continue;
                }
                if (!$this->activityAccess->isDepartmentInviteAccepted($candidate, $departmentId)) {
                    continue;
                }
                $invited[] = $candidate;
            }
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
     * @param list<string> $allowedStatuses
     *
     * @return list<array<string, mixed>>
     */
    private function loadWorkshopTickets(string $departmentId, array $allowedStatuses): array
    {
        if ($allowedStatuses === []) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(WorkshopTicket::class, 't')
            ->where('t.departmentId = :departmentId')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('statuses', $allowedStatuses)
            ->orderBy('t.createdAt', 'DESC');

        $rows = [];
        foreach ($qb->getQuery()->getResult() as $ticket) {
            if (!$ticket instanceof WorkshopTicket) {
                continue;
            }
            $rows[] = $this->serializeWorkshopTicket($ticket);
        }

        return $rows;
    }

    /**
     * Zähler für Statistik-Leiste (ohne Datumsfilter der Anzeige-Liste).
     *
     * @param list<string> $activityTypes
     * @param list<string> $activityStatuses
     * @param list<string> $workshopStatuses
     *
     * @return array{activities_by_status: array<string, int>, workshop_by_status: array<string, int>}
     */
    private function buildStatistics(
        string $departmentId,
        array $activityTypes,
        array $activityStatuses,
        array $workshopStatuses,
    ): array {
        $activitiesByStatus = [];
        foreach ($activityStatuses as $status) {
            $activitiesByStatus[$status] = 0;
        }

        if ($activityTypes !== [] && $activityStatuses !== []) {
            foreach ($this->loadActivities($departmentId, $activityTypes, $activityStatuses) as $row) {
                $status = (string) ($row['status'] ?? '');
                if (\array_key_exists($status, $activitiesByStatus)) {
                    ++$activitiesByStatus[$status];
                }
            }
        }

        $workshopByStatus = [];
        foreach ($workshopStatuses as $status) {
            $workshopByStatus[$status] = 0;
        }

        if ($workshopStatuses !== []) {
            foreach ($this->loadWorkshopTickets($departmentId, $workshopStatuses) as $row) {
                $status = (string) ($row['status'] ?? '');
                if (\array_key_exists($status, $workshopByStatus)) {
                    ++$workshopByStatus[$status];
                }
            }
        }

        return [
            'activities_by_status' => $activitiesByStatus,
            'workshop_by_status' => $workshopByStatus,
        ];
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
