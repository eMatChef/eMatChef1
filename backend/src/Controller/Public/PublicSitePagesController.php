<?php

namespace App\Controller\Public;

use App\Repository\SitePageRepository;
use App\Service\SitePageContentDefaults;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/site-pages', name: 'api_public_site_pages_')]
class PublicSitePagesController extends AbstractController
{
    public function __construct(
        private SitePageRepository $sitePageRepository,
        private SitePageContentDefaults $defaults
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $defaultMap = $this->defaults->allDefaults();
        $rows = $this->sitePageRepository->findAll();
        $bySlug = [];
        foreach ($rows as $row) {
            $bySlug[$row->getSlug()] = $row;
        }

        $pages = [];
        foreach ($defaultMap as $slug => $defaultContent) {
            if (isset($bySlug[$slug])) {
                $entity = $bySlug[$slug];
                $pages[] = [
                    'slug' => $slug,
                    'content' => $entity->getContent(),
                    'updatedAt' => $entity->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                ];
            } else {
                $pages[] = [
                    'slug' => $slug,
                    'content' => $defaultContent,
                    'updatedAt' => null,
                ];
            }
        }

        return $this->json(['pages' => $pages]);
    }
}
