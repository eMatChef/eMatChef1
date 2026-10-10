<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Gemeinsame Basis für Integrationstests, die einen EntityManager gegen eine ISOLIERTE Test-Datenbank brauchen.
 * Ohne EMATCHEF_TEST_DB_URL wird der Test übersprungen (nicht Teil der CI-Unit-Tests). Der Datenbankname muss mit
 * `val_` beginnen oder auf `_test` enden, nie `mvdb`. Jeder Test läuft in einer Transaktion, die zurückgerollt wird.
 */
trait IsolatedDatabase
{
    private ?Kernel $isolatedKernel = null;

    /** @param list<string> $departmentIds Departments, die der Test als vorhanden voraussetzt */
    private function isolatedEntityManager(array $departmentIds = []): EntityManagerInterface
    {
        $url = getenv('EMATCHEF_TEST_DB_URL') ?: '';
        if ($url === '') {
            self::markTestSkipped('EMATCHEF_TEST_DB_URL nicht gesetzt (Integrationstest gegen isolierte DB).');
        }
        $dbName = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        if (!preg_match('/^val_|_test$/', $dbName) || $dbName === 'mvdb') {
            self::fail('Unsicherer Datenbankname: ' . $dbName);
        }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $url;
        (new Dotenv())->usePutenv(false)->loadEnv(dirname(__DIR__, 2) . '/.env', 'APP_ENV', 'test', ['test']);
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $url;
        foreach (['APP_FRONTEND_URL', 'APP_MAIN_SITE_ORIGIN'] as $name) {
            if (!isset($_ENV[$name]) && !isset($_SERVER[$name]) && getenv($name) === false) {
                $_ENV[$name] = $_SERVER[$name] = 'http://wiring.invalid';
            }
        }

        // Eigenes Cache-Verzeichnis (ein Lauf, von allen Tests geteilt): ein gemeinsames `var/cache/test` bleibt sonst
        // veraltet (siehe FreshKernel); ein frischer Container je Test würde Zeit und Speicher kosten.
        static $dir = null;
        if ($dir === null) {
            $dir = sys_get_temp_dir() . '/ematchef-itest-' . bin2hex(random_bytes(6));
            register_shutdown_function(static fn () => (new Filesystem())->remove($dir));
        }
        $this->isolatedKernel = new class('test', false, $dir) extends Kernel {
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
        $this->isolatedKernel->boot();

        /** @var EntityManagerInterface $em */
        $em = $this->isolatedKernel->getContainer()->get('doctrine')->getManager();
        $conn = $em->getConnection();
        $conn->beginTransaction();
        $this->seedDepartments($conn, $departmentIds);

        return $em;
    }

    private function releaseIsolatedDatabase(?EntityManagerInterface $em): void
    {
        if ($em !== null && $em->getConnection()->isTransactionActive()) {
            $em->getConnection()->rollBack();
        }
        $this->isolatedKernel?->shutdown();
        $this->isolatedKernel = null;
    }

    /** @param list<string> $departmentIds */
    private function seedDepartments(Connection $conn, array $departmentIds): void
    {
        if ($departmentIds === []) {
            return;
        }
        $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('orgitest0001','Org',now(),now())");
        foreach ($departmentIds as $id) {
            $conn->executeStatement(
                'INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES (?,?,?,now(),now(),false,false)',
                [$id, 'orgitest0001', 'Test ' . $id],
            );
        }
    }

    /** Verantwortliche (dc, mw) eines Departments, wie sie Freigabe-Erinnerungen erhalten. */
    private function seedManagers(Connection $conn, string $departmentId): void
    {
        foreach (['dc' => 1, 'mw' => 2] as $role => $n) {
            $uid = 'uitest00000' . $n;
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES (?,?, 'T','T','T','[\"ROLE_USER\"]',now(),now())", ['pitest00000' . $n, "m$n@example.org"]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES (?,?, 'active','pw',true,now(),now())", [$uid, 'pitest00000' . $n]);
            $conn->executeStatement('INSERT INTO membership (user_id,department_id,role,is_primary) VALUES (?,?,?,false)', [$uid, $departmentId, $role]);
        }
    }
}
