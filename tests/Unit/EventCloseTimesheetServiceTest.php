<?php
/**
 * EventCloseTimesheetServiceTest — BR-007 timesheets subscriber tests.
 *
 * Covers FR-TIME-007-002 (bulk member rows, one transaction, idempotent by
 * (event_id, employee_id), no-op retrigger) and FR-TIME-007-003 (self-service
 * row, external track override, closed-only) plus the FR-CAL-007-003
 * aggregation rules (untracked -> all member, member wins, unclassified is
 * external).
 *
 * The service talks to a FakeDb (in-memory connection) and an injected
 * hookInvoker that simulates PM/HRM/CRM responders, so no FA is required.
 *
 * @since 2.4.3
 * @BABOK Related: BR-007, FR-TIME-007-002, FR-TIME-007-003, FR-CAL-007-003
 */

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FA\Timesheets;

use ksfraser\FrontAccounting\Timesheets\Service\EventCloseTimesheetService;
use PHPUnit\Framework\TestCase;

/**
 * In-memory connection implementing the service's DbConnectionInterface shape.
 */
class FakeDb
{
    public $timesheets = array();
    public $entries = array();
    public $users = array();
    public $txLog = array();
    public $failNextEntryInsert = false;
    private $lastId = 0;

    private function nextId(): int
    {
        return ++$this->lastId;
    }

    private function isInsert(string $sql): bool
    {
        return strpos(ltrim($sql), 'INSERT INTO') === 0;
    }

    public function fetchAssoc(string $sql, array $params = array()): ?array
    {
        if ($this->isInsert($sql)) {
            return null;
        }
        if (strpos($sql, '0_time_entries') !== false) {
            foreach ($this->entries as $e) {
                if ((int) $e['event_id'] === (int) $params[0]
                    && (int) $e['event_employee_id'] === (int) $params[1]) {
                    return $e;
                }
            }
            return null;
        }
        if (strpos($sql, '0_timesheets') !== false) {
            foreach ($this->timesheets as $t) {
                if ((int) $t['employee_id'] === (int) $params[0]
                    && $t['period_start'] === $params[1]) {
                    return $t;
                }
            }
            return null;
        }
        if (strpos($sql, '0_users') !== false) {
            foreach ($this->users as $u) {
                if (strtolower($u['email']) === strtolower((string) $params[0])) {
                    return $u;
                }
            }
            return null;
        }
        return null;
    }

    public function fetchAll(string $sql, array $params = array()): array
    {
        return array();
    }

    public function fetchScalar(string $sql, array $params = array())
    {
        return null;
    }

    public function executeUpdate(string $sql, array $params = array()): bool
    {
        if (strpos($sql, '0_time_entries') !== false) {
            if ($this->failNextEntryInsert) {
                $this->failNextEntryInsert = false;
                throw new \RuntimeException('simulated db error');
            }
            $eventId = (int) $params[7];
            $employeeId = (int) $params[8];
            if ($eventId > 0 && $employeeId > 0) {
                foreach ($this->entries as $e) {
                    if ((int) $e['event_id'] === $eventId
                        && (int) $e['event_employee_id'] === $employeeId) {
                        throw new \RuntimeException('duplicate key uk_event_employee');
                    }
                }
            }
            $this->entries[] = array(
                'id' => $this->nextId(),
                'timesheet_id' => (int) $params[0],
                'entry_date' => $params[1],
                'hours' => $params[2],
                'hour_type' => 'regular',
                'project_id' => $params[3],
                'project_stage_id' => $params[4],
                'project_activity_id' => $params[5],
                'description' => $params[6],
                'event_id' => $eventId,
                'event_employee_id' => $employeeId,
                'billing_rule' => 'cost',
                'status' => 'draft',
            );
            return true;
        }
        if (strpos($sql, '0_timesheets') !== false) {
            $id = $this->nextId();
            $this->timesheets[] = array(
                'id' => $id,
                'employee_id' => (int) $params[0],
                'period_start' => $params[1],
                'period_end' => $params[2],
                'status' => 'draft',
                'submitted_by' => $params[3],
                'created_by' => $params[4],
            );
            return true;
        }
        return false;
    }

    public function lastInsertId(): int
    {
        return $this->lastId;
    }

    public function beginTransaction(): void
    {
        $this->txLog[] = 'begin';
    }

    public function commit(): void
    {
        $this->txLog[] = 'commit';
    }

    public function rollBack(): void
    {
        $this->txLog[] = 'rollback';
    }
}

