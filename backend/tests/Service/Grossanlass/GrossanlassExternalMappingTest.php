<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassExternalMapping as M;
use PHPUnit\Framework\TestCase;

final class GrossanlassExternalMappingTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function mapping(): array
    {
        return M::sanitize([
            'records_path' => 'data.bookings',
            'fields' => [
                'booking_id' => 'booking.id', 'helper_key' => 'person.id', 'helper_name' => 'person.name', 'helper_email' => 'person.mail',
                'mission' => 'mission.title', 'shift_start' => 'shift.start', 'shift_end' => 'shift.end', 'booking_status' => 'booking.status',
            ],
            'cancelled_values' => ['Cancelled', 'storniert'],
        ]);
    }

    /** @return array<string, mixed> */
    private static function record(string $id, array $over = []): array
    {
        return array_replace_recursive([
            'booking' => ['id' => $id, 'status' => 'confirmed'],
            'person' => ['id' => 'p' . $id, 'name' => 'Anna Maria Muster', 'mail' => 'Anna@Example.org'],
            'mission' => ['title' => 'Aufbau'],
            'shift' => ['start' => '2027-06-01T08:00:00', 'end' => '2027-06-01T12:00:00'],
        ], $over);
    }

    public function testMapsFreelyConfiguredPathsAndNormalizes(): void
    {
        $json = ['data' => ['bookings' => [self::record('b1'), self::record('b2', ['booking' => ['status' => 'Cancelled']])]]];
        $ex = M::extractRecords($json, self::mapping());
        self::assertNull($ex['problem']);
        $res = M::mapRecords($ex['records'], self::mapping());

        self::assertSame([], $res['errors']);
        $row = $res['rows']['b1'];
        self::assertSame('Anna Maria', $row['helper_first_name']);
        self::assertSame('Muster', $row['helper_last_name']);
        self::assertSame('anna@example.org', $row['helper_email']);
        self::assertSame('pb1', $row['helper_key']);
        self::assertSame('2027-06-01T08:00:00', $row['shift_start']);
        self::assertFalse($row['cancelled']);
        self::assertTrue($res['rows']['b2']['cancelled'], 'Statuswert aus der Liste der Stornowerte (ohne Gross-/Kleinschreibung)');
    }

    public function testReportsMissingRequiredMappingsAndIdentity(): void
    {
        $codes = array_map(static fn (array $p) => $p['field'] . ':' . $p['code'], M::configProblems(M::sanitize(['fields' => ['booking_id' => 'id', 'helper_name' => 'n']])));
        self::assertEqualsCanonicalizing(['shift_start:required_unmapped', 'shift_end:required_unmapped', 'helper_key:identity_unmapped'], $codes);
        self::assertSame([], M::configProblems(self::mapping()));
    }

    public function testInvalidRecordsAreReportedAndSkippedNotImported(): void
    {
        $recs = [
            self::record('ok'),
            self::record('badtime', ['shift' => ['start' => 'gestern']]),
            self::record('reverse', ['shift' => ['start' => '2027-06-01T12:00:00', 'end' => '2027-06-01T08:00:00']]),
            self::record('badmail', ['person' => ['mail' => 'kein-mail']]),
            self::record('ok'),
            self::record('noid', ['booking' => ['id' => '']]),
            'kein-objekt',
        ];
        $res = M::mapRecords($recs, self::mapping());

        self::assertSame(['ok'], array_keys($res['rows']));
        $byCode = array_column($res['errors'], 'field', 'code');
        self::assertSame('shift_start', $byCode['invalid_datetime']);
        self::assertSame('shift_end', $byCode['end_before_start']);
        self::assertSame('helper_email', $byCode['invalid_email']);
        self::assertSame('booking_id', $byCode['duplicate_key']);
        self::assertArrayHasKey('record_not_object', $byCode);
    }

    public function testNameAloneDoesNotIdentifyAPerson(): void
    {
        $m = M::sanitize(['fields' => ['booking_id' => 'id', 'shift_start' => 's', 'shift_end' => 'e', 'helper_name' => 'n']]);
        self::assertContains('identity_unmapped', array_column(M::configProblems($m), 'code'));
        $res = M::mapRecords([['id' => '1', 's' => '2027-06-01T08:00:00', 'e' => '2027-06-01T09:00:00', 'n' => 'Anna Muster']], $m);
        self::assertSame([], $res['rows']);
        self::assertSame('missing_value', $res['errors'][0]['code']);
    }

    public function testCompletenessHints(): void
    {
        $m = M::sanitize(['records_path' => 'items', 'total_path' => 'meta.total', 'next_path' => 'meta.next']);
        self::assertTrue(M::extractRecords(['items' => [[], []], 'meta' => ['total' => 2, 'next' => null]], $m)['complete_hint']);
        self::assertFalse(M::extractRecords(['items' => [[], []], 'meta' => ['total' => 5, 'next' => null]], $m)['complete_hint']);
        self::assertFalse(M::extractRecords(['items' => [[], []], 'meta' => ['total' => 2, 'next' => '/p2']], $m)['complete_hint']);
        self::assertNull(M::extractRecords(['items' => [[]]], M::sanitize(['records_path' => 'items']))['complete_hint']);
        self::assertSame('records_path_not_list', M::extractRecords(['items' => ['a' => 1]], $m)['problem']);
    }

    public function testRejectsUnknownTargetsAndBrokenPaths(): void
    {
        foreach ([['fields' => ['password' => 'x']], ['fields' => ['booking_id' => 'a..b']], ['records_path' => '.x']] as $raw) {
            try {
                M::sanitize($raw);
                self::fail('Erwartet: InvalidArgumentException');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testEpochTimestampsAndNumericIds(): void
    {
        $m = M::sanitize(['fields' => ['booking_id' => 'id', 'shift_start' => 's', 'shift_end' => 'e', 'helper_key' => 'h', 'helper_name' => 'n']]);
        $res = M::mapRecords([['id' => 123, 's' => 1812096000, 'e' => 1812110400000, 'h' => 7, 'n' => 'Solo']], $m);
        self::assertSame([], $res['errors']);
        self::assertSame('123', $res['rows']['123']['booking_id']);
        self::assertSame('7', $res['rows']['123']['helper_key']);
        self::assertSame('', $res['rows']['123']['helper_first_name']);
        self::assertSame('Solo', $res['rows']['123']['helper_last_name']);
    }
}
