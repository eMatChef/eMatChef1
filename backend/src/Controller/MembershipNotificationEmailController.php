<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Service\AuditLogger;
use App\Service\MembershipNotificationEmailResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Eigene Benachrichtigungsadresse für ein Department (inkl. Grossanlass).
 * Nur die eigene Membership; Auswahl nur aus den eigenen verifizierten Adressen.
 */
#[Route('/api/departments/{departmentId}/my-notification-email', name: 'api_department_my_notification_email_')]
#[IsGranted('ROLE_USER')]
final class MembershipNotificationEmailController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MembershipNotificationEmailResolver $resolver,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function get(string $departmentId): JsonResponse
    {
        $membership = $this->ownMembership($departmentId);
        if ($membership instanceof JsonResponse) {
            return $membership;
        }

        return $this->payload($membership);
    }

    /** Body: {"email": "<eigene verifizierte Adresse>"} oder {"email": null} für die Hauptadresse. */
    #[Route('', name: 'set', methods: ['PUT'])]
    public function set(string $departmentId, Request $request): JsonResponse
    {
        $membership = $this->ownMembership($departmentId);
        if ($membership instanceof JsonResponse) {
            return $membership;
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data) || !\array_key_exists('email', $data) || ($data['email'] !== null && !\is_string($data['email']))) {
            return new JsonResponse(['error' => 'email ist erforderlich (Adresse oder null)'], 400);
        }

        $old = $membership->getNotificationEmail();
        try {
            $this->resolver->select($membership, $data['email']);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        if ($old !== $membership->getNotificationEmail()) {
            /** @var User $user */
            $user = $this->getUser();
            $this->auditLogger->log(
                'membership',
                AuditLogger::buildMembershipEntityId($user->getId(), $departmentId),
                'membership_notification_email_changed',
                $user,
                $user,
                $membership->getDepartment(),
                ['notification_email' => ['old' => $old, 'new' => $membership->getNotificationEmail()]]
            );
        }
        $this->entityManager->flush();

        return $this->payload($membership);
    }

    private function payload(Membership $membership): JsonResponse
    {
        $user = $membership->getUser();
        $effective = $this->resolver->effectiveEmail($membership);
        $primary = strtolower(trim((string) ($user->getProfile()?->getEmail() ?? '')));

        return new JsonResponse([
            'effective_email' => $effective,
            'primary_email' => $primary,
            // null = Hauptadresse verwenden (auch wenn eine gespeicherte Adresse nicht mehr gültig ist)
            'selected_email' => $effective !== $primary ? $effective : null,
            'options' => $this->resolver->selectableEmails($user),
        ]);
    }

    private function ownMembership(string $departmentId): Membership|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);

        return $membership instanceof Membership ? $membership : new JsonResponse(['error' => 'Keine Mitgliedschaft in diesem Department'], 404);
    }
}
