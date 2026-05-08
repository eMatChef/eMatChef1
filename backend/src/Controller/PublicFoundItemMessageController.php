<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\PublicFoundItemMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/public-found-messages', name: 'api_public_found_messages_')]
class PublicFoundItemMessageController extends AbstractController
{
    private const DEPARTMENT_MANAGER_ROLES = ['mw', 'dc', 'org', 'sub', 'sa'];

    /** @var string[] */
    private const ALLOWED_STATUSES = [
        PublicFoundItemMessage::STATUS_OPEN,
        PublicFoundItemMessage::STATUS_IN_PROGRESS,
        PublicFoundItemMessage::STATUS_DONE,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    private function assertDepartmentManager(User $user, string $departmentId): void
    {
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership || !in_array($membership->getRole(), self::DEPARTMENT_MANAGER_ROLES, true)) {
            throw new AccessDeniedException('Keine Berechtigung für dieses Department');
        }
    }

    #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function unreadCount(string $departmentId): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        try {
            $this->assertDepartmentManager($currentUser, $departmentId);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $deptRef = $this->entityManager->getReference(Department::class, $departmentId);
        // Nur „neu“ (open) zählt für Glocke; in_progress gilt als in der Zentrale gesehen.
        $unreadCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(PublicFoundItemMessage::class, 'm')
            ->where('m.department = :dept')
            ->andWhere('m.status = :open')
            ->setParameter('dept', $deptRef)
            ->setParameter('open', PublicFoundItemMessage::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse(['unread_count' => $unreadCount]);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        try {
            $this->assertDepartmentManager($currentUser, $departmentId);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $bucketParam = $request->query->get('bucket');
        if ($bucketParam !== null && is_string($bucketParam) && in_array($bucketParam, ['open', 'active', 'done', 'all'], true)) {
            $bucket = $bucketParam;
        } else {
            $unreadOnly = filter_var($request->query->get('unread_only', '1'), FILTER_VALIDATE_BOOLEAN);
            $bucket = $unreadOnly ? 'active' : 'all';
        }

        $limit = (int) $request->query->get('limit', 50);
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $deptRef = $this->entityManager->getReference(Department::class, $departmentId);
        $qb = $this->entityManager->getRepository(PublicFoundItemMessage::class)
            ->createQueryBuilder('m')
            ->where('m.department = :dept')
            ->setParameter('dept', $deptRef)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($bucket === 'open') {
            $qb->andWhere('m.status = :open')
                ->setParameter('open', PublicFoundItemMessage::STATUS_OPEN);
        } elseif ($bucket === 'active') {
            $qb->andWhere('m.status IN (:st)')
                ->setParameter('st', [PublicFoundItemMessage::STATUS_OPEN, PublicFoundItemMessage::STATUS_IN_PROGRESS]);
        } elseif ($bucket === 'done') {
            $qb->andWhere('m.status = :done')
                ->setParameter('done', PublicFoundItemMessage::STATUS_DONE);
        }

        /** @var PublicFoundItemMessage[] $rows */
        $rows = $qb->getQuery()->getResult();
        $items = array_map(fn (PublicFoundItemMessage $m) => $this->serializeMessage($m), $rows);

        $unreadTotal = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m2.id)')
            ->from(PublicFoundItemMessage::class, 'm2')
            ->where('m2.department = :dept')
            ->andWhere('m2.status = :open')
            ->setParameter('dept', $deptRef)
            ->setParameter('open', PublicFoundItemMessage::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'count' => count($items),
            'unread_count' => $unreadTotal,
            'items' => $items,
        ]);
    }

    #[Route('/{id}/read', name: 'mark_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function markRead(string $departmentId, string $id): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        try {
            $this->assertDepartmentManager($currentUser, $departmentId);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $msg = $this->findMessage($departmentId, $id);
        if (!$msg) {
            return new JsonResponse(['error' => 'Nachricht nicht gefunden'], 404);
        }

        $this->applyStatus($msg, PublicFoundItemMessage::STATUS_DONE, $currentUser);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true, 'item' => $this->serializeMessage($msg)]);
    }

    #[Route('/{id}', name: 'patch_status', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function patchStatus(string $departmentId, string $id, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        try {
            $this->assertDepartmentManager($currentUser, $departmentId);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $msg = $this->findMessage($departmentId, $id);
        if (!$msg) {
            return new JsonResponse(['error' => 'Nachricht nicht gefunden'], 404);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungueltiger JSON-Body'], 400);
        }
        $status = isset($data['status']) ? (string) $data['status'] : '';
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return new JsonResponse(['error' => 'status muss open, in_progress oder done sein'], 400);
        }

        $this->applyStatus($msg, $status, $currentUser);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true, 'item' => $this->serializeMessage($msg)]);
    }

    private function findMessage(string $departmentId, string $id): ?PublicFoundItemMessage
    {
        $deptRef = $this->entityManager->getReference(Department::class, $departmentId);

        /** @var PublicFoundItemMessage|null $msg */
        $msg = $this->entityManager->getRepository(PublicFoundItemMessage::class)->findOneBy([
            'id' => $id,
            'department' => $deptRef,
        ]);

        return $msg;
    }

    private function applyStatus(PublicFoundItemMessage $msg, string $newStatus, User $user): void
    {
        $msg->setStatus($newStatus);
        if ($newStatus === PublicFoundItemMessage::STATUS_DONE) {
            if ($msg->getReadAt() === null) {
                $msg->setReadAt(new \DateTime());
                $msg->setReadByUserId($user->getId());
            }
        } else {
            $msg->setReadAt(null);
            $msg->setReadByUserId(null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(PublicFoundItemMessage $m): array
    {
        return [
            'id' => $m->getId(),
            'entity_type' => $m->getEntityType(),
            'material_id' => $m->getMaterialId(),
            'batch_id' => $m->getBatchId(),
            'public_code' => $m->getPublicCode(),
            'material_name' => $m->getMaterialName(),
            'department_name' => $m->getDepartmentName(),
            'serial_line' => $m->getSerialLine(),
            'message' => $m->getMessage(),
            'sender_name' => $m->getSenderName(),
            'sender_email' => $m->getSenderEmail(),
            'public_url' => $m->getPublicUrl(),
            'status' => $m->getStatus(),
            'created_at' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'read_at' => $m->getReadAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
