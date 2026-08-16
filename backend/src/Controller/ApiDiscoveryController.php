<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Öffentliche API-Startseite (wie API Platform bei eCamp): HTML im Browser, JSON für Clients.
 * Nicht indexieren — die Produktsuche gehört auf ematchef.ch.
 */
class ApiDiscoveryController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $appFrontendUrl,
        #[Autowire('%env(default::APP_MAIN_SITE_ORIGIN)%')]
        private string $mainSiteOrigin = '',
    ) {
    }

    #[Route('/robots.txt', name: 'api_robots', methods: ['GET', 'HEAD'])]
    public function robots(): Response
    {
        return new Response(
            "User-agent: *\nDisallow: /\n",
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'public, max-age=86400',
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
        );
    }

    #[Route('/', name: 'api_root', methods: ['GET', 'HEAD'])]
    public function root(Request $request): Response
    {
        $payload = $this->discoveryPayload($request);

        if ($this->wantsJson($request)) {
            return new JsonResponse($payload, Response::HTTP_OK, [
                'Cache-Control' => 'public, max-age=300',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        return new Response($this->htmlPage($payload), Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->query->get('format') === 'json') {
            return true;
        }
        $accept = $request->headers->get('Accept', '');
        if ($accept === '' || $accept === '*/*') {
            return false;
        }

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    /** @return array<string, mixed> */
    private function discoveryPayload(Request $request): array
    {
        $website = $this->originOr($this->mainSiteOrigin, 'https://ematchef.ch');
        $app = $this->originOr($this->appFrontendUrl, 'https://app.ematchef.ch');
        $api = $request->getSchemeAndHttpHost();

        return [
            'name' => 'eMatChef API',
            'description' => 'JSON-API für die eMatChef-App. Die öffentliche Website ist ematchef.ch.',
            '_links' => [
                'self' => ['href' => $api.'/', 'type' => 'text/html'],
                'self.json' => ['href' => $api.'/?format=json', 'type' => 'application/json'],
                'website' => ['href' => $website.'/'],
                'app' => ['href' => $app.'/'],
                'health' => ['href' => $api.'/api/health'],
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function htmlPage(array $payload): string
    {
        $links = $payload['_links'];
        $website = htmlspecialchars((string) $links['website']['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $app = htmlspecialchars((string) $links['app']['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $health = htmlspecialchars((string) $links['health']['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $json = htmlspecialchars((string) $links['self.json']['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = htmlspecialchars((string) $links['self']['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = htmlspecialchars((string) $payload['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = htmlspecialchars((string) $payload['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="de-CH">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>{$name}</title>
  <link rel="icon" href="{$website}favicon.svg" type="image/svg+xml">
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: system-ui, Segoe UI, Ubuntu, sans-serif;
      background: #f3f4f6;
      color: #111827;
    }
    header {
      background: #10b981;
      color: #fff;
      padding: 1.15rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.85rem;
    }
    .mark {
      width: 40px; height: 40px;
      border-radius: 10px;
      background: #059669;
      border: 1px solid rgba(255,255,255,.25);
      display: grid; place-items: center;
      font-weight: 800; letter-spacing: -.04em; font-size: 13px;
    }
    header h1 { margin: 0; font-size: 1.35rem; font-weight: 700; }
    header p { margin: .15rem 0 0; opacity: .9; font-size: .9rem; }
    main { max-width: 720px; margin: 0 auto; padding: 1.5rem; }
    section {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 1.1rem 1.25rem;
      margin-bottom: 1rem;
    }
    h2 { margin: 0 0 .7rem; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; }
    .formats a, .links a {
      display: inline-block;
      margin: 0 .4rem .4rem 0;
      padding: .35rem .7rem;
      border-radius: 999px;
      background: #ecfdf5;
      color: #047857;
      text-decoration: none;
      font-weight: 600;
      font-size: .9rem;
    }
    .formats a:hover, .links a:hover { background: #d1fae5; }
    table { width: 100%; border-collapse: collapse; font-size: .95rem; }
    th, td { text-align: left; padding: .45rem 0; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    th { width: 7.5rem; color: #6b7280; font-weight: 600; }
    td a { color: #047857; }
    .note { color: #6b7280; font-size: .9rem; line-height: 1.45; margin: 0; }
  </style>
</head>
<body>
  <header>
    <div class="mark" aria-hidden="true">EMC</div>
    <div>
      <h1>{$name}</h1>
      <p>{$description}</p>
    </div>
  </header>
  <main>
    <section>
      <h2>Available formats</h2>
      <div class="formats">
        <a href="{$html}">html</a>
        <a href="{$json}">json</a>
      </div>
    </section>
    <section>
      <h2>Links</h2>
      <table>
        <tr><th>Website</th><td><a href="{$website}">{$website}</a></td></tr>
        <tr><th>App</th><td><a href="{$app}">{$app}</a></td></tr>
        <tr><th>Health</th><td><a href="{$health}">{$health}</a></td></tr>
      </table>
    </section>
    <section>
      <p class="note">Geschützte Endpunkte unter <code>/api/…</code> brauchen eine Anmeldung. Diese Seite ist keine Produktsuche — bitte <a href="{$website}">ematchef.ch</a> verwenden.</p>
    </section>
  </main>
</body>
</html>
HTML;
    }

    private function originOr(string $raw, string $fallback): string
    {
        $value = trim($raw);
        if ($value === '') {
            return $fallback;
        }

        return rtrim($value, '/');
    }
}