class EventCloseTimesheetServiceTest extends TestCase
{
    /** @var FakeDb */
    private $db;

    protected function setUp(): void
    {
        $this->db = new FakeDb();
        $this->db->users[1] = array('id' => 1, 'email' => 'alice@x.io');
        $this->db->users[2] = array('id' => 2, 'email' => 'bob@x.io');
        $this->db->users[3] = array('id' => 3, 'email' => 'carol@x.io');
    }

    private function closedPayload(array $attendees = null, int $eventId = 5): array
    {
        return array(
            'event_id'        => $eventId,
            'title'           => 'Sprint Planning',
            'project_id'      => '17',
            'task_id'         => '9',
            'started_at'      => '2026-09-21 09:00:00',
            'closed_at'       => '2026-09-21 10:30:00',
            'closed_by'       => 'admin',
            'attendee_emails' => $attendees ?? array(
                'alice@x.io', 'bob@x.io', 'carol@x.io',
                'ext1@x.io', 'ext2@x.io',
            ),
        );
    }

    private function pmResponder(): \Closure
    {
        return function (string $method, array &$data, array $opts): void {
            if ($method !== 'ksf_event_classify_attendees') {
                return;
            }
            $data['classification']['member'][]   = 'alice@x.io';
            $data['classification']['member'][]   = 'bob@x.io';
            $data['classification']['member'][]   = 'carol@x.io';
            $data['classification']['external'][] = 'ext1@x.io';
            $data['classification']['external'][] = 'ext2@x.io';
        };
    }

    private function service(int $actingUserId = 0, ?callable $invoker = null): EventCloseTimesheetService
    {
        return new EventCloseTimesheetService($this->db, '0_', $actingUserId, $invoker);
    }

    // ---------------------------------------------------------------
    // FR-TIME-007-002 — bulk
    // ---------------------------------------------------------------

    /**
     * ARI: 3 members + 2 contractors -> exactly 3 member rows created.
     *
     * @since 2.4.3
     */
    public function testBulkCreatesExactlyMemberRows(): void
    {
        $result = $this->service()->createMemberBulk($this->closedPayload());

        $this->assertSame(3, $result['created']);
        $this->assertSame(3, count($this->db->entries));
        $this->assertSame(3, count($this->db->timesheets));
        foreach ($this->db->entries as $e) {
            $this->assertSame(5, (int) $e['event_id']);
            $this->assertSame('2026-09-21', $e['entry_date']);
        }
    }

    /**
     * Bulk writes happen in ONE transaction (all-or-nothing).
     *
     * @since 2.4.3
     */
    public function testBulkIsTransactional(): void
    {
        $this->service()->createMemberBulk($this->closedPayload());

        $this->assertSame(array('begin', 'commit'), $this->db->txLog);
    }

    /**
     * CAN: retriggering the same event with rows already present -> 0 new.
     *
     * @since 2.4.3
     */
    public function testBulkRetriggerAddsNothing(): void
    {
        $svc = $this->service();

        $svc->createMemberBulk($this->closedPayload());
        $second = $svc->createMemberBulk($this->closedPayload());

        $this->assertSame(0, $second['created']);
        $this->assertSame(3, count($this->db->entries));
    }

    /**
     * BRO: untracked meeting (no responder) -> all attendees are members.
     *
     * @since 2.4.3
     */
    public function testUntrackedMeetingTreatsEveryoneAsMember(): void
    {
        $result = $this->service()->createMemberBulk($this->closedPayload());

        // ext1/ext2 are not resolvable users -> skipped
        $this->assertSame(3, $result['created']);
        $this->assertSame(3, count($this->db->entries));
    }

    /**
     * Rule 2: a responder tagging the same email member AND external -> member.
     *
     * @since 2.4.3
     */
    public function testMemberWinsOverExternal(): void
    {
        $invoker = function (string $method, array &$data, array $opts): void {
            $data['classification']['member'][]   = 'alice@x.io';
            $data['classification']['member'][]   = 'bob@x.io';
            $data['classification']['external'][] = 'alice@x.io';
        };

        $svc = new EventCloseTimesheetService($this->db, '0_', 0, $invoker);
        $payload = $this->closedPayload(array('alice@x.io', 'bob@x.io', 'carol@x.io'), 7);

        $out = $svc->createMemberBulk($payload);

        $this->assertSame(2, $out['created']); // alice + bob; carol (unclassified) is external
        $employees = array_column($this->db->entries, 'event_employee_id');
        $this->assertContains(1, $employees);
        $this->assertContains(2, $employees);
        $this->assertNotContains(3, $employees);
    }

