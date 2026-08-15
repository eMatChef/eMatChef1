<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Media\MediaFileAccessService;
use App\Service\Media\MediaStorageService;
use App\Service\Workshop\WorkshopPhotoAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** GET /media/{departmentId}/{photos|documents}/{folder}/{contextId}/{filename} */
class MediaFileController extends AbstractController
{
    public function __construct(
        private MediaStorageService $mediaStorage,
        private MediaFileAccessService $mediaFileAccess,
        private WorkshopPhotoAccessService $workshopPhotoAccess,
    ) {
    }

    #[Route(
        '/media/{departmentId}/{kind}/{folder}/{contextId}/{filename}',
        name: 'media_file_show',
        methods: ['GET'],
        requirements: [
            'departmentId' => '[A-Za-z0-9_-]+',
            'kind' => 'photos|documents',
            'folder' => '[A-Za-z0-9_-]+',
            'contextId' => '[A-Za-z0-9_-]+',
            'filename' => '[A-Za-z0-9._-]+',
        ],
    )]
    #[IsGranted('ROLE_USER')]
    public function show(
        string $departmentId,
        string $kind,
        string $folder,
        string $contextId,
        string $filename,
    ): Response {
        try {
            $context = $this->mediaStorage->contextFromKindAndFolder($kind, $folder);
            $user = $this->requireUser();
            $this->mediaFileAccess->assertCanView($user, $context, $departmentId, $contextId);

            if ($context === MediaStorageService::CONTEXT_WORKSHOP_TICKET) {
                $ticket = $this->workshopPhotoAccess->requireTicketById($contextId);
                $legacyCompanyId = $this->workshopPhotoAccess->resolveLegacySupplierCompanyIdForPhoto($ticket, $filename);
                $path = $this->mediaStorage->resolveWorkshopTicketFilePath(
                    $departmentId,
                    $contextId,
                    $filename,
                    $legacyCompanyId,
                );
            } else {
                $path = $this->mediaStorage->resolveStoredFilePath(
                    $context,
                    $departmentId,
                    $contextId,
                    $filename,
                );
            }

            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
            $response->setPrivate();
            $response->setMaxAge(86400);

            return $response;
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
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
