<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityItem;
use App\Entity\ActivityPackItem;
use App\Entity\ActivityReplenishmentWish;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class ActivityReplenishmentWishService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
    ) {}

    /**
     * @param array<string, mixed>|null $availabilitySnapshot
     */
    public function createWish(
        Activity $activity,
        User $requester,
        MaterialItem $material,
        int $quantity,
        ?string $notes,
        ?array $availabilitySnapshot,
    ): ActivityReplenishmentWish {
        $wish = new ActivityReplenishmentWish();
        $wish->setId(IdGenerator::generate13('rw'));
        $wish->setActivity($activity);
        $wish->setMaterialItem($material);
        $wish->setQuantityRequested(max(1, $quantity));
        $wish->setNotes($notes !== null && trim($notes) !== '' ? trim($notes) : null);
        $wish->setRequestedByUserId($requester->getId());
        $wish->setAvailabilitySnapshot($availabilitySnapshot);
        $this->entityManager->persist($wish);
        $this->entityManager->flush();

        $this->inboxMessages->notifyReplenishmentWishCreated($activity, $requester, $wish);

        return $wish;
    }

    public function rejectWish(ActivityReplenishmentWish $wish, User $decider, ?string $reason): void
    {
        $wish->setStatus(ActivityReplenishmentWish::STATUS_REJECTED);
        $wish->setDecidedByUserId($decider->getId());
        $wish->setDecidedAt(new \DateTime());
        $wish->setRejectionReason($reason !== null && trim($reason) !== '' ? trim($reason) : null);
        $wish->touch();
        $this->entityManager->flush();

        $this->inboxMessages->notifyReplenishmentWishDecided($wish->getActivity(), $decider, $wish, false);
    }

    public function fulfillWish(ActivityReplenishmentWish $wish, User $decider): ActivityItem
    {
        $activity = $wish->getActivity();
        $material = $wish->getMaterialItem();
        $qty = $wish->getQuantityRequested();

        $existing = $this->entityManager->getRepository(ActivityItem::class)->createQueryBuilder('ai')
            ->where('ai.activityId = :activityId')
            ->andWhere('ai.materialItemId = :materialId')
            ->andWhere('ai.isReplenishment = false')
            ->setParameter('activityId', $activity->getId())
            ->setParameter('materialId', $material->getId())
            ->orderBy('ai.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof ActivityItem) {
            $existing->setQuantity($existing->getQuantity() + $qty);
            $existing->setUpdatedAt(new \DateTime());
            $activityItem = $existing;
        } else {
            $activityItem = new ActivityItem();
            $activityItem->setId(IdGenerator::generate13('ai'));
            $activityItem->setActivity($activity);
            $activityItem->setMaterialItem($material);
            $activityItem->setQuantity($qty);
            $activityItem->setPriority('normal');
            $activityItem->setNotes($wish->getNotes());
            $activityItem->setIsConsumable($material->getIsConsumable());
            $activityItem->setIsReplenishment(false);
            $this->entityManager->persist($activityItem);
        }

        $this->syncPackItem($activity, $material, $qty);

        $wish->setStatus(ActivityReplenishmentWish::STATUS_FULFILLED);
        $wish->setDecidedByUserId($decider->getId());
        $wish->setDecidedAt(new \DateTime());
        $wish->setFulfilledActivityItemId($activityItem->getId());
        $wish->touch();
        $this->entityManager->flush();

        $this->inboxMessages->notifyReplenishmentWishDecided($activity, $decider, $wish, true);

        return $activityItem;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeWish(ActivityReplenishmentWish $wish): array
    {
        $material = $wish->getMaterialItem();
        $profile = null;
        $requester = $this->entityManager->getRepository(User::class)->find($wish->getRequestedByUserId());
        if ($requester !== null) {
            $profile = $requester->getProfile();
        }

        return [
            'id' => $wish->getId(),
            'activity_id' => $wish->getActivityId(),
            'material_item_id' => $wish->getMaterialItemId(),
            'material_name' => $material->getName(),
            'quantity_requested' => $wish->getQuantityRequested(),
            'notes' => $wish->getNotes(),
            'status' => $wish->getStatus(),
            'requested_by_user_id' => $wish->getRequestedByUserId(),
            'requested_by_name' => $profile ? $profile->getDisplayName() : null,
            'requested_at' => $wish->getRequestedAt()->format(\DateTimeInterface::ATOM),
            'decided_by_user_id' => $wish->getDecidedByUserId(),
            'decided_at' => $wish->getDecidedAt()?->format(\DateTimeInterface::ATOM),
            'rejection_reason' => $wish->getRejectionReason(),
            'fulfilled_activity_item_id' => $wish->getFulfilledActivityItemId(),
            'availability_snapshot' => $wish->getAvailabilitySnapshot(),
        ];
    }

    private function syncPackItem(Activity $activity, MaterialItem $material, int $addQty): void
    {
        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
            'activityId' => $activity->getId(),
            'materialItemId' => $material->getId(),
        ]);

        if ($packItem === null) {
            $packItem = new ActivityPackItem();
            $packItem->setId(IdGenerator::generate13('pk'));
            $packItem->setActivity($activity);
            $packItem->setMaterialItem($material);
            $packItem->setQuantityOrdered($addQty);
            $this->entityManager->persist($packItem);
            return;
        }

        $packItem->setQuantityOrdered($packItem->getQuantityOrdered() + $addQty);
    }
}
