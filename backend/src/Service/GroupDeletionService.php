<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Repository\ExternalStructureIdentityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Single place for deleting a Group: never cascades to members or child groups.
 */
final class GroupDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalStructureIdentityRepository $structureIdentities,
    ) {}

    /**
     * @throws GroupNotDeletableException when the group still has members or child groups
     */
    public function delete(Group $group): void
    {
        if ($this->entityManager->getRepository(GroupMembership::class)->count(['groupId' => $group->getId()]) > 0) {
            throw new GroupNotDeletableException('has_members', 'Die Gruppe hat noch Mitglieder und kann nicht gelöscht werden. Entferne zuerst alle Mitglieder.');
        }
        if ($this->entityManager->getRepository(Group::class)->count(['parentId' => $group->getId()]) > 0) {
            throw new GroupNotDeletableException('has_children', 'Die Gruppe hat noch Untergruppen und kann nicht gelöscht werden. Lösche oder verschiebe zuerst die Untergruppen.');
        }

        // Only eMatChef's own mapping goes; the external MiData structure is never touched.
        foreach ($this->structureIdentities->findByGroup($group) as $mapping) {
            $this->entityManager->remove($mapping);
        }
        $this->entityManager->remove($group);
        $this->entityManager->flush();
    }
}
