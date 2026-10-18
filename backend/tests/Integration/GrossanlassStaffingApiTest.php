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

    public function testHelperProfileFieldsSkillsAndQualificationsRoundTrip(): void
    {
        $this->withApi(function (Api $a): void {
            $a->as('ushsa00000hv');
            [$status, $helper] = self::out($a->call('createHelper', [self::D, $a->json([
                'first_name' => 'Ben', 'last_name' => 'Extern', 'email' => 'Ben@Example.org',
                'occupation' => 'Zimmermann', 'area' => 'Bühne',
                'skills' => ['Gabelstapler', ' gabelstapler ', 'Elektro'],
                'drive_classes' => ['b', 'r1', 'unbekannt'], 'drive_valid_until' => '2030-05-01',
                'qualifications' => [['label' => 'Erste-Hilfe-Kurs', 'valid_until' => '2028-01-31'], ['label' => ''], ['label' => 'SUVA-Kran', 'valid_until' => null]],
            ])]));
            self::assertSame(201, $status);
            [, $loaded] = self::out($a->call('getHelper', [self::D, $helper['id']]));
            self::assertSame('Zimmermann', $loaded['occupation']);
            self::assertSame('Bühne', $loaded['area']);
            self::assertSame(['Gabelstapler', 'Elektro'], $loaded['skills'], 'Duplikate (Schreibweise) entfallen');
            self::assertSame(['classes' => ['b', 'r1'], 'valid_until' => '2030-05-01', 'source' => 'local'], $loaded['drive_license']);
            self::assertSame([['label' => 'Erste-Hilfe-Kurs', 'valid_until' => '2028-01-31'], ['label' => 'SUVA-Kran', 'valid_until' => null]], $loaded['qualifications']);

            // Teil-Update lässt andere Felder unberührt
            [, $patched] = self::out($a->call('updateHelper', [self::D, $helper['id'], $a->json(['skills' => ['Elektro']])]));
            self::assertSame(['Elektro'], $patched['skills']);
            self::assertSame('Zimmermann', $patched['occupation']);
            self::assertSame(400, $a->call('updateHelper', [self::D, $helper['id'], $a->json(['qualifications' => [['label' => 'X', 'valid_until' => 'morgen']]])])->getStatusCode());

            // Katalog: verwendete Fähigkeiten stehen darin; Neuaufnahme ist idempotent
            self::assertSame(['Elektro', 'Gabelstapler'], self::out($a->call('listSkills', [self::D]))[1]);
            [$status, $catalog] = self::out($a->call('addSkill', [self::D, $a->json(['name' => 'Schweissen'])]));
            self::assertSame(201, $status);
            self::assertSame(['Elektro', 'Gabelstapler', 'Schweissen'], $catalog);
            self::assertSame($catalog, self::out($a->call('addSkill', [self::D, $a->json(['name' => 'schweissen'])]))[1]);
            self::assertSame(400, $a->call('addSkill', [self::D, $a->json(['name' => '  '])])->getStatusCode());

            // Funktionswünsche des Personalbedarfs erscheinen im selben Katalog
            $a->connection()->executeStatement("INSERT INTO department_grossanlass_staffing_need (id,department_id,task_id,label,required_count,created_at,updated_at,sort_order) VALUES ('snsa00000001',?,'gtsa00000001','Kranführer',1,now(),now(),0)", [self::D]);
            self::assertContains('Kranführer', self::out($a->call('listSkills', [self::D]))[1]);

            // Lesen für Mitglieder (Bedarfsdialog), Schreiben nur Helferpool-Verwaltung, Fremde gar nicht
            $a->as('ushsa000000u');
            self::assertSame(200, $a->call('listSkills', [self::D])->getStatusCode());
            self::assertSame(403, $a->call('addSkill', [self::D, $a->json(['name' => 'Hack'])])->getStatusCode());
            self::assertSame(403, $a->call('linkableUsers', [self::D])->getStatusCode());
            $a->as('ushsa000nomb');
            self::assertSame(403, $a->call('listSkills', [self::D])->getStatusCode());
        });
    }

    public function testLinkedUserReferencesProfileAndAvoidsDuplicates(): void
    {
        $this->withApi(function (Api $a): void {
            $conn = $a->connection();
            $conn->executeStatement("UPDATE profile SET first_name='Mia', last_name='Konto' WHERE id='pshsa000000u'");
            $conn->executeStatement("INSERT INTO user_drive_license (user_id, drive_classes, valid_until, document_filename, document_original_name, updated_at) VALUES ('ushsa000000u', '[\"b\",\"be\"]', '2031-02-03', '', '', now())");
            $a->clear();
            $a->as('ushsa00000hv');

            [, $ext] = self::out($a->call('createHelper', [self::D, $a->json(['first_name' => 'Mia', 'last_name' => 'Lokal', 'phone' => '079', 'skills' => ['Elektro'], 'drive_classes' => ['c']])]));
            self::assertContains('ushsa000000u', array_column(self::out($a->call('linkableUsers', [self::D]))[1], 'user_id'));

            // Verknüpfen: lokale Fähigkeiten bleiben, Name/E-Mail/Führerausweis kommen aus dem Profil (nur lesend)
            [$status, $linked] = self::out($a->call('updateHelper', [self::D, $ext['id'], $a->json(['user_id' => 'ushsa000000u', 'first_name' => 'Überschrieben', 'drive_classes' => ['d']])]));
            self::assertSame(200, $status);
            self::assertTrue($linked['has_login']);
            self::assertSame(['Mia', 'Konto'], [$linked['first_name'], $linked['last_name']]);
            self::assertSame('ushsa000000u@example.org', $linked['email']);
            self::assertSame(['Elektro'], $linked['skills']);
            self::assertSame('079', $linked['phone']);
            self::assertSame(['classes' => ['b', 'be'], 'valid_until' => '2031-02-03', 'source' => 'profile'], $linked['drive_license']);
            self::assertSame('Mia', $conn->fetchOne("SELECT first_name FROM profile WHERE id='pshsa000000u'"), 'zentrales Profil bleibt unberührt');
            self::assertSame(['c'], json_decode((string) $conn->fetchOne('SELECT drive_classes FROM department_grossanlass_helper WHERE id = ?', [$ext['id']]), true), 'GA schreibt keine Führerausweis-Daten verknüpfter Helfer');

            // Neuer Helfer direkt aus einem Konto: Name kommt aus dem Profil, kein Namenszwang im Formular
            [$status, $fromAccount] = self::out($a->call('createHelper', [self::D, $a->json(['user_id' => 'ushsa0000sub', 'skills' => ['Koch']])]));
            self::assertSame(201, $status);
            self::assertTrue($fromAccount['has_login']);
            self::assertSame(['T', 'T'], [$fromAccount['first_name'], $fromAccount['last_name']]);
            self::assertSame(['Koch'], $fromAccount['skills']);

            // Kein zweiter Helfer für dasselbe Konto, keine Verknüpfung mit Fremden
            [, $second] = self::out($a->call('createHelper', [self::D, $a->json(['first_name' => 'Zweit', 'last_name' => 'Helfer'])]));
            self::assertSame(400, $a->call('updateHelper', [self::D, $second['id'], $a->json(['user_id' => 'ushsa000000u'])])->getStatusCode());
            self::assertSame(404, $a->call('updateHelper', [self::D, $second['id'], $a->json(['user_id' => 'ushsa000nomb'])])->getStatusCode());
            // Doppelte E-Mail (extern oder zum verknüpften Profil) wird abgelehnt
            self::assertSame(400, $a->call('createHelper', [self::D, $a->json(['first_name' => 'Dup', 'email' => 'ushsa000000u@example.org'])])->getStatusCode());
            // Bewusst verknüpfter Helfer ohne Helferrolle bleibt aktiv (Konten-Abgleich archiviert nur eigene Einträge)
            $a->call('listHelpers', [self::D, new Request()]); // löst den Konten-Abgleich aus
            self::assertSame('active', self::out($a->call('getHelper', [self::D, $fromAccount['id']]))[1]['status']);
            // Konten-Sync erzeugt für das verknüpfte Konto keinen zweiten Eintrag
            $conn->executeStatement("UPDATE membership SET is_helper = true WHERE user_id='ushsa000000u' AND department_id=?", [self::D]);
            $a->clear();
            $a->as('ushsa00000hv');
            $rows = array_filter(self::out($a->call('listHelpers', [self::D, new Request()]))[1], static fn (array $h) => $h['user_id'] === 'ushsa000000u');
            self::assertCount(1, $rows);
            self::assertSame($ext['id'], array_values($rows)[0]['id']);
        });
    }

    public function testGaUserWithHelperRoleAppearsAlongsideOtherRolesAndKeepsExternalHelpers(): void
    {
        $this->withApi(function (Api $a): void {
            $conn = $a->connection();
            // Konto mit weiterer Rolle (dc) und zusätzlicher Helferrolle
            $conn->executeStatement("UPDATE membership SET is_helper = true WHERE user_id='ushsa00000dc' AND department_id=?", [self::D]);
            $a->clear();
            $a->as('ushsa00000hv');
            self::out($a->call('createHelper', [self::D, $a->json(['first_name' => 'Extern', 'last_name' => 'Ohne Konto'])]));
            $rows = self::out($a->call('listHelpers', [self::D, new Request()]))[1];
            $byUser = array_values(array_filter($rows, static fn (array $h) => $h['user_id'] === 'ushsa00000dc'));
            self::assertCount(1, $byUser, 'Konto mit Helferrolle und weiteren Rollen erscheint genau einmal');
            self::assertCount(1, array_filter($rows, static fn (array $h) => $h['user_id'] === null && $h['last_name'] === 'Ohne Konto'));

            // Fällt die Helferrolle weg, wird archiviert (nicht gelöscht); der externe Helfer bleibt
            $conn->executeStatement("UPDATE membership SET is_helper = false WHERE user_id='ushsa00000dc' AND department_id=?", [self::D]);
            $a->clear();
            $a->as('ushsa00000hv');
            $active = self::out($a->call('listHelpers', [self::D, new Request()]))[1];
            self::assertCount(0, array_filter($active, static fn (array $h) => $h['user_id'] === 'ushsa00000dc'));
            self::assertCount(1, array_filter($active, static fn (array $h) => $h['last_name'] === 'Ohne Konto'));
            $all = self::out($a->call('listHelpers', [self::D, new Request(['status' => 'all'])]))[1];
            self::assertCount(1, array_filter($all, static fn (array $h) => $h['user_id'] === 'ushsa00000dc' && $h['status'] === 'archived'));
        });
    }

    public function testSkillCatalogAndHelpersAreIsolatedPerDepartment(): void
    {
        $this->withApi(function (Api $a): void {
            $other = 'deptsa000002';
            $a->connection()->executeStatement("INSERT INTO membership (user_id,department_id,role,is_primary) VALUES ('ushsa00000hv',?,'u',false)", [$other]);
            $a->clear();
            $a->as('ushsa00000hv');
            [, $helper] = self::out($a->call('createHelper', [self::D, $a->json(['first_name' => 'A', 'skills' => ['Nur-D']])]));
            // Als reines Mitglied des anderen Departments: kein Zugriff auf Helfer, Katalog enthält nichts aus D
            self::assertSame(403, $a->call('addSkill', [$other, $a->json(['name' => 'Fremd'])])->getStatusCode());
            self::assertSame([], self::out($a->call('listSkills', [$other]))[1]);
            self::assertSame(403, $a->call('getHelper', [$other, $helper['id']])->getStatusCode());
            self::assertContains('Nur-D', self::out($a->call('listSkills', [self::D]))[1]);
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
