<?php

namespace App\Controller;

use App\Entity\InboxMessage;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\InboxMessageService;
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
        InboxMessage::WORKFLOW_OPEN,
        InboxMessage::WORKFLOW_IN_PROGRESS,
        InboxMessage::WORKFLOW_DONE,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
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

        return new JsonResponse([
            'unread_count' => $this->inboxMessages->countUnreadQrFound($departmentId),
        ]);
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

        $items = $this->inboxMessages->listQrFound($departmentId, $bucket, $limit);

        return new JsonResponse([
            'count' => count($items),
            'unread_count' => $this->inboxMessages->countUnreadQrFound($departmentId),
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

        $item = $this->inboxMessages->updateQrFoundStatus($departmentId, $id, InboxMessage::WORKFLOW_DONE, $currentUser);
        if ($item === null) {
            return new JsonResponse(['error' => 'Nachricht nicht gefunden'], 404);
        }

        return new JsonResponse(['ok' => true, 'item' => $item]);
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

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungueltiger JSON-Body'], 400);
        }
        $status = isset($data['status']) ? (string) $data['status'] : '';
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return new JsonResponse(['error' => 'status muss open, in_progress oder done sein'], 400);
        }

        $item = $this->inboxMessages->updateQrFoundStatus($departmentId, $id, $status, $currentUser);
        if ($item === null) {
            return new JsonResponse(['error' => 'Nachricht nicht gefunden'], 404);
        }

        return new JsonResponse(['ok' => true, 'item' => $item]);
    }
}
