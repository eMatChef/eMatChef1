<?php

namespace App\Controller\Public;

use App\Service\PublicSitemapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public', name: 'api_public_')]
class PublicSitemapController extends AbstractController
{
    public function __construct(
        private PublicSitemapService $sitemapService,
        #[Autowire('%env(default::APP_MAIN_SITE_ORIGIN)%')]
        private string $mainSiteOrigin = '',
    ) {
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $origin = trim($this->mainSiteOrigin);
        if ($origin === '') {
            $origin = 'https://ematchef.ch';
        }

        return new Response(
            $this->sitemapService->toXml($origin),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ],
        );
    }
}
