<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Grossanlass\GrossanlassGmailAccountService;
use App\Service\Grossanlass\GrossanlassInquiryService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass/beschaffung/anfragen', name: 'api_grossanlass_inquiries_')]
class GrossanlassInquiryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassInquiryService $inquiries,
        private GrossanlassGmailAccountService $gmail,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $department, User $user) => $this->inquiries->list($department, $user));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->inquiries->create($department, $user, $data),
            201,
        );
    }

    #[Route('/from-tips', name: 'from_tips', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function fromTips(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $department, User $user) => $this->inquiries->importTips($department, $user));
    }

    #[Route('/mark-sent', name: 'mark_sent', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markSent(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];

        return $this->handle($departmentId, fn (Department $department, User $user) => $this->inquiries->markSent($department, $user, $ids));
    }

    #[Route('/create-drafts', name: 'create_drafts', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createDrafts(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->gmail->createDrafts($department, $user, $ids),
        );
    }

    #[Route('/sync-gmail', name: 'sync_gmail', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function syncGmail(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $department, User $user) => $this->gmail->syncInbox($department, $user));
    }

    #[Route('/unmatched', name: 'unmatched_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function unmatchedList(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $department, User $user) => $this->gmail->listUnmatched($department, $user));
    }

    #[Route('/unmatched/{unmatchedId}/assign', name: 'unmatched_assign', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unmatchedAssign(string $departmentId, string $unmatchedId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $inquiryId = (string) ($data['inquiry_id'] ?? '');

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->gmail->assignUnmatched($department, $user, $unmatchedId, $inquiryId),
        );
    }

    #[Route('/unmatched/{unmatchedId}/discard', name: 'unmatched_discard', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unmatchedDiscard(string $departmentId, string $unmatchedId): JsonResponse
    {
        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->gmail->discardUnmatched($department, $user, $unmatchedId),
        );
    }

    #[Route('/unmatched/{unmatchedId}/to-inquiry', name: 'unmatched_to_inquiry', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unmatchedToInquiry(string $departmentId, string $unmatchedId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->gmail->unmatchedToInquiry($department, $user, $unmatchedId, $data),
        );
    }

    #[Route('/{inquiryId}/reply-draft', name: 'reply_draft', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function replyDraft(string $departmentId, string $inquiryId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $kind = (string) ($data['kind'] ?? '');

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->gmail->createReplyDraft($department, $user, $inquiryId, $kind),
        );
    }

    #[Route('/{inquiryId}/reply', name: 'reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reply(string $departmentId, string $inquiryId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->inquiries->recordReply($department, $user, $inquiryId, $data),
        );
    }

    #[Route('/{inquiryId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $inquiryId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle(
            $departmentId,
            fn (Department $department, User $user) => $this->inquiries->update($department, $user, $inquiryId, $data),
        );
    }

    /**
     * @param callable(Department, User): mixed $fn
     */
    private function handle(string $departmentId, callable $fn, int $okStatus = 200): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        if (!$department->isGrossanlass()) {
            return new JsonResponse(['error' => 'Kein Grossanlass-Department'], 400);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($user->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }

        try {
            return new JsonResponse($fn($department, $user), $okStatus);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (GoogleOAuthException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'reason' => $e->reason], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }
}
