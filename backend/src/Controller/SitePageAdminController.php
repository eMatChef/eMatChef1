<?php

namespace App\Controller;

use App\Entity\SitePage;
use App\Entity\User;
use App\Repository\SitePageRepository;
use App\Service\SitePageContentDefaults;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/site-pages', name: 'api_admin_site_pages_')]
class SitePageAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SitePageRepository $sitePageRepository,
        private SitePageContentDefaults $defaults
    ) {
    }

    #[Route('/{slug}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $slug): JsonResponse
    {
        if (!$this->canEditSite()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        if (!$this->isAllowedSlug($slug)) {
            return new JsonResponse(['error' => 'Unknown slug'], Response::HTTP_NOT_FOUND);
        }

        $entity = $this->sitePageRepository->findOneBySlug($slug);
        $content = $entity ? $entity->getContent() : $this->defaults->forSlug($slug);
        $updatedAt = $entity ? $entity->getUpdatedAt()->format(\DateTimeInterface::ATOM) : null;

        return $this->json([
            'slug' => $slug,
            'content' => $content,
            'updatedAt' => $updatedAt,
        ]);
    }

    #[Route('/{slug}', name: 'put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function put(string $slug, Request $request): JsonResponse
    {
        if (!$this->canEditSite()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        if (!$this->isAllowedSlug($slug)) {
            return new JsonResponse(['error' => 'Unknown slug'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !\array_key_exists('content', $payload)) {
            return new JsonResponse(['error' => 'Expected JSON with "content"'], Response::HTTP_BAD_REQUEST);
        }
        $content = $payload['content'];
        if (!\is_array($content)) {
            return new JsonResponse(['error' => 'content must be an object'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $now = new \DateTimeImmutable();
        $entity = $this->sitePageRepository->findOneBySlug($slug);
        if (!$entity) {
            $entity = new SitePage($slug);
            $this->em->persist($entity);
        }
        $entity->setContent($content);
        $entity->setUpdatedAt($now);
        $entity->setUpdatedBy($user);
        $this->em->flush();

        return $this->json([
            'slug' => $slug,
            'content' => $entity->getContent(),
            'updatedAt' => $entity->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function canEditSite(): bool
    {
        return $this->isGranted('ROLE_SUPERADMIN') || $this->isGranted('ROLE_WEBADMIN');
    }

    private function isAllowedSlug(string $slug): bool
    {
        return \in_array($slug, $this->defaults->allowedSlugs(), true);
    }
}
