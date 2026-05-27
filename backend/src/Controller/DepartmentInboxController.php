<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\ActivityUserNotificationService;
use App\Service\UserDirectMessageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/inbox', name: 'api_department_inbox_')]
class DepartmentInboxController extends AbstractController
{
    private const MANAGER_ROLES = ['mw', 'dc', 'org', 'sub', 'sa'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserDirectMessageService $directMessages,
        private ActivityUserNotificationService $activityUserNotifications,
    ) {}

    #[Route('/messages', name: 'messages_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listMessages(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->requireMember($departmentId);

        $bucket = strtolower(trim((string) $request->query->get('bucket', 'all')));
        if (!in_array($bucket, ['unread', 'read', 'all'], true)) {
            $bucket = 'all';
        }
        $limit = min(200, max(1, (int) $request->query->get('limit', 100)));

        $items = $this->directMessages->listInbox($departmentId, $user->getId(), $bucket, $limit);
        $unreadCount = $this->directMessages->countUnread($departmentId, $user->getId());

        return new JsonResponse([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    #[Route('/messages/sent', name: 'messages_sent_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listSentMessages(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->requireMember($departmentId);
        $limit = min(200, max(1, (int) $request->query->get('limit', 100)));
        $items = $this->directMessages->listSent($departmentId, $user->getId(), $limit);

        return new JsonResponse([
            'count' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/messages', name: 'messages_send', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sendMessage(string $departmentId, Request $request): JsonResponse
    {
        $sender = $this->requireMember($departmentId);
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungueltiger JSON-Body'], 400);
        }

        $recipientId = trim((string) ($data['recipient_user_id'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($recipientId === '') {
            return new JsonResponse(['error' => 'recipient_user_id ist erforderlich'], 400);
        }
        if ($subject === '') {
            return new JsonResponse(['error' => 'subject ist erforderlich'], 400);
        }
        if (mb_strlen($message) < 2) {
            return new JsonResponse(['error' => 'message ist zu kurz'], 400);
        }

        if ($recipientId === $sender->getId()) {
            return new JsonResponse(['error' => 'Du kannst dir selbst keine Nachricht senden'], 400);
        }

        $recipientMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $recipientId,
            'departmentId' => $departmentId,
        ]);
        if (!$recipientMembership) {
            return new JsonResponse(['error' => 'Empfaenger ist kein Mitglied dieses Departments'], 400);
        }

        $recipient = $this->entityManager->getRepository(User::class)->find($recipientId);
        if (!$recipient) {
            return new JsonResponse(['error' => 'Empfaenger nicht gefunden'], 404);
        }

        $entry = $this->directMessages->send($department, $sender, $recipient, $subject, $message);

        return new JsonResponse(['ok' => true, 'item' => $entry], 201);
    }

    #[Route('/messages/{messageId}/read', name: 'messages_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function markMessageRead(string $departmentId, string $messageId): JsonResponse
    {
        $user = $this->requireMember($departmentId);

        if (!$this->directMessages->markRead($departmentId, $user->getId(), $messageId)) {
            return new JsonResponse(['error' => 'Nachricht nicht gefunden'], 404);
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/activity-status', name: 'activity_status_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listActivityStatus(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->requireMember($departmentId);

        $bucket = strtolower(trim((string) $request->query->get('bucket', 'all')));
        if (!in_array($bucket, ['unread', 'read', 'all'], true)) {
            $bucket = 'all';
        }
        $limit = min(200, max(1, (int) $request->query->get('limit', 100)));

        $items = $this->activityUserNotifications->listInbox($departmentId, $user->getId(), $bucket, $limit);
        $unreadCount = $this->activityUserNotifications->countUnread($departmentId, $user->getId());

        return new JsonResponse([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    #[Route('/activity-status/{notificationId}/read', name: 'activity_status_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function markActivityStatusRead(string $departmentId, string $notificationId): JsonResponse
    {
        $user = $this->requireMember($departmentId);

        if (!$this->activityUserNotifications->markRead($departmentId, $user->getId(), $notificationId)) {
            return new JsonResponse(['error' => 'Nachricht nicht gefunden'], 404);
        }

        return new JsonResponse(['ok' => true]);
    }

    private function requireMember(string $departmentId): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Nicht authentifiziert');
        }

        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return $user;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership) {
            throw new AccessDeniedException('Keine Berechtigung für dieses Department');
        }

        return $user;
    }
}
