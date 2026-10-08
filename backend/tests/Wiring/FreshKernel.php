<?php

declare(strict_types=1);

namespace App\Tests\Wiring;

use App\Kernel;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Wiring-Tests booten einen Kernel mit eigenem, frischem Cache-Verzeichnis. Ein gemeinsames `var/cache/test`
 * mit `debug=false` bleibt sonst veraltet, wenn neue Services/Commands hinzukommen (stale Container).
 * Es wird keine DB-Verbindung aufgebaut und kein Service instanziiert; der Container wird nur kompiliert
 * und die Tag-Zuordnung als Parameter ausgelesen.
 */
final class FreshKernel
{
    /**
     * @param callable(Kernel): void $assertions
     * @param string|null            $databaseUrl    echte, isolierte Test-DB (nur für Integrationstests); sonst Platzhalter
     * @param list<string>           $publicServices Service-IDs, die für den Test öffentlich zugänglich gemacht werden
     */
    public static function run(callable $assertions, ?string $databaseUrl = null, array $publicServices = []): void
    {
        $dir = sys_get_temp_dir() . '/ematchef-wiring-' . bin2hex(random_bytes(6));
        // Platzhalter nur für Variablen, die der Container beim Instanziieren auflöst; es wird nichts verbunden.
        foreach ([
            'DATABASE_URL' => 'postgresql://wiring:wiring@127.0.0.1:5432/wiring?serverVersion=16',
            'APP_FRONTEND_URL' => 'http://wiring.invalid',
            'APP_MAIN_SITE_ORIGIN' => 'http://wiring.invalid',
        ] as $name => $value) {
            if ($name === 'DATABASE_URL') {
                $_ENV[$name] = $_SERVER[$name] = $databaseUrl ?? $value;
                continue;
            }
            if ((!isset($_ENV[$name]) && !isset($_SERVER[$name]) && getenv($name) === false)) {
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }

        $kernel = new class('test', false, $dir, $publicServices) extends Kernel {
            /** @param list<string> $publicServices */
            public function __construct(string $environment, bool $debug, private string $dir, private array $publicServices = [])
            {
                parent::__construct($environment, $debug);
            }

            /** Erfasst die Service-IDs je Tag als Parameter `wiring.tagged.<tag>` (ohne Services zu instanziieren). */
            protected function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
            {
                parent::build($container);
                $container->setParameter('wiring.public_services', $this->publicServices);
                $container->addCompilerPass(new class implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
                    public function process(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
                    {
                        foreach ($container->getParameter('wiring.public_services') as $id) {
                            $container->getDefinition($id)->setPublic(true);
                        }
                        foreach (['app.demo_scenario', 'app.clock_origin_resolver'] as $tag) {
                            $container->setParameter('wiring.tagged.' . $tag, array_keys($container->findTaggedServiceIds($tag)));
                        }
                    }
                }, \Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_REMOVING);
            }

            public function getCacheDir(): string
            {
                return $this->dir . '/cache';
            }

            public function getLogDir(): string
            {
                return $this->dir . '/log';
            }
        };
        try {
            $kernel->boot();
            $assertions($kernel);
        } finally {
            $kernel->shutdown();
            (new Filesystem())->remove($dir);
        }
    }
}