    /**
     * A bulk failure rolls the whole batch back (no partial rows).
     *
     * @since 2.4.3
     */
    public function testBulkFailureRollsBack(): void
    {
        $this->db->failNextEntryInsert = true;

        $this->expectException(\RuntimeException::class);

        try {
            $this->service()->createMemberBulk($this->closedPayload());
        } finally {
            $this->assertSame(array('begin', 'rollback'), $this->db->txLog);
        }
    }

    // ---------------------------------------------------------------
    // FR-TIME-007-003 — self-service
    // ---------------------------------------------------------------

    /**
     * BON: member self-service uses the event track; idempotent retry -> no rows.
     *
     * @since 2.4.3
     */
    public function testSelfServiceMemberUsesEventTrack(): void
    {
        $payload = $this->closedPayload(array('alice@x.io'), 9);
        $payload['closed_by'] = 'alice@x.io';
        $invoker = function (string $method, array &$data, array $opts): void {
            $data['classification']['member'][] = 'alice@x.io';
        };

        $svc = new EventCloseTimesheetService($this->db, '0_', 1, $invoker);
        $first = $svc->createSelfService($payload);
        $second = $svc->createSelfService($payload);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, count($this->db->entries));

        $entry = $this->db->entries[0];
        $this->assertSame(17, $entry['project_id']);
        $this->assertSame(9, $entry['project_stage_id']);
        $this->assertSame(9, (int) $entry['event_id']);
    }

    /**
     * ARI: external attendee must supply their own track (no event task leak).
     *
     * @since 2.4.3
     */
    public function testSelfServiceExternalRequiresOwnTrack(): void
    {
        $payload = $this->closedPayload(array('ext1@x.io'), 9);
        $payload['closed_by'] = 'ext1@x.io';
        $invoker = function (string $method, array &$data, array $opts): void {
            $data['classification']['external'][] = 'ext1@x.io';
        };

        $svc = new EventCloseTimesheetService($this->db, '0_', 9, $invoker);

        $this->expectException(\LogicException::class);
        $svc->createSelfService($payload);
    }

    /**
     * External attendee WITH own track override writes to her own project.
     *
     * @since 2.4.3
     */
    public function testSelfServiceExternalUsesOverrideTrack(): void
    {
        $payload = $this->closedPayload(array('ext1@x.io'), 9);
        $payload['closed_by'] = 'ext1@x.io';
        $payload['project_id'] = '17';
        $invoker = function (string $method, array &$data, array $opts): void {
            $data['classification']['external'][] = 'ext1@x.io';
        };

        $svc = new EventCloseTimesheetService($this->db, '0_', 9, $invoker);
        $result = $svc->createSelfService($payload, array(
            'project_id'          => 22,
            'project_stage_id'    => 3,
            'project_activity_id' => 7,
        ));

        $this->assertSame(1, $result['created']);
        $entry = $this->db->entries[0];
        $this->assertSame(22, $entry['project_id']);
        $this->assertSame(3, $entry['project_stage_id']);
        $this->assertSame(7, $entry['project_activity_id']);
        $this->assertSame(9, (int) $entry['event_employee_id']);
    }

    /**
     * CAN: neither path writes on an OPEN (unclosed) event.
     *
     * @since 2.4.3
     */
    public function testOpenEventIsRefusedEverywhere(): void
    {
        $open = $this->closedPayload();
        unset($open['closed_at']);

        $this->expectException(\LogicException::class);
        $this->service()->createMemberBulk($open);
    }

    // ---------------------------------------------------------------
    // Aggregation rules (FR-CAL-007-003)
    // ---------------------------------------------------------------

    /**
     * klassifikation result is deterministic and case-insensitive.
     *
     * @since 2.4.3
     */
    public function testClassificationIsCaseInsensitive(): void
    {
        $payload = $this->closedPayload(array('ALICE@X.IO'), 1);
        $invoker = function (string $method, array &$data, array $opts): void {
            $data['classification']['member'][] = 'alice@x.io';
        };

        $svc = new EventCloseTimesheetService($this->db, '0_', 0, $invoker);
        $classification = $svc->classify($payload);

        $this->assertSame(array('alice@x.io'), $classification['member']);
        $this->assertSame(array(), $classification['external']);
    }
}