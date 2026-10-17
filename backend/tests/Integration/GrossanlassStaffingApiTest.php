<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\GrossanlassStaffingController;
use App\Entity\User;
use App\Tests\Wiring\FreshKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * REST-Schicht des Helferpools gegen die echte Datenbank: Berechtigung je Rolle, CRUD, Zuteilung, 409-Konflikte.
 * Ruft den Controller direkt auf (die Firewall prüft ROLE_USER; hier zählen Mitgliedschaft und GA-Rolle).
 * Läuft nur mit EMATCHEF_TEST_DB_URL gegen eine isolierte Datenbank; jeder Test endet mit Rollback.
 */
final class GrossanlassStaffingApiTest extends TestCase
{
    private const D = 'deptsa000001';
    private const DAY = '2027-06-01';

    /** @param callable(Api): void $test */
    private function withApi(callable $test): void
    {
        $url = GrossanlassStaffingIntegrationTest::url();
        FreshKernel::run(function ($kernel) use ($test): void {
            $c = $kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $c->get('doctrine')->getManager();
            $conn = $em->getConnection();
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
            try {
                GrossanlassStaffingIntegrationTest::seed($conn);
                $em->clear();
                $test(new Api($c->get(GrossanlassStaffingController::class), $em));
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassStaffingController::class]);
    }

    private static function at(string $hm): string
    {
        return self::DAY . 'T' . $hm . ':00';
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private static function out(JsonResponse $r): array
    {
        $data = json_decode((string) $r->getContent(), true);

        return [$r->getStatusCode(), is_array($data) ? $data : []];
    }

    public function testRolesAreEnforcedServerSide(): void
    {
        $this->withApi(function (Api $a): void {
            // Standard: GA-MW und GA-OK (dc) verwalten; Mitglied (u) und Fremde nicht
            foreach (['ushsa00000mw', 'ushsa00000dc'] as $uid) {
                $a->as($uid);
                self::assertSame(200, $a->call('listHelpers', [self::D, new Request()])->getStatusCode(), $uid);
                self::assertTrue(self::out($a->call('overview', [self::D, new Request()]))[1]['can_manage'], $uid);
            }
            foreach (['ushsa000000u'] as $uid) {
                $a->as($uid);
                self::assertSame(403, $a->call('listHelpers', [self::D, new Request()])->getStatusCode(), $uid);
                self::assertSame(403, $a->call('createHelper', [self::D, $a->json(['first_name' => 'X'])])->getStatusCode(), $uid);
                self::assertSame(403, $a->call('assign', [self::D, $a->json(['need_id' => 'x', 'helper_id' => 'y'])])->getStatusCode(), $uid);
                self::assertSame(403, $a->call('conflicts', [self::D])->getStatusCode(), $uid);
                self::assertSame(403, $a->call('schedule', [self::D, new Request(['from' => self::at('00:00'), 'to' => self::at('23:00')])])->getStatusCode(), $uid);
                self::assertSame(403, $a->call('needDetail', [self::D, 'x'])->getStatusCode(), $uid);
            }
            $a->as('ushsa000nomb');
            self::assertSame(403, $a->call('overview', [self::D, new Request()])->getStatusCode(), 'ohne Mitgliedschaft');

            // Lesen der Übersicht: einfaches Mitglied nein
            $a->as('ushsa000000u');
            self::assertSame(403, $a->call('overview', [self::D, new Request()])->getStatusCode());

            foreach (['ushsa00000hv', 'ushsa0000sub'] as $uid) {
                $a->as($uid);
                self::assertSame(200, $a->call('listHelpers', [self::D, new Request()])->getStatusCode(), $uid);
                self::assertSame(200, $a->call('overview', [self::D, new Request()])->getStatusCode(), $uid);
            }
        });
    }

    public function testMwAndOkSwitchesAreIndependentAndHvStaysAllowed(): void
    {
        foreach ([[false, true], [true, false], [false, false]] as [$mwOn, $okOn]) {
            $this->withApi(function (Api $a) use ($mwOn, $okOn): void {
                GrossanlassStaffingIntegrationTest::seedConfig($a->connection(), $mwOn, $okOn);
                $a->clear();
                foreach (['ushsa00000mw' => $mwOn, 'ushsa00000dc' => $okOn] as $uid => $on) {
                    $a->as($uid);
                    self::assertSame($on ? 200 : 403, $a->call('listHelpers', [self::D, new Request()])->getStatusCode(), $uid);
                    self::assertSame($on ? 201 : 403, $a->call('createHelper', [self::D, $a->json(['first_name' => 'X'])])->getStatusCode(), $uid);
                    self::assertSame($on ? 200 : 403, $a->call('conflicts', [self::D])->getStatusCode(), $uid);
                    self::assertSame($on, self::out($a->call('overview', [self::D, new Request()]))[1]['can_manage'], $uid);
                }
                foreach (['ushsa00000hv', 'ushsa0000sub'] as $uid) {
                    $a->as($uid);
                    self::assertSame(200, $a->call('listHelpers', [self::D, new Request()])->getStatusCode(), $uid);
                }
            });
        }
    }

    public function testHelperAndAvailabilityCrud(): void
    {
        $this->withApi(function (Api $a): void {
            $a->as('ushsa00000hv');
            [$status, $helper] = self::out($a->call('createHelper', [self::D, $a->json(['first_name' => 'Lena', 'last_name' => 'Frei', 'email' => 'lena@example.org'])]));
            self::assertSame(201, $status);
            self::assertFalse($helper['has_login']);
            self::assertSame(400, $a->call('createHelper', [self::D, $a->json(['first_name' => '', 'last_name' => ''])])->getStatusCode());
            self::assertSame(400, $a->call('createHelper', [self::D, $a->json(['first_name' => 'A', 'email' => 'kaputt'])])->getStatusCode());

            [, $patched] = self::out($a->call('updateHelper', [self::D, $helper['id'], $a->json(['phone' => '079 111 22 33'])]));
            self::assertSame('079 111 22 33', $patched['phone']);
            self::assertSame(404, $a->call('getHelper', [self::D, 'ghunbekannt01'])->getStatusCode());

            [$status, $av] = self::out($a->call('addAvailability', [self::D, $helper['id'], $a->json(['starts_at' => self::at('08:00'), 'ends_at' => self::at('12:00')])]));
            self::assertSame(201, $status);
            self::assertSame(400, $a->call('addAvailability', [self::D, $helper['id'], $a->json(['starts_at' => self::at('11:00'), 'ends_at' => self::at('13:00')])])->getStatusCode(), 'Überlappung');
            self::assertSame(400, $a->call('addAvailability', [self::D, $helper['id'], $a->json(['starts_at' => self::at('13:00'), 'ends_at' => self::at('13:00')])])->getStatusCode(), 'leeres Fenster');
            [, $moved] = self::out($a->call('updateAvailability', [self::D, $av['id'], $a->json(['starts_at' => self::at('07:00'), 'ends_at' => self::at('12:00')])]));
            self::assertSame(self::at('07:00'), $moved['starts_at']);
            self::assertCount(1, self::out($a->call('listAvailability', [self::D, $helper['id']]))[1]);
            self::assertSame(200, $a->call('removeAvailability', [self::D, $av['id']])->getStatusCode());

            self::assertSame('archived', self::out($a->call('archiveHelper', [self::D, $helper['id']]))[1]['status']);
            self::assertCount(0, self::out($a->call('listHelpers', [self::D, new Request()]))[1]);
            self::assertSame('active', self::out($a->call('restoreHelper', [self::D, $helper['id']]))[1]['status']);
        });
    }

    public function testNeedsAssignmentsConflictsAndNameFreeOverview(): void
    {
        $this->withApi(function (Api $a): void {
            // Personalbedarf legt die GA-Leitung am Bauprojekt an (Bearbeitungsrecht), mit Standardaufgabe «Allgemein»
            $a->as('ushsa00000mw');
            [$status, $n1] = self::out($a->call('createNeed', [self::D, 'grpsa0000001', $a->json(['required_count' => 1, 'label' => 'Elektriker', 'starts_at' => self::at('08:00'), 'ends_at' => self::at('10:00')])]));
            self::assertSame(201, $status);
            [, $n2] = self::out($a->call('createNeed', [self::D, 'grpsa0000001', $a->json(['required_count' => 1, 'starts_at' => self::at('09:00'), 'ends_at' => self::at('11:00')])]));
            self::assertSame(400, $a->call('createNeed', [self::D, 'grpsa0000001', $a->json(['required_count' => -1])])->getStatusCode());
            self::assertSame(404, $a->call('updateNeed', [self::D, 'grpsa0000001', 'snunbekannt01', $a->json(['required_count' => 2])])->getStatusCode());
            self::assertCount(2, self::out($a->call('listNeeds', [self::D, 'grpsa0000001']))[1]);

            $a->as('ushsa00000hv');
            [, $helper] = self::out($a->call('createHelper', [self::D, $a->json(['first_name' => 'Geheim', 'last_name' => 'Name'])]));
            $a->call('addAvailability', [self::D, $helper['id'], $a->json(['starts_at' => self::at('07:00'), 'ends_at' => self::at('18:00')])]);

            [$status, $ok] = self::out($a->call('assign', [self::D, $a->json(['need_id' => $n1['id'], 'helper_id' => $helper['id']])]));
            self::assertSame(201, $status);
            self::assertSame([], $ok['warnings']);

            [$status, $conflict] = self::out($a->call('assign', [self::D, $a->json(['need_id' => $n2['id'], 'helper_id' => $helper['id']])]));
            self::assertSame(409, $status);
            self::assertSame('staffing_conflict', $conflict['code']);
            self::assertSame('double_booking', $conflict['conflict']['kind']);
            self::assertTrue($conflict['conflict']['override_possible']);

            [$status, $override] = self::out($a->call('assign', [self::D, $a->json(['need_id' => $n2['id'], 'helper_id' => $helper['id'], 'override_reason' => 'Pendelt zwischen Bühne und Bar'])]));
            self::assertSame(201, $status);
            self::assertTrue($override['assignment']['has_override']);

            // ohne Verfügbarkeit nie, auch nicht mit Ausnahme
            [, $other] = self::out($a->call('createHelper', [self::D, $a->json(['first_name' => 'Ohne', 'last_name' => 'Zeit'])]));
            [$status, $none] = self::out($a->call('assign', [self::D, $a->json(['need_id' => $n1['id'], 'helper_id' => $other['id'], 'override_reason' => 'egal'])]));
            self::assertSame(409, $status);
            self::assertSame('no_availability', $none['conflict']['kind']);
            self::assertSame(404, $a->call('assign', [self::D, $a->json(['need_id' => $n1['id'], 'helper_id' => 'ghunbekannt01'])])->getStatusCode());

            self::assertGreaterThanOrEqual(1, \count(self::out($a->call('conflicts', [self::D]))[1]));
            self::assertCount(1, self::out($a->call('schedule', [self::D, new Request(['from' => self::at('00:00'), 'to' => self::at('23:00')])]))[1], 'nur Helfer mit Verfügbarkeit oder Zuteilung im Zeitraum');
            $detail = self::out($a->call('needDetail', [self::D, $n1['id']]))[1];
            self::assertSame('Geheim Name', $detail['assignments'][0]['helper_name'], 'Detail zeigt Namen für GA-HV');

            // Übersicht: Zahlen, aber keine Helfernamen — auch für die GA-Leitung
            $a->as('ushsa00000mw');
            $raw = (string) $a->call('overview', [self::D, new Request(['group_ids' => 'grpsa0000001'])])->getContent();
            self::assertStringNotContainsString('Geheim', $raw);
            self::assertStringNotContainsString('Name', $raw);
            $overview = json_decode($raw, true);
            self::assertSame(2, $overview['totals']['required_total']);
            self::assertEqualsWithDelta(1.0, $overview['needs'][0]['coverage']['fill_ratio'], 0.0001);

            // Zuteilung ändern und stornieren: nicht für ein einfaches Mitglied
            $a->as('ushsa000000u');
            self::assertSame(403, $a->call('cancelAssignment', [self::D, $ok['assignment']['id']])->getStatusCode());
            $a->as('ushsa0000sub');
            self::assertSame(200, $a->call('updateAssignment', [self::D, $ok['assignment']['id'], $a->json(['starts_at' => self::at('08:00'), 'ends_at' => self::at('09:00')])])->getStatusCode());
            self::assertSame('cancelled', self::out($a->call('cancelAssignment', [self::D, $ok['assignment']['id']]))[1]['status']);

            // Bedarf mit Zuteilung kann nicht gelöscht werden → 409
            $a->as('ushsa00000mw');
            [$status, $inUse] = self::out($a->call('deleteNeed', [self::D, 'grpsa0000001', $n2['id']]));
            self::assertSame(409, $status);
            self::assertSame('in_use', $inUse['conflict']['kind']);
        });
    }
}

/** @internal Testkontext: ruft Controller-Aktionen als wählbarer Benutzer auf. */
final class Api
{
    public function __construct(private GrossanlassStaffingController $controller, private EntityManagerInterface $em)
    {
    }

    public function connection(): \Doctrine\DBAL\Connection
    {
        return $this->em->getConnection();
    }

    public function clear(): void
    {
        $this->em->clear();
    }

    public function as(string $userId): void
    {
        $user = $this->em->find(User::class, $userId);
        if (!$user instanceof User) {
            throw new \LogicException('Testbenutzer fehlt: ' . $userId);
        }
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $tokens);
        $this->controller->setContainer($container);
    }

    /** @param list<mixed> $args */
    public function call(string $action, array $args): JsonResponse
    {
        return $this->controller->{$action}(...$args);
    }

    /** @param array<string, mixed> $data */
    public function json(array $data): Request
    {
        return new Request([], [], [], [], [], [], json_encode($data));
    }
}
