<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\PrimaryDepartmentService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Primäres Department wechseln/entfernen gegen die echte Datenbank (partieller Unique-Index). Nur mit EMATCHEF_TEST_DB_URL
 * gegen eine isolierte Datenbank (`val_*` oder `*_test`); jeder Test endet mit Rollback.
 */
final class PrimaryDepartmentIntegrationTest extends TestCase
{
    /** @param callable(PrimaryDepartmentService, Connection, EntityManagerInterface): void $test */
    private function withDb(callable $test): void
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

        FreshKernel::run(function ($kernel) use ($test): void {
            $c = $kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $c->get('doctrine')->getManager();
            $conn = $em->getConnection();
            $conn->beginTransaction();
            try {
                $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('orgprim00001','Org',now(),now())");
                foreach (['deptprim0001', 'deptprim0002', 'deptprim0003', 'deptprim0004'] as $i => $id) {
                    $conn->executeStatement('INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES (?,?,?,now(),now(),false,false)', [$id, 'orgprim00001', 'Dept ' . $i]);
                }
                foreach (['uprim0000001', 'uprim0000002'] as $n => $uid) {
                    $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES (?,?, 'T','T','T','[\"ROLE_USER\"]',now(),now())", ['pprim000000' . $n, "p$n@example.org"]);
                    $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES (?,?, 'active','pw',true,now(),now())", [$uid, 'pprim000000' . $n]);
                }
                // uprim1: drei Mitgliedschaften, die erste primär; uprim2: fremder Benutzer mit eigenem Primär
                foreach ([['uprim0000001', 'deptprim0001', true], ['uprim0000001', 'deptprim0002', false], ['uprim0000001', 'deptprim0003', false], ['uprim0000002', 'deptprim0004', true]] as [$u, $d, $p]) {
                    $conn->executeStatement('INSERT INTO membership (user_id,department_id,role,is_primary) VALUES (?,?,?,?)', [$u, $d, 'u', $p ? 'true' : 'false'], [1 => \PDO::PARAM_STR, 2 => \PDO::PARAM_STR, 3 => \PDO::PARAM_STR, 4 => \PDO::PARAM_STR]);
                }
                $test($c->get(PrimaryDepartmentService::class), $conn, $em);
            } finally {
                $conn->rollBack();
            }
        }, $url, [PrimaryDepartmentService::class]);
    }

    /** @return list<string> */
    private static function primaries(Connection $conn, string $userId): array
    {
        return $conn->fetchFirstColumn('SELECT department_id FROM membership WHERE user_id=? AND is_primary ORDER BY department_id', [$userId]);
    }

    public function testChangingThePrimaryResetsTheOldOneAtomicallyAndKeepsAllMemberships(): void
    {
        $this->withDb(function (PrimaryDepartmentService $service, Connection $conn): void {
            // mehrfach hin und her: früher Unique-Verletzung (HTTP 500), wenn das neue vor dem alten UPDATE geschrieben wurde
            foreach (['deptprim0003', 'deptprim0002', 'deptprim0001'] as $target) {
                $result = $service->change('uprim0000001', $target);
                self::assertSame($target, $result['current']);
                self::assertSame([$target], self::primaries($conn, 'uprim0000001'));
            }
            self::assertSame(3, (int) $conn->fetchOne("SELECT count(*) FROM membership WHERE user_id='uprim0000001'"));
            // Gleiches Department erneut ist ein No-Op
            self::assertSame(['previous' => 'deptprim0001', 'current' => 'deptprim0001'], $service->change('uprim0000001', 'deptprim0001'));
            // fremder Benutzer bleibt unberührt
            self::assertSame(['deptprim0004'], self::primaries($conn, 'uprim0000002'));
        });
    }

    public function testPreviousPrimaryIsReportedForTheConfirmation(): void
    {
        $this->withDb(function (PrimaryDepartmentService $service): void {
            self::assertSame(['previous' => 'deptprim0001', 'current' => 'deptprim0002'], $service->change('uprim0000001', 'deptprim0002'));
        });
    }

    public function testPrimaryCanBeRemovedWithoutDeletingMemberships(): void
    {
        $this->withDb(function (PrimaryDepartmentService $service, Connection $conn): void {
            self::assertSame(['previous' => 'deptprim0001', 'current' => null], $service->change('uprim0000001', null));
            self::assertSame([], self::primaries($conn, 'uprim0000001'));
            self::assertSame(3, (int) $conn->fetchOne("SELECT count(*) FROM membership WHERE user_id='uprim0000001'"));
            self::assertSame(['previous' => null, 'current' => null], $service->change('uprim0000001', null));
        });
    }

    public function testDepartmentWithoutMembershipIsRejectedAndNothingChanges(): void
    {
        $this->withDb(function (PrimaryDepartmentService $service, Connection $conn): void {
            try {
                $service->change('uprim0000001', 'deptprim0004');
                self::fail('Erwartet: Ablehnung');
            } catch (\InvalidArgumentException) {
            }
            self::assertSame(['deptprim0001'], self::primaries($conn, 'uprim0000001'));
        });
    }
}
