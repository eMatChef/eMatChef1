<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Interaktive API-Dokumentation (Swagger UI) für die lokale Entwicklung.
 *
 * Nur aktiv mit API_DOCS_ENABLED=1 (Standard: aus; lokal per docker-compose.override gesetzt),
 * sonst 404. Die Swagger-UI-Assets stammen aus api-platform/core (keine zusätzliche Abhängigkeit).
 * Die OpenAPI-Beschreibung wird aus den Symfony-Routen der Controller erzeugt, weil eMatChef
 * keine API-Platform-Ressourcen verwendet. Authentifizierung: bestehendes BEARER-Cookie.
 */
class ApiDocsController extends AbstractController
{
    private const ASSETS = [
        'swagger-ui.css' => ['swagger-ui/swagger-ui.css', 'text/css'],
        'swagger-ui-bundle.js' => ['swagger-ui/swagger-ui-bundle.js', 'application/javascript'],
    ];

    public function __construct(
        private readonly RouterInterface $router,
        #[Autowire('%env(bool:default::API_DOCS_ENABLED)%')]
        private readonly bool $enabled,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private readonly string $appFrontendUrl,
    ) {
    }

    #[Route('/api/doc', name: 'api_docs_ui', methods: ['GET', 'HEAD'])]
    public function ui(): Response
    {
        $this->assertEnabled();

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="de-CH">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>eMatChef API – Dokumentation</title>
  <link rel="stylesheet" href="/api/doc/swagger-ui.css">
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="/api/doc/swagger-ui-bundle.js"></script>
  <script src="/api/doc/init.js"></script>
</body>
</html>
HTML;

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    #[Route('/api/doc/init.js', name: 'api_docs_init', methods: ['GET'])]
    public function init(): Response
    {
        $this->assertEnabled();

        $js = <<<'JS'
window.ui = SwaggerUIBundle({
  url: '/api/doc.json',
  dom_id: '#swagger-ui',
  deepLinking: true,
  tryItOutEnabled: true,
  // Das HttpOnly-Cookie BEARER (Login der App) wird bei jeder Anfrage mitgeschickt.
  requestInterceptor: function (req) {
    req.credentials = 'include';
    return req;
  },
});
JS;

        return new Response($js, Response::HTTP_OK, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    #[Route('/api/doc/{file}', name: 'api_docs_asset', requirements: ['file' => 'swagger-ui\.css|swagger-ui-bundle\.js'], methods: ['GET'])]
    public function asset(string $file): Response
    {
        $this->assertEnabled();

        [$relative, $type] = self::ASSETS[$file];
        $path = $this->projectDir.'/vendor/api-platform/core/src/Symfony/Bundle/Resources/public/'.$relative;
        if (!is_file($path)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $type);

        return $response;
    }

    #[Route('/api/doc.json', name: 'api_docs_spec', methods: ['GET'])]
    public function spec(): JsonResponse
    {
        $this->assertEnabled();

        $paths = [];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            $controller = (string) $route->getDefault('_controller');
            if (!str_starts_with($path, '/api/') || !str_starts_with($controller, 'App\\Controller\\')) {
                continue;
            }
            if (str_starts_with($path, '/api/doc')) {
                continue;
            }

            $methods = array_values(array_diff($route->getMethods() ?: ['GET'], ['HEAD', 'OPTIONS']));
            foreach ($methods as $method) {
                $paths[$path][strtolower($method)] = $this->operation($name, $path, $method, $controller);
            }
        }
        ksort($paths);

        // Der Login ist ein Firewall-Check-Pfad ohne eigenen Controller.
        $paths['/api/auth/login_check']['post'] ??= $this->operation('api_auth_login_check', '/api/auth/login_check', 'POST', 'Auth\\Login');

        $appUrl = rtrim($this->appFrontendUrl, '/');

        return new JsonResponse([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'eMatChef API',
                'version' => 'local',
                'description' => "Lokale, automatisch aus den Symfony-Routen erzeugte Übersicht (Pfade, Methoden, Pfadparameter). "
                    ."Request-/Response-Schemas sind nicht beschrieben.\n\n"
                    ."**Anmeldung:** Im Browser unter {$appUrl}/ anmelden. Das HttpOnly-Cookie `BEARER` gilt für alle "
                    ."`*.ematchef.test`-Hosts und wird von «Try it out» automatisch mitgesendet. "
                    ."Alternativ `POST /api/auth/login_check` hier ausführen. Rollen und Berechtigungen gelten unverändert.",
            ],
            // Relativ: hinter dem TLS-Proxy kennt PHP das Schema https nicht (Mixed Content vermeiden).
            'servers' => [['url' => '/']],
            'security' => [['cookieAuth' => []], ['bearerAuth' => []]],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'cookieAuth' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'BEARER', 'description' => 'Wird vom Login der App gesetzt (HttpOnly, nicht manuell eintragbar).'],
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
                ],
            ],
        ], Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    /** @return array<string, mixed> */
    private function operation(string $name, string $path, string $method, string $controller): array
    {
        $short = preg_replace('/Controller(::.*)?$/', '', substr($controller, (int) strrpos($controller, '\\') + 1));

        $parameters = [];
        if (preg_match_all('/\{(\w+)\}/', $path, $matches)) {
            foreach ($matches[1] as $param) {
                $parameters[] = ['name' => $param, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
            }
        }

        $operation = [
            'tags' => [$short ?: 'Api'],
            'summary' => $name,
            'operationId' => $name.'_'.strtolower($method),
            'parameters' => $parameters,
            'responses' => [
                '200' => ['description' => 'Erfolg'],
                '401' => ['description' => 'Nicht angemeldet'],
                '403' => ['description' => 'Keine Berechtigung'],
            ],
        ];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = [
                'content' => ['application/json' => ['schema' => ['type' => 'object']]],
            ];
        }

        return $operation;
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }
    }
}
