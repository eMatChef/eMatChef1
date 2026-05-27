<?php

namespace App\Service;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\Activity;
use App\Entity\Department;
use App\Entity\InboxMessage;
use App\Entity\User;
use App\Util\IdGenerator;

/**
 * Zusätzliche Inbox-Kategorien (QR, Einladungen, Buchhaltung).
 *
 * @phpstan-require-extends InboxMessageService
 */
trait InboxMessageKindsTrait
{
    // --- QR ---

    public function createQrFoundMessage(
        Department $department,
        string $entityType,
        ?string $materialId,
        ?string $batchId,
        string $publicCode,
        string $materialName,
        string $departmentName,
        ?string $serialLine,
        string $message,
        ?string $senderName,
        ?string $senderEmail,
        string $publicUrl,
    ): array {
        $row = new InboxMessage();
        $row->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, InboxMessage::class, 'pf'));
        $row->setDepartment($department);
        $row->setCategory(InboxMessage::CATEGORY_QR_FOUND);
        $row->setType('qr_contact');
        $row->setRecipientScope(InboxMessage::RECIPIENT_DEPARTMENT_MW);
        $row->setWorkflowStatus(InboxMessage::WORKFLOW_OPEN);
        $row->setBody($message);
        $row->setPayload([
            'entity_type' => $entityType,
            'material_id' => $materialId,
            'batch_id' => $batchId,
            'public_code' => $publicCode,
            'material_name' => $materialName,
            'department_name' => $departmentName,
            'serial_line' => $serialLine,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'public_url' => $publicUrl,
        ]);

        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return $this->toQrFoundArray($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listQrFound(string $departmentId, string $bucket = 'all', int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_QR_FOUND)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 200)));

        if ($bucket === 'open') {
            $qb->andWhere('m.workflowStatus = :st')->setParameter('st', InboxMessage::WORKFLOW_OPEN);
        } elseif ($bucket === 'active') {
            $qb->andWhere('m.workflowStatus IN (:st)')
                ->setParameter('st', [InboxMessage::WORKFLOW_OPEN, InboxMessage::WORKFLOW_IN_PROGRESS]);
        } elseif ($bucket === 'done') {
            $qb->andWhere('m.workflowStatus = :st')->setParameter('st', InboxMessage::WORKFLOW_DONE);
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(fn (InboxMessage $m) => $this->toQrFoundArray($m), $rows);
    }

    public function countUnreadQrFound(string $departmentId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.workflowStatus = :st')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_QR_FOUND)
            ->setParameter('st', InboxMessage::WORKFLOW_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function updateQrFoundStatus(string $departmentId, string $messageId, string $status, ?User $user = null): ?array
    {
        $row = $this->findQrMessage($departmentId, $messageId);
        if (!$row) {
            return null;
        }

        $row->setWorkflowStatus($status);
        if ($status === InboxMessage::WORKFLOW_DONE) {
            $row->setReadAt(new \DateTime());
            $row->setReadByUserId($user?->getId());
        } else {
            $row->setReadAt(null);
            $row->setReadByUserId(null);
        }
        $this->entityManager->flush();

        return $this->toQrFoundArray($row);
    }

    // --- Department invite (join) ---

    /**
     * @param array<string, mixed> $pendingInviteEntry
     */
    public function notifyDepartmentInvite(User $user, Department $department, array $pendingInviteEntry): void
    {
        $inviteId = (string) ($pendingInviteEntry['id'] ?? '');
        if ($inviteId === '') {
            return;
        }

        $this->deleteDepartmentInviteByInviteId($department->getId(), $user->getId(), $inviteId);

        $inviterId = (string) ($pendingInviteEntry['created_by_user_id'] ?? '');
        $row = new InboxMessage();
        $row->setId(IdGenerator::generateUnique($this->entityManager, InboxMessage::class));
        $row->setDepartment($department);
        $row->setCategory(InboxMessage::CATEGORY_DEPARTMENT_INVITE);
        $row->setType('department_invite');
        $row->setRecipientScope(InboxMessage::RECIPIENT_USER);
        $row->setRecipientUserId($user->getId());
        $row->setSenderUserId($inviterId !== '' ? $inviterId : null);
        $row->setSourceRefId($inviteId);
        $row->setWorkflowStatus(InboxMessage::WORKFLOW_PENDING);
        $row->setSubject($department->getName());
        $row->setPayload(array_merge([
            'invite_id' => $inviteId,
            'department_id' => $department->getId(),
            'department_name' => $department->getName(),
            'invited_by_user_id' => $inviterId,
            'invited_by_name' => (string) ($pendingInviteEntry['created_by_name'] ?? ''),
            'role' => (string) ($pendingInviteEntry['role'] ?? 'u'),
            'invite_url' => (string) ($pendingInviteEntry['invite_url'] ?? ''),
            'status' => 'pending',
        ], $this->inviterProfilePayload($inviterId)));

        $created = (string) ($pendingInviteEntry['created_at'] ?? '');
        if ($created !== '') {
            try {
                $row->setCreatedAt(new \DateTime($created));
            } catch (\Throwable) {
            }
        }

        $this->entityManager->persist($row);
        $this->entityManager->flush();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listDepartmentInvitesForUser(string $userId, string $bucket = 'all', int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('m.recipientUserId = :userId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.workflowStatus = :pending')
            ->setParameter('userId', $userId)
            ->setParameter('cat', InboxMessage::CATEGORY_DEPARTMENT_INVITE)
            ->setParameter('pending', InboxMessage::WORKFLOW_PENDING)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 100)));

        if ($bucket === 'unread') {
            $qb->andWhere('m.readAt IS NULL');
        } elseif ($bucket === 'read') {
            $qb->andWhere('m.readAt IS NOT NULL');
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(fn (InboxMessage $m) => $this->toDepartmentInviteArray($m), $rows);
    }

    public function countUnreadDepartmentInvites(string $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(InboxMessage::class, 'm')
            ->where('m.recipientUserId = :userId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.workflowStatus = :pending')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('cat', InboxMessage::CATEGORY_DEPARTMENT_INVITE)
            ->setParameter('pending', InboxMessage::WORKFLOW_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markDepartmentInviteRead(string $userId, string $notificationId): bool
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('m.id = :id')
            ->andWhere('m.recipientUserId = :userId')
            ->andWhere('m.category = :cat')
            ->setParameter('id', $notificationId)
            ->setParameter('userId', $userId)
            ->setParameter('cat', InboxMessage::CATEGORY_DEPARTMENT_INVITE)
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

    public function removeDepartmentInvite(string $departmentId, string $userId, string $inviteId): void
    {
        $this->deleteDepartmentInviteByInviteId($departmentId, $userId, $inviteId);
    }

    // --- Invite accepted (for inviter) ---

    /**
     * @param array<string, mixed> $invite pending-invite entry
     */
    public function notifyInviteAccepted(Department $department, array $invite, User $joinedUser): void
    {
        $inviterId = (string) ($invite['created_by_user_id'] ?? '');
        if ($inviterId === '') {
            return;
        }

        $profile = $joinedUser->getProfile();
        $row = new InboxMessage();
        $row->setId(IdGenerator::generateUnique($this->entityManager, InboxMessage::class));
        $row->setDepartment($department);
        $row->setCategory(InboxMessage::CATEGORY_INVITE_ACCEPTED);
        $row->setType('invite_accepted');
        $row->setRecipientScope(InboxMessage::RECIPIENT_USER);
        $row->setRecipientUserId($inviterId);
        $row->setSenderUserId($joinedUser->getId());
        $row->setSourceRefId((string) ($invite['id'] ?? ''));
        $row->setPayload([
            'email' => (string) ($invite['email'] ?? ''),
            'user_id' => $joinedUser->getId(),
            'user_name' => $profile ? $profile->getDisplayName() : '',
            'invited_by_user_id' => $inviterId,
            'invited_by_name' => (string) ($invite['created_by_name'] ?? ''),
            'role' => (string) ($invite['role'] ?? 'u'),
        ]);

        $this->entityManager->persist($row);
        $this->entityManager->flush();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInviteAcceptedForInviter(
        string $departmentId,
        string $inviterUserId,
        string $bucket = 'all',
        int $limit = 50,
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.recipientUserId = :userId')
            ->andWhere('m.category = :cat')
            ->setParameter('deptId', $departmentId)
            ->setParameter('userId', $inviterUserId)
            ->setParameter('cat', InboxMessage::CATEGORY_INVITE_ACCEPTED)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 100)));

        if ($bucket === 'unread') {
            $qb->andWhere('m.readAt IS NULL');
        } elseif ($bucket === 'read') {
            $qb->andWhere('m.readAt IS NOT NULL');
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(fn (InboxMessage $m) => $this->toInviteAcceptedArray($m), $rows);
    }

    public function countUnreadInviteAccepted(string $departmentId, string $inviterUserId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.recipientUserId = :userId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('deptId', $departmentId)
            ->setParameter('userId', $inviterUserId)
            ->setParameter('cat', InboxMessage::CATEGORY_INVITE_ACCEPTED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markInviteAcceptedRead(string $departmentId, string $inviterUserId, string $notificationId): bool
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('m.id = :id')
            ->andWhere('IDENTITY(m.department) = :deptId')
            ->andWhere('m.recipientUserId = :userId')
            ->andWhere('m.category = :cat')
            ->setParameter('id', $notificationId)
            ->setParameter('deptId', $departmentId)
            ->setParameter('userId', $inviterUserId)
            ->setParameter('cat', InboxMessage::CATEGORY_INVITE_ACCEPTED)
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

    // --- Activity department invite (camp/event) ---

    public function syncActivityDepartmentInvites(Activity $activity): void
    {
        $activityId = $activity->getId();
        $this->entityManager->createQueryBuilder()
            ->delete(InboxMessage::class, 'm')
            ->where('m.activityId = :activityId')
            ->andWhere('m.category = :cat')
            ->setParameter('activityId', $activityId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_DEPT_INVITE)
            ->getQuery()
            ->execute();

        if (in_array($activity->getStatus(), [Activity::STATUS_COMPLETED, Activity::STATUS_CANCELLED], true)) {
            return;
        }

        $invites = $activity->getInvitedDepartments() ?? [];
        $sourceDept = $activity->getDepartment();

        foreach ($invites as $invite) {
            if (!is_array($invite) || ($invite['status'] ?? 'pending') !== 'pending') {
                continue;
            }
            $invitedDeptId = (string) ($invite['id'] ?? '');
            if ($invitedDeptId === '') {
                continue;
            }
            $invitedDept = $this->entityManager->getRepository(Department::class)->find($invitedDeptId);
            if (!$invitedDept) {
                continue;
            }

            $row = new InboxMessage();
            $row->setId(IdGenerator::generateUnique($this->entityManager, InboxMessage::class));
            $row->setDepartment($invitedDept);
            $row->setCategory(InboxMessage::CATEGORY_ACTIVITY_DEPT_INVITE);
            $row->setType('activity_department_invite');
            $row->setRecipientScope(InboxMessage::RECIPIENT_DEPARTMENT_MW);
            $row->setActivityId($activityId);
            $row->setWorkflowStatus(InboxMessage::WORKFLOW_PENDING);
            $row->setSubject($activity->getName());
            $row->setPayload([
                'activity_id' => $activityId,
                'activity_name' => $activity->getName(),
                'activity_type' => $activity->getType(),
                'usage_start' => $activity->getUsageStart()?->format(\DateTimeInterface::ATOM),
                'usage_end' => $activity->getUsageEnd()?->format(\DateTimeInterface::ATOM),
                'source_department_id' => $sourceDept->getId(),
                'source_department_name' => $sourceDept->getName(),
                'invited_at' => $invite['invited_at'] ?? (new \DateTime())->format(\DateTimeInterface::ATOM),
            ]);

            $this->entityManager->persist($row);
        }

        $this->entityManager->flush();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingActivityDepartmentInvites(string $departmentId, int $limit = 200): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.workflowStatus = :pending')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_DEPT_INVITE)
            ->setParameter('pending', InboxMessage::WORKFLOW_PENDING)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 200)))
            ->getQuery()
            ->getResult();

        return array_map(fn (InboxMessage $m) => $this->toActivityDepartmentInviteArray($m), $rows);
    }

    public function removeActivityDepartmentInvite(string $activityId, string $invitedDepartmentId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(InboxMessage::class, 'm')
            ->where('m.activityId = :activityId')
            ->andWhere('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->setParameter('activityId', $activityId)
            ->setParameter('deptId', $invitedDepartmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACTIVITY_DEPT_INVITE)
            ->getQuery()
            ->execute();
    }

    // --- Accounting follow-up (Inbox-Spiegel; Fachdaten bleiben in accounting_acquisition_follow_up) ---

    public function syncAccountingFollowUp(AccountingAcquisitionFollowUp $followUp): void
    {
        if ($followUp->getStatus() !== AccountingAcquisitionFollowUp::STATUS_PENDING) {
            $this->removeAccountingFollowUpInbox($followUp->getId());

            return;
        }

        $existing = $this->findAccountingFollowUpInbox($followUp->getDepartment()->getId(), $followUp->getId());
        $row = $existing ?? new InboxMessage();
        if ($existing === null) {
            $row->setId(IdGenerator::generateUnique($this->entityManager, InboxMessage::class));
            $this->entityManager->persist($row);
        }

        $row->setDepartment($followUp->getDepartment());
        $row->setCategory(InboxMessage::CATEGORY_ACCOUNTING_FOLLOWUP);
        $row->setType((string) ($followUp->getSourceKind() ?? 'batch'));
        $row->setRecipientScope(InboxMessage::RECIPIENT_DEPARTMENT_MW);
        $row->setActivityId($followUp->getActivity()?->getId());
        $row->setSourceRefId($followUp->getId());
        $row->setWorkflowStatus(InboxMessage::WORKFLOW_PENDING);
        $row->setSubject($followUp->getReceiptLabel() ?? 'Buchhaltung');
        $row->setPayload([
            'amount' => $followUp->getAmount(),
            'receipt_label' => $followUp->getReceiptLabel(),
            'source_kind' => $followUp->getSourceKind(),
            'suggested_date' => $followUp->getSuggestedDate()->format('Y-m-d'),
            'material_batch_id' => $followUp->getMaterialBatch()?->getId(),
            'activity_id' => $followUp->getActivity()?->getId(),
        ]);

        $this->entityManager->flush();
    }

    public function removeAccountingFollowUpInbox(string $followUpId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(InboxMessage::class, 'm')
            ->where('m.sourceRefId = :ref')
            ->andWhere('m.category = :cat')
            ->setParameter('ref', $followUpId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACCOUNTING_FOLLOWUP)
            ->getQuery()
            ->execute();
    }

    public function countPendingAccountingFollowUps(string $departmentId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->andWhere('m.workflowStatus = :pending')
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACCOUNTING_FOLLOWUP)
            ->setParameter('pending', InboxMessage::WORKFLOW_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // --- Serializers ---

    /**
     * @return array<string, mixed>
     */
    public function toQrFoundArray(InboxMessage $m): array
    {
        $p = $m->getPayload();

        return [
            'id' => $m->getId(),
            'entity_type' => $p['entity_type'] ?? 'material',
            'material_id' => $p['material_id'] ?? null,
            'batch_id' => $p['batch_id'] ?? null,
            'public_code' => $p['public_code'] ?? '',
            'material_name' => $p['material_name'] ?? '',
            'department_name' => $p['department_name'] ?? '',
            'serial_line' => $p['serial_line'] ?? null,
            'message' => $m->getBody() ?? '',
            'sender_name' => $p['sender_name'] ?? null,
            'sender_email' => $p['sender_email'] ?? null,
            'public_url' => $p['public_url'] ?? '',
            'status' => $m->getWorkflowStatus() ?? InboxMessage::WORKFLOW_OPEN,
            'created_at' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'read_at' => $m->getReadAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toInviteAcceptedArray(InboxMessage $m): array
    {
        $p = $m->getPayload();

        return [
            'id' => $m->getId(),
            'type' => 'invite_accepted',
            'email' => $p['email'] ?? '',
            'user_id' => $p['user_id'] ?? $m->getSenderUserId(),
            'user_name' => $p['user_name'] ?? '',
            'invited_by_user_id' => $p['invited_by_user_id'] ?? $m->getRecipientUserId(),
            'invited_by_name' => $p['invited_by_name'] ?? '',
            'role' => $p['role'] ?? 'u',
            'accepted_at' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'read' => $m->isRead(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDepartmentInviteArray(InboxMessage $m): array
    {
        $p = $m->getPayload();

        return array_merge($p, [
            'id' => $m->getId(),
            'type' => 'department_invite',
            'read' => $m->isRead(),
            'read_at' => $m->getReadAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'status' => $m->getWorkflowStatus() ?? InboxMessage::WORKFLOW_PENDING,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toActivityDepartmentInviteArray(InboxMessage $m): array
    {
        $p = $m->getPayload();

        return [
            'activity_id' => $p['activity_id'] ?? $m->getActivityId(),
            'activity_name' => $p['activity_name'] ?? $m->getSubject(),
            'activity_type' => $p['activity_type'] ?? '',
            'usage_start' => $p['usage_start'] ?? null,
            'usage_end' => $p['usage_end'] ?? null,
            'source_department_id' => $p['source_department_id'] ?? '',
            'source_department_name' => $p['source_department_name'] ?? '',
            'invited_at' => $p['invited_at'] ?? $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function findQrMessage(string $departmentId, string $messageId): ?InboxMessage
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('m.id = :id')
            ->andWhere('IDENTITY(m.department) = :deptId')
            ->andWhere('m.category = :cat')
            ->setParameter('id', $messageId)
            ->setParameter('deptId', $departmentId)
            ->setParameter('cat', InboxMessage::CATEGORY_QR_FOUND)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function findAccountingFollowUpInbox(string $departmentId, string $followUpId): ?InboxMessage
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.sourceRefId = :ref')
            ->andWhere('m.category = :cat')
            ->setParameter('deptId', $departmentId)
            ->setParameter('ref', $followUpId)
            ->setParameter('cat', InboxMessage::CATEGORY_ACCOUNTING_FOLLOWUP)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function deleteDepartmentInviteByInviteId(string $departmentId, string $userId, string $inviteId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(InboxMessage::class, 'm')
            ->where('IDENTITY(m.department) = :deptId')
            ->andWhere('m.recipientUserId = :userId')
            ->andWhere('m.sourceRefId = :inviteId')
            ->andWhere('m.category = :cat')
            ->setParameter('deptId', $departmentId)
            ->setParameter('userId', $userId)
            ->setParameter('inviteId', $inviteId)
            ->setParameter('cat', InboxMessage::CATEGORY_DEPARTMENT_INVITE)
            ->getQuery()
            ->execute();
    }

    /**
     * @return array<string, string|null>
     */
    private function inviterProfilePayload(string $inviterId): array
    {
        if ($inviterId === '') {
            return [];
        }
        $user = $this->entityManager->getRepository(User::class)->find($inviterId);
        if (!$user) {
            return [];
        }
        $profile = $user->getProfile();

        return [
            'invited_by_first_name' => $profile?->getFirstName(),
            'invited_by_last_name' => $profile?->getLastName(),
            'invited_by_nickname' => $profile?->getNickname(),
            'invited_by_avatar_initials' => $profile?->getAvatarInitials(),
            'invited_by_background_color' => $profile?->getBackgroundColor(),
            'invited_by_text_color' => $profile?->getTextColor(),
        ];
    }
}
