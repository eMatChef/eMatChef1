<?php

namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\Department;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class AuditLogger
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function log(
        string $entityType,
        string $entityId,
        string $action,
        ?User $actor = null,
        ?User $targetUser = null,
        ?Department $department = null,
        array $changes = []
    ): void {
        $event = new AuditEvent();
        $event->setId(IdGenerator::generate13Unique($this->entityManager, AuditEvent::class, 'ae'));
        $event->setEntityType($entityType);
        $event->setEntityId($entityId);
        $event->setAction($action);
        $event->setActorUserId($actor?->getId());
        $event->setTargetUserId($targetUser?->getId());
        $event->setDepartmentId($department?->getId());
        $event->setChanges($changes);

        $this->entityManager->persist($event);
    }

    public static function buildMembershipEntityId(string $userId, string $departmentId): string
    {
        return $userId . ':' . $departmentId;
    }
}
