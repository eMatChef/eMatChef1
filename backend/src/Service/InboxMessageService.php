<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityIssueReport;
use App\Entity\Department;
use App\Entity\GroupMembership;
use App\Entity\InboxMessage;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zentrale Persistenz für Inbox-Nachrichten (ersetzt JSON in department_setting).
 */
class InboxMessageService
{
    use InboxMessageKindsTrait;

    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {}

    public function sendUserMessage(
        Department $department,
        User $sender,
        User $recipient,
        string $subject,
        string $message,
    ): array {
        $profile = $sender->getProfile();
        $recipientProfile = $recipient->getProfile();

        $row = new InboxMessage();
        $row->setId(IdGenerator::generateUnique($this->entityManager, InboxMessage::class));
        $row->setDepartment($department);
        $row->setCategory(InboxMessage::CATEGORY_USER_MESSAGE);
        $row->setType('user_message');
        $row->setRecipientScope(InboxMessage::RECIPIENT_USER);
        $row->setRecipientUserId($recipient->getId());
        $row->setSenderUserId($sender->getId());
        $row->setSubject($subject);
        $row->setBody($message);
        $row->setPayload([
            'sender_name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'sender_first_name' => $profile?->getFirstName(),
            'sender_last_name' => $profile?->getLastName(),
            'sender_nickname' => $profile?->getNickname(),
            'sender_avatar_initials' => $profile?->getAvatarInitials(),
            'sender_background_color' => $profile?->getBackgroundColor(),
            'sender_text_color' => $profile?->getTextColor(),
            'recipient_name' => $recipientProfile ? $recipientProfile->getDisplayName() : 'Unbekannt',
            'recipient_first_name' => $recipientProfile?->getFirstName(),
            'recipient_last_name' => $recipientProfile?->getLastName(),
            'recipient_nickname' => $recipientProfile?->getNickname(),
            'recipient_avatar_initials' => $recipientProfile?->getAvatarInitials(),
            'recipient_background_color' => $recipientProfile?->getBackgroundColor(),
            'recipient_text_color' => $recipientProfile?->getTextColor(),
        ]);

        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return $this->toUserMessageArray($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUserInbox(string $departmentId, string $recipientUserId, string $bucket = 'all', int $limit = 100): array
    {
        $qb = $this->baseUserInboxQuery($departmentId, $recipientUserId);
        $this->applyReadBucket($qb, $bucket);
        $rows = $qb->setMaxResults(max(1, min($limit, 200)))->getQuery()->getResult();

        return array_map(fn (InboxMessage $m) => $this->toUserMessageArray($m), $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUserSent(string $departmentId, string $senderUserId, int $limit = 100): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.senderUserId = :senderId')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_USER_MESSAGE)
            ->setParameter('senderId', $senderUserId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 200)))
            ->getQuery()
            ->getResult();

        return array_map(fn (InboxMessage $m) => $this->toSentMessageArray($m), $rows);
    }

    public function countUnreadUserMessages(string $departmentId, string $recipientUserId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientUserId = :userId')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_USER_MESSAGE)
            ->setParameter('userId', $recipientUserId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markUserMessageRead(string $departmentId, string $recipientUserId, string $messageId): bool
    {
        $row = $this->findUserInboxMessage($departmentId, $recipientUserId, $messageId);
        if (!$row) {
            return false;
        }
        $row->setReadAt(new \DateTime());
        $this->entityManager->flush();

        return true;
    }

    public function notifyActivitySubmitted(Activity $activity, User $actor): void
    {
        $department = $activity->getDepartment();
        $this->removeUnreadActivityDuplicate(
            $department->getId(),
            $activity->getId(),
            'activity_submitted',
            InboxMessage::CATEGORY_ACTIVITY_MW,
            InboxMessage::RECIPIENT_DEPARTMENT_MW,
            null,
        );

        $row = $this->buildActivityRow($activity, $actor, 'activity_submitted', InboxMessage::CATEGORY_ACTIVITY_MW, InboxMessage::RECIPIENT_DEPARTMENT_MW, null);
        $this->entityManager->persist($row);
        $this->entityManager->flush();
    }

    /** Gruppe/User hat «Retour erfassen» — MW/DC können Material wieder ins Lager nehmen. */
    public function notifyActivityReturned(Activity $activity, User $actor): void
    {
        $department = $activity->getDepartment();
        $this->removeUnreadActivityDuplicate(
            $department->getId(),
            $activity->getId(),
            'activity_returned_mw',
            InboxMessage::CATEGORY_ACTIVITY_MW,
            InboxMessage::RECIPIENT_DEPARTMENT_MW,
            null,
        );

        $row = $this->buildActivityRow(
            $activity,
            $actor,
            'activity_returned_mw',
            InboxMessage::CATEGORY_ACTIVITY_MW,
            InboxMessage::RECIPIENT_DEPARTMENT_MW,
            null,
        );
        $this->entityManager->persist($row);
        $this->entityManager->flush();
    }

    public function notifyActivityUserStatus(Activity $activity, User $actor, string $type): void
    {
        $recipient = $activity->getResponsibleUser() ?? $activity->getCreatedByUser();
        if (!$recipient || $recipient->getId() === $actor->getId()) {
            return;
        }

        $this->persistActivityUserStatusNotification($activity, $actor, $type, $recipient);
        $this->entityManager->flush();
    }

    /**
     * «Gepackt markieren»: Ersteller der Aktivität und alle Mitglieder der zugeordneten Gruppe.
     */
    public function notifyActivityPacked(Activity $activity, User $actor): void
    {
        $recipients = $this->collectActivityPackedRecipients($activity, $actor);
        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $recipient) {
            $this->persistActivityUserStatusNotification($activity, $actor, 'activity_packed', $recipient);
        }
        $this->entityManager->flush();
    }

    private function persistActivityUserStatusNotification(
        Activity $activity,
        User $actor,
        string $type,
        User $recipient,
    ): void {
        $department = $activity->getDepartment();
        $this->removeUnreadActivityDuplicate(
            $department->getId(),
            $activity->getId(),
            $type,
            InboxMessage::CATEGORY_ACTIVITY_USER,
            InboxMessage::RECIPIENT_USER,
            $recipient->getId(),
        );

        $row = $this->buildActivityRow(
            $activity,
            $actor,
            $type,
            InboxMessage::CATEGORY_ACTIVITY_USER,
            InboxMessage::RECIPIENT_USER,
            $recipient->getId(),
        );
        $this->entityManager->persist($row);
    }

    /**
     * @return list<User>
     */
    private function collectActivityPackedRecipients(Activity $activity, User $actor): array
    {
        $byId = [];
        $actorId = $actor->getId();

        $creator = $activity->getCreatedByUser();
        if ($creator !== null && $creator->getId() !== $actorId) {
            $byId[$creator->getId()] = $creator;
        }

        $groupId = trim((string) ($activity->getGroupId() ?? ''));
        if ($groupId !== '') {
            $memberships = $this->entityManager->getRepository(GroupMembership::class)->findBy(['groupId' => $groupId]);
            foreach ($memberships as $membership) {
                $user = $membership->getUser();
                if ($user->getId() !== $actorId) {
                    $byId[$user->getId()] = $user;
                }
            }
        }

        return array_values($byId);
    }

    /**
     * Verlust/Reparatur/Schaden: MW-Inbox + Ersteller/Gruppe (jede Meldung ein eigener Eintrag).
     */
    public function notifyActivityIssueReported(Activity $activity, User $actor, ActivityIssueReport $report): void
    {
        if (!\in_array($report->getType(), [
            ActivityIssueReport::TYPE_LOSS,
            ActivityIssueReport::TYPE_REPAIR,
            ActivityIssueReport::TYPE_DAMAGE,
        ], true)) {
            return;
        }

        $extra = $this->issueReportNotificationPayload($report);

        $mwRow = $this->buildActivityRow(
            $activity,
            $actor,
            'activity_issue_reported',
            InboxMessage::CATEGORY_ACTIVITY_MW,
            InboxMessage::RECIPIENT_DEPARTMENT_MW,
            null,
        );
        $mwRow->setPayload(array_merge($mwRow->getPayload(), $extra));
        $this->entityManager->persist($mwRow);

        foreach ($this->collectActivityPackedRecipients($activity, $actor) as $recipient) {
            $userRow = $this->buildActivityRow(
                $activity,
                $actor,
                'activity_issue_reported',
                InboxMessage::CATEGORY_ACTIVITY_USER,
                InboxMessage::RECIPIENT_USER,
                $recipient->getId(),
            );
            $userRow->setPayload(array_merge($userRow->getPayload(), $extra));
            $this->entityManager->persist($userRow);
        }

        $this->entityManager->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function issueReportNotificationPayload(ActivityIssueReport $report): array
    {
        $material = $report->getMaterialItem();
        $materialName = $material?->getName() ?? '';

        return [
            'issue_report_id' => $report->getId(),
            'issue_report_type' => $report->getType(),
            'issue_report_quantity' => $report->getQuantity(),
            'material_item_id' => $report->getMaterialItemId(),
            'material_name' => $materialName,
        ];
    }

    /** Storno durch MW/DC: persönliche Meldung an den Ersteller (bleibt nach purgeByActivity erhalten). */
    public function notifyActivityCancelled(Activity $activity, User $actor): void
    {
        $recipient = $activity->getCreatedByUser();
        if (!$recipient || $recipient->getId() === $actor->getId()) {
            return;
        }

        $department = $activity->getDepartment();
        $this->removeUnreadActivityDuplicate(
            $department->getId(),
            $activity->getId(),
            'activity_cancelled',
            InboxMessage::CATEGORY_ACTIVITY_USER,
            InboxMessage::RECIPIENT_USER,
            $recipient->getId(),
        );

        $row = $this->buildActivityRow(
            $activity,
            $actor,
            'activity_cancelled',
            InboxMessage::CATEGORY_ACTIVITY_USER,
            InboxMessage::RECIPIENT_USER,
            $recipient->getId(),
        );
        $this->entityManager->persist($row);
        $this->entityManager->flush();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActivityMw(string $departmentId, string $bucket = 'all', int $limit = 100): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientScope = :scope')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_MW)
            ->setParameter('scope', InboxMessage::RECIPIENT_DEPARTMENT_MW)
            ->orderBy('m.createdAt', 'DESC');

        $this->applyReadBucket($qb, $bucket);

        $rows = $qb->setMaxResults(max(1, min($limit, 200)))->getQuery()->getResult();

        return array_map(fn (InboxMessage $m) => $this->toActivityNotificationArray($m), $rows);
    }

    public function countUnreadActivityMw(string $departmentId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientScope = :scope')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_MW)
            ->setParameter('scope', InboxMessage::RECIPIENT_DEPARTMENT_MW)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markActivityMwRead(string $departmentId, string $notificationId): bool
    {
        $row = $this->findActivityMwMessage($departmentId, $notificationId);
        if (!$row) {
            return false;
        }
        $row->setReadAt(new \DateTime());
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActivityUser(string $departmentId, string $userId, string $bucket = 'all', int $limit = 100): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientUserId = :userId')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_USER)
            ->setParameter('userId', $userId)
            ->orderBy('m.createdAt', 'DESC');

        $this->applyReadBucket($qb, $bucket);

        $rows = $qb->setMaxResults(max(1, min($limit, 200)))->getQuery()->getResult();

        return array_map(fn (InboxMessage $m) => $this->toActivityNotificationArray($m), $rows);
    }

    public function countUnreadActivityUser(string $departmentId, string $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientUserId = :userId')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_USER)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markActivityUserRead(string $departmentId, string $userId, string $notificationId): bool
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('m.id = :id')
            ->andWhere('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientUserId = :userId')
            ->setParameter('id', $notificationId)
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_USER)
            ->setParameter('userId', $userId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$row) {
            return false;
        }
        $row->setReadAt(new \DateTime());
        $this->entityManager->flush();

        return true;
    }

    /**
     * Entfernt aktivitätsbezogene Inbox-Einträge nach Abschluss/Storno (Historie bleibt in activity_history).
     */
    public function purgeByActivity(Department $department, string $activityId): int
    {
        return $this->entityManager->createQueryBuilder()
            ->delete(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.activityId = :activityId')
            ->andWhere('m.category IN (:categories)')
            ->andWhere('NOT (m.category = :userCat AND m.type = :cancelledType)')
            ->setParameter('deptId', $department->getId())
            ->setParameter('activityId', $activityId)
            ->setParameter('categories', InboxMessage::CATEGORIES_PURGE_ON_ACTIVITY_TERMINAL)
            ->setParameter('userCat', InboxMessage::CATEGORY_ACTIVITY_USER)
            ->setParameter('cancelledType', 'activity_cancelled')
            ->getQuery()
            ->execute();
    }

    /**
     * @return array<string, mixed>
     */
    public function toUserMessageArray(InboxMessage $m): array
    {
        $p = $m->getPayload();

        return [
            'id' => $m->getId(),
            'type' => 'user_message',
            'sender_user_id' => $m->getSenderUserId(),
            'sender_name' => $p['sender_name'] ?? 'Unbekannt',
            'sender_first_name' => $p['sender_first_name'] ?? null,
            'sender_last_name' => $p['sender_last_name'] ?? null,
            'sender_nickname' => $p['sender_nickname'] ?? null,
            'sender_avatar_initials' => $p['sender_avatar_initials'] ?? null,
            'sender_background_color' => $p['sender_background_color'] ?? null,
            'sender_text_color' => $p['sender_text_color'] ?? null,
            'subject' => $m->getSubject() ?? '',
            'message' => $m->getBody() ?? '',
            'created_at' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'read' => $m->isRead(),
            'read_at' => $m->getReadAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSentMessageArray(InboxMessage $m): array
    {
        $base = $this->toUserMessageArray($m);
        $p = $m->getPayload();

        return array_merge($base, [
            'recipient_user_id' => $m->getRecipientUserId(),
            'recipient_name' => $p['recipient_name'] ?? 'Unbekannt',
            'recipient_first_name' => $p['recipient_first_name'] ?? null,
            'recipient_last_name' => $p['recipient_last_name'] ?? null,
            'recipient_nickname' => $p['recipient_nickname'] ?? null,
            'recipient_avatar_initials' => $p['recipient_avatar_initials'] ?? null,
            'recipient_background_color' => $p['recipient_background_color'] ?? null,
            'recipient_text_color' => $p['recipient_text_color'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toActivityNotificationArray(InboxMessage $m): array
    {
        $p = $m->getPayload();

        return array_merge($p, [
            'id' => $m->getId(),
            'type' => $m->getType(),
            'activity_id' => $m->getActivityId(),
            'created_at' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'read' => $m->isRead(),
            'read_at' => $m->getReadAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function buildActivityRow(
        Activity $activity,
        User $actor,
        string $type,
        string $category,
        string $recipientScope,
        ?string $recipientUserId,
    ): InboxMessage {
        $profile = $actor->getProfile();
        $group = $activity->getGroup();

        $row = new InboxMessage();
        $row->setId(IdGenerator::generateUnique($this->entityManager, InboxMessage::class));
        $row->setDepartment($activity->getDepartment());
        $row->setCategory($category);
        $row->setType($type);
        $row->setRecipientScope($recipientScope);
        $row->setRecipientUserId($recipientUserId);
        $row->setSenderUserId($actor->getId());
        $row->setActivityId($activity->getId());
        $row->setPayload([
            'activity_name' => $activity->getName(),
            'activity_type' => $activity->getType(),
            'activity_no' => $activity->getNo(),
            'activity_status' => $activity->getStatus(),
            'group_id' => $group?->getId(),
            'group_name' => $group?->getName(),
            'creator_user_id' => $actor->getId(),
            'creator_name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'creator_first_name' => $profile?->getFirstName(),
            'creator_last_name' => $profile?->getLastName(),
            'creator_nickname' => $profile?->getNickname(),
            'creator_avatar_initials' => $profile?->getAvatarInitials(),
            'creator_background_color' => $profile?->getBackgroundColor(),
            'creator_text_color' => $profile?->getTextColor(),
        ]);

        return $row;
    }

    private function removeUnreadActivityDuplicate(
        string $departmentId,
        string $activityId,
        string $type,
        string $category,
        string $recipientScope,
        ?string $recipientUserId,
    ): void {
        $qb = $this->entityManager->createQueryBuilder()
            ->delete(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.activityId = :activityId')
            ->andWhere('m.type = :type')
            ->andWhere('m.category = :category')
            ->andWhere('m.recipientScope = :scope')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('deptId', $departmentId)
            ->setParameter('activityId', $activityId)
            ->setParameter('type', $type)
            ->setParameter('category', $category)
            ->setParameter('scope', $recipientScope);

        if ($recipientUserId !== null) {
            $qb->andWhere('m.recipientUserId = :recipientUserId')
                ->setParameter('recipientUserId', $recipientUserId);
        } else {
            $qb->andWhere('m.recipientUserId IS NULL');
        }

        $qb->getQuery()->execute();
    }

    private function findUserInboxMessage(string $departmentId, string $recipientUserId, string $messageId): ?InboxMessage
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('m.id = :id')
            ->andWhere('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientUserId = :userId')
            ->setParameter('id', $messageId)
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_USER_MESSAGE)
            ->setParameter('userId', $recipientUserId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function findActivityMwMessage(string $departmentId, string $messageId): ?InboxMessage
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('m.id = :id')
            ->andWhere('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientScope = :scope')
            ->setParameter('id', $messageId)
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_MW)
            ->setParameter('scope', InboxMessage::RECIPIENT_DEPARTMENT_MW)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function baseUserInboxQuery(string $departmentId, string $recipientUserId)
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.recipientUserId = :userId')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_USER_MESSAGE)
            ->setParameter('userId', $recipientUserId)
            ->orderBy('m.createdAt', 'DESC');
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $qb
     */
    private function applyReadBucket($qb, string $bucket): void
    {
        $bucket = strtolower(trim($bucket));
        if ($bucket === 'unread') {
            $qb->andWhere('m.readAt IS NULL');
        } elseif ($bucket === 'read') {
            $qb->andWhere('m.readAt IS NOT NULL');
        }
    }
}
