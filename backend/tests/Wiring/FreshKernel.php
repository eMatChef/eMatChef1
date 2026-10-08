<?php

declare(strict_types=1);

namespace App\Tests\Wiring;

use App\Kernel;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Wiring-Tests booten einen Kernel mit eigenem, frischem Cache-Verzeichnis. Ein gemeinsames `var/cache/test`
 * mit `debug=false` bleibt sonst veraltet, wenn neue Services/Commands hinzukommen (stale Container).
 * Es wird keine DB-Verbindung aufgebaut; der Container wird nur kompiliert.
 */
final class FreshKernel
{
    /** @param callable(Kernel): void $assertions */
    public static function run(callable $assertions): void
    {
        $dir = sys_get_temp_dir() . '/ematchef-wiring-' . bin2hex(random_bytes(6));
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'postgresql://wiring:wiring@127.0.0.1:5432/wiring?serverVersion=16';

        $kernel = new class('test', false, $dir) extends Kernel {
            public function __construct(string $environment, bool $debug, private string $dir)
            {
                parent::__construct($environment, $debug);
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
