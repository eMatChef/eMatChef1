<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Workshop\WorkshopTicketPhotoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Werkstatt-Sicht: Foto-Upload am Ticket. Auslieferung über GET /media/… */
#[Route('/api/workshop/tickets', name: 'api_workshop_ticket_photos_')]
class WorkshopPhotoController extends AbstractController
{
    public function __construct(
        private WorkshopTicketPhotoService $ticketPhotoService,
    ) {
    }

    #[Route('/{ticketId}/photos', name: 'upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(string $ticketId, Request $request): JsonResponse
    {
        $file = $request->files->get('photo');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'photo ist erforderlich'], 400);
        }

        try {
            $photos = $this->ticketPhotoService->addPhoto($ticketId, $this->requireUser(), $file);

            return new JsonResponse(['photos' => $photos, 'message' => 'Foto hochgeladen']);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
