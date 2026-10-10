<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\User;
use App\EventSubscriber\GrossanlassSetupGateSubscriber;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Grossanlass\GrossanlassPlanungService;
use App\Service\Grossanlass\GrossanlassSetupIncompleteException;
use App\Service\Grossanlass\GrossanlassSetupService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Ersteinrichtung und serverseitige Sperre eines Grossanlasses gegen die echte Datenbank. Nur mit EMATCHEF_TEST_DB_URL
 * gegen eine isolierte Datenbank (`val_*` oder `*_test`); jeder Test endet mit Rollback.
 */
final class GrossanlassSetupIntegrationTest extends TestCase
{
    private const GA = 'deptsetup001';
    private const GUEST = 'deptsetup002';

    /** @param callable(object): void $test */
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
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
            try {
                $this->seed($conn);
                $ctx = new class($em, $conn, $kernel, $c->get(GrossanlassSetupService::class), $c->get(GrossanlassAccessService::class), $c->get(AdminCapabilityChecker::class), $c->get(GrossanlassPlanungService::class)) {
                    public function __construct(
                        public EntityManagerInterface $em,
                        public Connection $conn,
                        public KernelInterface $kernel,
                        public GrossanlassSetupService $setup,
                        public GrossanlassAccessService $access,
                        public AdminCapabilityChecker $admin,
                        public GrossanlassPlanungService $planung,
                    ) {
                    }

                    public function dept(): Department
                    {
                        $this->em->clear();
                        $d = $this->em->find(Department::class, GrossanlassSetupIntegrationTest::gaId());
                        TestCase::assertInstanceOf(Department::class, $d);

                        return $d;
                    }

                    public function user(string $id): User
                    {
                        $u = $this->em->find(User::class, $id);
                        TestCase::assertInstanceOf(User::class, $u);

                        return $u;
                    }

                    /** Antwort der Sperre für diese Anfrage oder null, wenn sie durchgelassen wird. */
                    public function gate(?string $userId, string $method, string $path): ?int
                    {
                        $tokens = new TokenStorage();
                        if ($userId !== null) {
                            $user = $this->user($userId);
                            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
                        }
                        $subscriber = new GrossanlassSetupGateSubscriber($this->em, $tokens, $this->access, $this->admin);
                        $event = new RequestEvent($this->kernel, Request::create($path, $method), HttpKernelInterface::MAIN_REQUEST);
                        $subscriber->onRequest($event);

                        return $event->getResponse()?->getStatusCode();
                    }
                };
                $test($ctx);
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassSetupService::class, GrossanlassAccessService::class, AdminCapabilityChecker::class, GrossanlassPlanungService::class]);
    }

    public static function gaId(): string
    {
        return self::GA;
    }

    private function seed(Connection $conn): void
    {
        $conn->executeStatement("INSERT INTO organisation (id,name,created_at,updated_at) VALUES ('orgsetup0001','Org',now(),now())");
        foreach ([[self::GA, 'Setup GA', 'true'], [self::GUEST, 'Gast Abteilung', 'false']] as [$id, $name, $ga]) {
            $conn->executeStatement("INSERT INTO department (id,organisation_id,name,created_at,updated_at,demo_mode,is_grossanlass) VALUES (?,?,?,now(),now(),false,$ga)", [$id, 'orgsetup0001', $name]);
        }
        $conn->executeStatement("INSERT INTO department_grossanlass_config (department_id,planned_event_start,location_text,notes,guest_activity_type) VALUES (?, '2027-06-01', '', '', 'camp')", [self::GA]);
        $users = [['usetmw000001', 'mw'], ['usetcmw00001', 'cmw'], ['usetdc000001', 'dc'], ['usetbl000001', 'bl'], ['usetlw000001', 'lw'], ['usethelp0001', 'u']];
        foreach ($users as $n => [$uid, $role]) {
            $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES (?,?, 'T','T','T','[\"ROLE_USER\"]',now(),now())", ['psetup00000' . $n, "s$n@example.org"]);
            $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES (?,?, 'active','pw',true,now(),now())", [$uid, 'psetup00000' . $n]);
            $conn->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES (?,?,?,false)", [$uid, self::GA, $role]);
        }
        // Benutzer ohne Mitgliedschaft im GA und Superadmin
        $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('psetupout001','out@example.org','T','T','T','[\"ROLE_USER\"]',now(),now())");
        $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('usetout00001','psetupout001','active','pw',true,now(),now())");
        $conn->executeStatement("INSERT INTO profile (id,email,first_name,last_name,nickname,roles,created_at,updated_at) VALUES ('psetupsa0001','sa@example.org','T','T','T','[\"ROLE_USER\",\"ROLE_SUPERADMIN\"]',now(),now())");
        $conn->executeStatement("INSERT INTO \"user\" (id,profile_id,state,password,email_verified,created_at,updated_at) VALUES ('usetsa000001','psetupsa0001','active','pw',true,now(),now())");
    }

    private function complete(object $c): void
    {
        $c->conn->executeStatement("UPDATE department_grossanlass_config SET location_text='Festplatz Nord' WHERE department_id=?", [self::GA]);
        $c->conn->executeStatement("INSERT INTO \"group\" (id,department_id,name,created_at,updated_at) VALUES ('grpsetup0001',?,'Infrastruktur',now(),now())", [self::GA]);
        $c->conn->executeStatement("INSERT INTO group_membership (user_id,group_id,role,created_at) VALUES ('usetbl000001','grpsetup0001','leader',now())");
    }

    public function testReleaseNeedsAllThreeMandatoryAreasAndRecordsWhoReleased(): void
    {
        $this->withDb(function (object $c): void {
            $mw = $c->user('usetmw000001');
            $status = $c->setup->status($c->dept(), $mw);
            self::assertFalse($status['released']);
            self::assertSame(['stammdaten' => false, 'ressorts' => false, 'mitglieder' => true], array_column($status['steps'], 'done', 'id'));

            try {
                $c->setup->release($c->dept(), $mw);
                self::fail('Unvollständig: keine Freigabe');
            } catch (GrossanlassSetupIncompleteException $e) {
                $steps = $e->toPayload()['steps'];
                self::assertSame('location', $steps[0]['missing'][0]['code']);
                self::assertSame('no_ressort', $steps[1]['missing'][0]['code']);
            }
            self::assertFalse($c->dept()->getGrossanlassConfig()?->isSetupReleased());

            // Ressort ohne Leitung bleibt offen
            $c->conn->executeStatement("INSERT INTO \"group\" (id,department_id,name,created_at,updated_at) VALUES ('grpsetup0001',?,'Infrastruktur',now(),now())", [self::GA]);
            $steps = $c->setup->status($c->dept(), $mw)['steps'];
            self::assertSame('ressort_without_leader', $steps[1]['missing'][0]['code']);
            self::assertSame('Infrastruktur', $steps[1]['missing'][0]['name']);

            $c->conn->executeStatement("INSERT INTO group_membership (user_id,group_id,role,created_at) VALUES ('usetbl000001','grpsetup0001','leader',now())");
            $c->conn->executeStatement("UPDATE department_grossanlass_config SET location_text='Festplatz Nord' WHERE department_id=?", [self::GA]);
            $released = $c->setup->release($c->dept(), $mw);
            self::assertTrue($released['released']);
            self::assertSame('usetmw000001', $c->conn->fetchOne('SELECT setup_released_by_user_id FROM department_grossanlass_config WHERE department_id=?', [self::GA]));
            // idempotent: zweite Freigabe ändert Zeitpunkt und Person nicht
            $at = $c->conn->fetchOne('SELECT setup_released_at FROM department_grossanlass_config WHERE department_id=?', [self::GA]);
            $c->setup->release($c->dept(), $c->user('usetdc000001'));
            self::assertSame($at, $c->conn->fetchOne('SELECT setup_released_at FROM department_grossanlass_config WHERE department_id=?', [self::GA]));
            self::assertSame('usetmw000001', $c->conn->fetchOne('SELECT setup_released_by_user_id FROM department_grossanlass_config WHERE department_id=?', [self::GA]));
        });
    }

    public function testMembersStepNeedsAnMwAndAnOkLeader(): void
    {
        $this->withDb(function (object $c): void {
            $c->conn->executeStatement("DELETE FROM membership WHERE user_id='usetdc000001'");
            $steps = $c->setup->status($c->dept(), $c->user('usetmw000001'))['steps'];
            self::assertSame([['code' => 'no_ok']], $steps[2]['missing']);
        });
    }

    public function testSetupAndReleaseRightsAreSeparated(): void
    {
        $this->withDb(function (object $c): void {
            $this->complete($c);
            // Co-MW richtet ein, gibt aber nicht frei
            $c->setup->status($c->dept(), $c->user('usetcmw00001'));
            try {
                $c->setup->release($c->dept(), $c->user('usetcmw00001'));
                self::fail('Co-MW darf nicht freigeben');
            } catch (\RuntimeException) {
            }
            self::assertFalse($c->dept()->getGrossanlassConfig()?->isSetupReleased());
            $status = $c->setup->status($c->dept(), $c->user('usetcmw00001'));
            self::assertTrue($status['can_setup']);
            self::assertFalse($status['can_release']);

            // andere GA-Rollen und Nicht-Mitglieder: weder Stand noch Freigabe
            foreach (['usetbl000001', 'usetlw000001', 'usethelp0001', 'usetout00001'] as $other) {
                foreach (['status', 'release'] as $action) {
                    try {
                        $c->setup->{$action}($c->dept(), $c->user($other));
                        self::fail("$other darf $action nicht");
                    } catch (\RuntimeException) {
                    }
                }
            }
            self::assertFalse($c->dept()->getGrossanlassConfig()?->isSetupReleased());

            // OK-Leitung (dc) gibt frei
            self::assertTrue($c->setup->release($c->dept(), $c->user('usetdc000001'))['released']);
        });
    }

    public function testGateLocksEverythingButTheSetupBeforeRelease(): void
    {
        $this->withDb(function (object $c): void {
            $base = '/api/departments/' . self::GA . '/grossanlass';
            $setupRoutes = [['GET', '/setup'], ['POST', '/setup/release'], ['GET', '/planung'], ['PATCH', '/planung'], ['GET', '/groups'], ['POST', '/groups'], ['PUT', '/groups/g1'], ['POST', '/groups/g1/members'], ['DELETE', '/groups/g1/members/u1']];
            $lockedRoutes = [['GET', '/uebersicht'], ['POST', '/uebersicht/einsaetze'], ['GET', '/beschaffung/zusagen'], ['GET', '/user-cards'], ['GET', '/gaeste'], ['POST', '/publish'], ['POST', '/groups/g1/tasks'], ['PATCH', '/groups/g1/bauprojekt'], ['GET', '/planung/participants/search'], ['POST', '/planung/rounds'], ['GET', '/mein-ressort/wishes']];

            // MW, Co-MW, OK-Leitung: nur Einrichtung
            foreach (['usetmw000001', 'usetcmw00001', 'usetdc000001'] as $setupUser) {
                foreach ($setupRoutes as [$m, $p]) {
                    self::assertNull($c->gate($setupUser, $m, $base . $p), "$setupUser $m $p");
                }
                foreach ($lockedRoutes as [$m, $p]) {
                    self::assertSame(403, $c->gate($setupUser, $m, $base . $p), "$setupUser $m $p");
                }
            }
            // andere GA-Rollen: gar nichts, auch nicht die Einrichtung
            foreach (['usetbl000001', 'usetlw000001', 'usethelp0001'] as $other) {
                foreach ([...$setupRoutes, ...$lockedRoutes] as [$m, $p]) {
                    self::assertSame(403, $c->gate($other, $m, $base . $p), "$other $m $p");
                }
            }
            // unverändert: Nicht-Mitglieder (die Endpunkte lehnen selbst ab), Superadmin, Anonyme, andere Departments
            self::assertNull($c->gate('usetout00001', 'GET', $base . '/uebersicht'));
            self::assertNull($c->gate('usetsa000001', 'GET', $base . '/uebersicht'));
            self::assertNull($c->gate(null, 'GET', $base . '/uebersicht'));
            self::assertNull($c->gate('usetbl000001', 'GET', '/api/departments/' . self::GUEST . '/grossanlass/hosts/' . self::GA . '/freigaben'));
            self::assertNull($c->gate('usetbl000001', 'GET', '/api/departments/' . self::GA . '/members'));
            // allgemeine Einstellungen: Lesen bleibt offen, Schreiben ist vor der Freigabe für alle Mitglieder gesperrt
            $settings = '/api/departments/' . self::GA . '/settings';
            foreach (['usetmw000001', 'usetcmw00001', 'usetdc000001', 'usetbl000001'] as $member) {
                self::assertNull($c->gate($member, 'GET', $settings), "$member GET settings");
                self::assertNull($c->gate($member, 'GET', $settings . '/group/general'), "$member GET settings group");
                self::assertSame(403, $c->gate($member, 'PUT', $settings), "$member PUT settings");
            }
            self::assertNull($c->gate('usetsa000001', 'PUT', $settings));
            self::assertNull($c->gate('usetout00001', 'PUT', $settings));
            // normales Department (Gast-Department): unverändert
            self::assertNull($c->gate('usetbl000001', 'PUT', '/api/departments/' . self::GUEST . '/settings'));
        });
    }

    public function testReleasedAndMigratedGrossanlassIsNotLocked(): void
    {
        $this->withDb(function (object $c): void {
            $c->conn->executeStatement('UPDATE department_grossanlass_config SET setup_released_at = now() WHERE department_id=?', [self::GA]);
            $base = '/api/departments/' . self::GA . '/grossanlass';
            foreach (['usetmw000001', 'usetbl000001', 'usethelp0001', 'usetlw000001'] as $user) {
                self::assertNull($c->gate($user, 'GET', $base . '/uebersicht'), $user);
                self::assertNull($c->gate($user, 'POST', $base . '/uebersicht/einsaetze'), $user);
                self::assertNull($c->gate($user, 'PUT', '/api/departments/' . self::GA . '/settings'), $user);
            }
        });
    }

    public function testGuestInvitationNeedsTheReleasedSetup(): void
    {
        $this->withDb(function (object $c): void {
            $this->complete($c);
            try {
                $c->planung->publish($c->dept(), $c->user('usetmw000001'));
                self::fail('Einladen vor der Freigabe der Einrichtung');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Ersteinrichtung', $e->getMessage());
            }
            self::assertSame('draft', $c->conn->fetchOne('SELECT status FROM department_grossanlass_config WHERE department_id=?', [self::GA]));
            $c->setup->release($c->dept(), $c->user('usetmw000001'));
            $c->planung->publish($c->dept(), $c->user('usetmw000001'));
            self::assertSame('published', $c->conn->fetchOne('SELECT status FROM department_grossanlass_config WHERE department_id=?', [self::GA]));
        });
    }
}
