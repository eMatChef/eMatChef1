<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassExternalSourceService;
use App\Service\Grossanlass\GrossanlassExternalSyncService;
use App\Service\Grossanlass\GrossanlassHelperPoolService;
use App\Service\Grossanlass\GrossanlassStaffingAssignmentService;
use App\Service\Grossanlass\GrossanlassStaffingCoverageService;
use App\Service\Grossanlass\GrossanlassStaffingNeedService;
use App\Tests\Wiring\FreshKernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/** API-Daten des Helferpools gegen die echte Datenbank (JSON-Import, Mapping, Synchronisation, Konflikte). */
final class GrossanlassExternalSyncIntegrationTest extends TestCase
{
    private const D = 'deptsa000001';
    private const OTHER = 'deptsa000002';

    /** @param callable(object): void $test */
    private function withDb(callable $test): void
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
                $test((object) [
                    'em' => $em, 'conn' => $conn,
                    'dept' => $em->find(Department::class, self::D),
                    'other' => $em->find(Department::class, self::OTHER),
                    'hv' => $em->find(User::class, 'ushsa00000hv'),
                    'mw' => $em->find(User::class, 'ushsa00000mw'),
                    'sources' => $c->get(GrossanlassExternalSourceService::class),
                    'sync' => $c->get(GrossanlassExternalSyncService::class),
                    'pool' => $c->get(GrossanlassHelperPoolService::class),
                    'assign' => $c->get(GrossanlassStaffingAssignmentService::class),
                    'coverage' => $c->get(GrossanlassStaffingCoverageService::class),
                    'needs' => $c->get(GrossanlassStaffingNeedService::class),
                ]);
            } finally {
                $conn->rollBack();
            }
        }, $url, [
            GrossanlassExternalSourceService::class, GrossanlassExternalSyncService::class, GrossanlassHelperPoolService::class,
            GrossanlassStaffingAssignmentService::class, GrossanlassStaffingCoverageService::class, GrossanlassStaffingNeedService::class,
        ]);
    }

    private const MAPPING = [
        'records_path' => 'bookings',
        'fields' => [
            'booking_id' => 'id', 'helper_key' => 'person.id', 'helper_name' => 'person.name', 'helper_email' => 'person.mail',
            'mission' => 'mission', 'shift_start' => 'start', 'shift_end' => 'end', 'booking_status' => 'status',
        ],
        'cancelled_values' => ['cancelled'],
    ];

    /** @param array<string, array{0: string, 1: string}> $shifts id => [start, end] */
    private static function json(array $shifts, array $status = []): string
    {
        $rows = [];
        foreach ($shifts as $id => [$s, $e]) {
            $rows[] = ['id' => $id, 'person' => ['id' => 'P' . $id, 'name' => "Hans $id Muster", 'mail' => "h$id@example.org"], 'mission' => 'Aufbau', 'start' => "2027-06-01T$s:00", 'end' => "2027-06-01T$e:00", 'status' => $status[$id] ?? 'confirmed'];
        }

        return json_encode(['bookings' => $rows]);
    }

    private static function newSource(object $x, bool $complete = false): string
    {
        $src = $x->sources->create($x->dept, $x->hv, ['name' => 'Mission Control PFF27', 'snapshot_complete' => $complete]);
        $x->sources->saveMapping($x->dept, $x->hv, $src['id'], self::MAPPING);

        return $src['id'];
    }

    private static function rows(object $x, string $table): int
    {
        return (int) $x->conn->fetchOne("SELECT COUNT(*) FROM $table");
    }

    public function testSecretIsEncryptedAndNeverReturned(): void
    {
        $this->withDb(function ($x): void {
            $src = $x->sources->create($x->dept, $x->hv, ['name' => 'MC', 'base_url' => 'https://mc.example.org', 'endpoint_path' => '/api/bookings', 'auth_type' => 'bearer', 'secret' => 'PLAIN-TOKEN-123']);
            self::assertTrue($src['has_secret']);
            self::assertStringNotContainsString('PLAIN-TOKEN-123', json_encode([$src, $x->sources->list($x->dept, $x->hv)]));
            $stored = (string) $x->conn->fetchOne('SELECT secret_enc FROM department_grossanlass_ext_source WHERE id = ?', [$src['id']]);
            self::assertNotSame('', $stored);
            self::assertStringNotContainsString('PLAIN-TOKEN-123', $stored);
            // Änderung ohne neuen Schlüssel behält ihn
            self::assertTrue($x->sources->update($x->dept, $x->hv, $src['id'], ['name' => 'MC 2'])['has_secret']);
        });
    }

    public function testOnlyManagersAndOwnGrossanlassCanUseSources(): void
    {
        $this->withDb(function ($x): void {
            $id = self::newSource($x);
            $u = $x->em->find(User::class, 'ushsa000000u'); // Mitglied ohne Verwaltungsrolle
            try {
                $x->sources->list($x->dept, $u);
                self::fail('Ohne Verwaltungsrecht keine Datenquellen');
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
            try {
                $x->sources->get($x->other, $x->hv, $id);
                self::fail('Quelle eines anderen Grossanlasses darf nicht lesbar sein');
            } catch (\InvalidArgumentException | \RuntimeException) {
                self::assertTrue(true);
            }
        });
    }

    public function testPreviewChangesNothingAndSyncIsIdempotent(): void
    {
        $this->withDb(function ($x): void {
            $id = self::newSource($x);
            $payload = $x->sources->importJson($x->dept, $x->hv, $id, self::json(['b1' => ['08:00', '12:00'], 'b2' => ['09:00', '13:00']]));

            $p = $x->sync->preview($x->dept, $x->hv, $id, $payload['id']);
            self::assertTrue($p['dry_run']);
            self::assertSame(2, $p['counts']['created']);
            self::assertSame(0, self::rows($x, 'department_grossanlass_helper'));
            self::assertSame(0, self::rows($x, 'department_grossanlass_ext_record'));
            self::assertSame(0, self::rows($x, 'department_grossanlass_ext_run'));

            $x->em->clear();
            $x->dept = $x->em->find(Department::class, self::D);
            $x->hv = $x->em->find(User::class, 'ushsa00000hv');
            $r1 = $x->sync->sync($x->dept, $x->hv, $id, $payload['id'], false);
            self::assertSame(2, $r1['counts']['created']);
            self::assertSame(2, self::rows($x, 'department_grossanlass_helper'));
            self::assertSame(2, self::rows($x, 'department_grossanlass_helper_availability'));

            $x->em->clear();
            $x->dept = $x->em->find(Department::class, self::D);
            $x->hv = $x->em->find(User::class, 'ushsa00000hv');
            $r2 = $x->sync->sync($x->dept, $x->hv, $id, $payload['id'], false);
            self::assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 2, 'removed' => 0, 'conflicts' => 0], $r2['counts']);
            self::assertSame(2, self::rows($x, 'department_grossanlass_helper'), 'keine doppelten Helfer');
            self::assertSame(2, self::rows($x, 'department_grossanlass_ext_record'));
            self::assertSame(2, self::rows($x, 'department_grossanlass_ext_run'));

            // identische Originalantwort wird nicht doppelt abgelegt
            $again = $x->sources->importJson($x->dept, $x->hv, $id, self::json(['b1' => ['08:00', '12:00'], 'b2' => ['09:00', '13:00']]));
            self::assertSame($payload['id'], $again['id']);
            self::assertSame(1, self::rows($x, 'department_grossanlass_ext_payload'));
            self::assertSame(2, (int) $x->conn->fetchOne('SELECT seen_count FROM department_grossanlass_ext_payload'));
        });
    }

    public function testShiftChangeFlagsAssignmentAsConflictAndExcludesItFromCoverage(): void
    {
        $this->withDb(function ($x): void {
            $id = self::newSource($x);
            $x->sources->importJson($x->dept, $x->hv, $id, self::json(['b1' => ['08:00', '12:00']]));
            $x->sync->sync($x->dept, $x->hv, $id, null, false);
            $helperId = trim((string) $x->conn->fetchOne('SELECT id FROM department_grossanlass_helper'));

            $task = $x->em->find(DepartmentGrossanlassTask::class, 'gtsa00000001');
            $group = $x->em->find(Group::class, 'grpsa0000001');
            $need = $x->needs->create($x->dept, $task, $group, ['required_count' => 1, 'label' => '', 'starts_at' => '2027-06-01T08:00:00', 'ends_at' => '2027-06-01T12:00:00'])->getId();
            $a = $x->assign->assign($x->dept, $x->hv, ['need_id' => $need, 'helper_id' => $helperId, 'starts_at' => '2027-06-01T09:00:00', 'ends_at' => '2027-06-01T11:00:00']);
            $assignmentId = $a['assignment']['id'] ?? $a['id'];
            self::assertSame(1, $x->coverage->overview($x->dept)['needs'][0]['coverage']['peak_assigned']);

            // Schicht verschoben: Zuteilung liegt nicht mehr darin
            $p2 = $x->sources->importJson($x->dept, $x->hv, $id, self::json(['b1' => ['13:00', '17:00']]));
            $run = $x->sync->sync($x->dept, $x->hv, $id, $p2['id'], false);
            self::assertSame(1, $run['counts']['updated']);
            self::assertSame(1, $run['counts']['conflicts']);
            self::assertSame('shift_changed', $run['conflicts'][0]['detail']);
            self::assertSame('conflict', trim((string) $x->conn->fetchOne('SELECT status FROM department_grossanlass_staffing_assignment WHERE id = ?', [$assignmentId])));
            self::assertSame(0, $x->coverage->overview($x->dept)['needs'][0]['coverage']['peak_assigned'], 'Konflikt zählt nicht zur Deckung');
            self::assertContains('external_booking_invalid', array_column($x->coverage->conflicts($x->dept, $x->hv), 'kind'));
            self::assertSame(1, self::rows($x, "department_grossanlass_staffing_log WHERE action = 'conflict'"));
            self::assertSame(2, self::rows($x, 'department_grossanlass_ext_change'), 'created + updated mit altem und neuem Wert');

            // zurück verschoben: Konflikt löst sich
            $p3 = $x->sources->importJson($x->dept, $x->hv, $id, self::json(['b1' => ['08:00', '12:00']], ['b1' => 'confirmed']) . ' ');
            $back = $x->sync->sync($x->dept, $x->hv, $id, $p3['id'], false);
            self::assertSame(1, $back['resolved']);
            self::assertNotSame('conflict', trim((string) $x->conn->fetchOne('SELECT status FROM department_grossanlass_staffing_assignment WHERE id = ?', [$assignmentId])));
            self::assertSame(1, $x->coverage->overview($x->dept)['needs'][0]['coverage']['peak_assigned']);
        });
    }

    public function testCancelledBookingAndRemovalOnlyForProvablyCompleteResponse(): void
    {
        $this->withDb(function ($x): void {
            $id = self::newSource($x, false);
            $x->sources->importJson($x->dept, $x->hv, $id, self::json(['b1' => ['08:00', '12:00'], 'b2' => ['08:00', '12:00']]));
            $x->sync->sync($x->dept, $x->hv, $id, null, false);

            // b2 fehlt, Quelle nicht als vollständig bestätigt: nichts wird entfernt
            $p = $x->sources->importJson($x->dept, $x->hv, $id, self::json(['b1' => ['08:00', '12:00']], ['b1' => 'cancelled']));
            $r = $x->sync->sync($x->dept, $x->hv, $id, $p['id'], false);
            self::assertSame(0, $r['counts']['removed']);
            self::assertContains('removal_skipped_not_complete', array_column($r['errors'], 'code'));
            self::assertSame('cancelled', $x->conn->fetchOne("SELECT state FROM department_grossanlass_ext_record WHERE external_key = 'b1'"));
            self::assertSame(1, self::rows($x, 'department_grossanlass_helper_availability'), 'Verfügbarkeit der stornierten Buchung entfällt');

            // bestätigt vollständig: b2 gilt als entfernt
            $x->sources->update($x->dept, $x->hv, $id, ['snapshot_complete' => true]);
            $r2 = $x->sync->sync($x->dept, $x->hv, $id, $p['id'], false);
            self::assertSame(1, $r2['counts']['removed']);
            self::assertSame('removed', $x->conn->fetchOne("SELECT state FROM department_grossanlass_ext_record WHERE external_key = 'b2'"));
            self::assertSame(0, self::rows($x, 'department_grossanlass_helper_availability'));
            self::assertSame(2, self::rows($x, 'department_grossanlass_helper'), 'Helfer bleiben erhalten');

            // leere Antwort entfernt nichts
            $empty = $x->sources->importJson($x->dept, $x->hv, $id, '{"bookings":[]}');
            $r3 = $x->sync->sync($x->dept, $x->hv, $id, $empty['id'], false);
            self::assertContains('removal_skipped_empty_response', array_column($r3['errors'], 'code'));
        });
    }

    public function testNeverMergesByNameOrEmailWithExistingHelpers(): void
    {
        $this->withDb(function ($x): void {
            $manual = $x->pool->create($x->dept, $x->hv, ['first_name' => 'Hans b1', 'last_name' => 'Muster', 'email' => 'hb1@example.org']);
            $id = self::newSource($x);
            $x->sources->importJson($x->dept, $x->hv, $id, self::json(['b1' => ['08:00', '12:00']]));
            $r = $x->sync->sync($x->dept, $x->hv, $id, null, false);

            self::assertSame(2, self::rows($x, 'department_grossanlass_helper'));
            self::assertContains('email_belongs_to_other_helper', array_column($r['errors'], 'code'));
            $ext = $x->conn->fetchAssociative("SELECT id, email FROM department_grossanlass_helper WHERE ext_source_id IS NOT NULL");
            self::assertNotSame($manual['id'], trim((string) $ext['id']));
            self::assertNull($ext['email']);
            self::assertSame(0, self::rows($x, "department_grossanlass_helper_availability WHERE helper_id = '{$manual['id']}'"));
        });
    }

    public function testIncompleteMappingAndInvalidRecordsFailSafely(): void
    {
        $this->withDb(function ($x): void {
            $src = $x->sources->create($x->dept, $x->hv, ['name' => 'Leer']);
            $x->sources->importJson($x->dept, $x->hv, $src['id'], '{"bookings":[]}');
            $r = $x->sync->sync($x->dept, $x->hv, $src['id'], null, false);
            self::assertSame('failed', $r['outcome']);
            self::assertSame(0, self::rows($x, 'department_grossanlass_helper'));
            self::assertSame(1, self::rows($x, "department_grossanlass_ext_run WHERE outcome = 'failed'"));

            $id = self::newSource($x);
            $bad = json_encode(['bookings' => [
                ['id' => 'ok', 'person' => ['id' => 'P1', 'name' => 'Gut Test', 'mail' => 'g@example.org'], 'mission' => 'x', 'start' => '2027-06-01T08:00:00', 'end' => '2027-06-01T09:00:00'],
                ['id' => 'bad', 'person' => ['id' => 'P2', 'name' => 'Schlecht'], 'start' => 'nie', 'end' => '2027-06-01T09:00:00'],
            ]]);
            $x->sources->importJson($x->dept, $x->hv, $id, $bad);
            $p = $x->sync->preview($x->dept, $x->hv, $id, null);
            self::assertSame(1, $p['counts']['created']);
            self::assertSame('invalid_datetime', $p['errors'][0]['code']);
            self::assertSame('partial', $p['outcome']);
        });
    }
}
