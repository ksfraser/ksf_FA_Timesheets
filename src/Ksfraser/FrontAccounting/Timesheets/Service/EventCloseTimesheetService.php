<?php
/**
 * EventCloseTimesheetService — BR-007 timesheets subscriber logic.
 *
 * Turns a closed calendar event (ksf_event_closed) into `0_time_entries`
 * rows for the event's PROJECT TEAM MEMBERS, using the cross-module
 * membership protocol (FR-CAL-007-003):
 *
 *     1. The caller runs  hook_invoke_all('ksf_event_classify_attendees')
 *        with $payload['dto'] set to the closed event; PM/HRM respond as
 *        'member', CRM responds as 'external' (append-by-reference).
 *     2. aggregate() applies the ARCH-007 rules:
 *          no responder recognised any linkage   -> all attendees = member
 *          member wins over external (same email) -> kept as member
 *          UNCLASSIFIED = external                 -> never auto-timed
 *
 * Bulk path  (FR-TIME-007-002): ONE row per member attendee in a single
 * transaction, idempotent by unique (event_id, employee_id) on
 * 0_time_entries. Retriggers create nothing new.
 * Self path  (FR-TIME-007-003): exactly one row for the acting employee;
 * external attendees may override the track with their OWN project/task.
 *
 * The service talks ONLY to Timesheets' OWN tables via the injected
 * connection ($db) — mirroring TimeEntryService/TimesheetService — so tests
 * inject a fake adapter and the FA runtime uses a native db_* adapter.
 *
 * PHP 7.3+ compatible (this module's PHP-Version floor).
 *
 * @since 2.4.3
 * @BABOK Related: BR-007, FR-TIME-007-002, FR-TIME-007-003, FR-CAL-007-003
 */

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Timesheets\Service;

/**
 * @package ksfraser\FrontAccounting\Timesheets\Service
 */
class EventCloseTimesheetService
{
    /** @var object */
    private $db;
    /** @var string */
    private $prefix;
    /** @var int */
    private $actingUserId;
    /** @var callable */
    private $hookInvoker;

    /**
     * @param object        $db           Connection: fetchAssoc/fetchAll/fetchScalar/
     *                                    executeUpdate/lastInsertId/beginTransaction/
     *                                    commit/rollBack (FA db_* or PDO adapter).
     * @param string        $prefix       Table prefix (e.g. "0_").
     * @param int           $actingUserId FA employee/user acting (self-service emp;
     *                                    bulk = the closer).
     * @param callable|null $hookInvoker  fn(string $method, array &$data, array $opts)
     */
    public function __construct(
        $db,
        string $prefix = '0_',
        int $actingUserId = 0,
        ?callable $hookInvoker = null
    ) {
        $this->db = $db;
        $this->prefix = $prefix;
        $this->actingUserId = $actingUserId;
        $this->hookInvoker = $hookInvoker ?: array(self::class, 'dispatchHook');
    }

    /**
     * Default dispatcher: FA's procedural hook system when present.
     *
     * @param string $method
     * @param array  $data  by-reference accumulation
     * @param array  $opts
     */
    public static function dispatchHook(string $method, array &$data, array $opts = array()): void
    {
        if (function_exists('hook_invoke_all')) {
            hook_invoke_all($method, $data, $opts);
        }
    }

    /**
     * Run the membership broadcast and aggregate (FR-CAL-007-003).
     *
     * The payload's 'dto' slot keeps the ORIGINAL object so PM/HRM/CRM
     * responders (built against EventClosedDto) can read it unchanged.
     *
     * @param object|array $dto Closed-event DTO, or its array form
     * @return array{member: string[], external: string[]} lower-cased emails
     */
    public function classify($dto): array
    {
        $payload = array(
            'dto'            => $dto,
            'classification' => array(
                'member'   => array(),
                'external' => array(),
            ),
        );
        $opts = array('event_id' => $this->dtoValue($dto, 'event_id'));

        $invoker = $this->hookInvoker;
        $invoker('ksf_event_classify_attendees', $payload, $opts);

        return $this->aggregate($payload['classification'], $this->dtoAttendees($dto));
    }

    /**
     * FR-TIME-007-002 bulk path: create N member rows in ONE transaction.
     *
     * Only member attendees are written; external and unresolvable attendees
     * are skipped. Any failure rolls the whole batch back (all-or-nothing).
     *
     * @param object|array $dto Closed-event DTO / array
     * @return array{created: int, skipped: string[]}
     * @throws \LogicException on missing event_id or an open (unclosed) event
     */
    public function createMemberBulk($dto): array
    {
        $payload = $this->normalizeDto($dto);
        $eventId = $this->dtoEventId($dto);
        if (!$this->isClosed($dto)) {
            throw new \LogicException('EventCloseTimesheetService: event not closed');
        }

        $classification = $this->classify($dto);
        $rows = $this->buildRows($payload, $eventId, $classification['member'], false);

        if (empty($rows)) {
            return array('created' => 0, 'skipped' => array());
        }

        $created = $this->writeAll($rows);
        return array('created' => $created, 'skipped' => array());
    }

    /**
     * FR-TIME-007-003 self path: exactly ONE row for the acting employee.
     *
     * External attendees MUST supply their own track (overrideTrack);
     * member attendees use the event's track. Only after the event closed.
     *
     * @param object|array $dto           Closed-event DTO / array
     * @param array|null   $overrideTrack ['project_id','project_stage_id','project_activity_id']
     *                                     integers, used for external attendees
     * @return array{created: int, timesheet_id: int|null, entry_id: int|null}
     * @throws \LogicException on open event, missing employee, or missing track
     */
    public function createSelfService($dto, ?array $overrideTrack = null): array
    {
        $payload = $this->normalizeDto($dto);
        $eventId = $this->dtoEventId($dto);
        if (!$this->isClosed($dto)) {
            throw new \LogicException('EventCloseTimesheetService: event not closed');
        }
        if ($this->actingUserId <= 0) {
            throw new \LogicException('EventCloseTimesheetService: no acting employee');
        }

        $classification = $this->classify($dto);
        $self = strtolower(trim((string) $this->dtoValue($dto, 'closed_by')));
        $isExternal = $this->isExternal($self, $classification);

        $track = $isExternal
            ? $this->overrideTrack($overrideTrack)
            : array(
                'project_id'         => $this->intOrNull($payload['project_id']),
                'project_stage_id'   => $this->intOrNull($payload['task_id']),
                'project_activity_id' => null,
            );

        $row = $this->buildEntryInfo($payload, $this->actingUserId, $eventId, $track);

        $this->db->beginTransaction();
        try {
            $timesheetId = $this->getOrCreateTimesheetRow($row['employee_id'], $row['week_start']);
            $entryId = $this->addEventEntry(
                $timesheetId, $row['employee_id'], $eventId,
                $row['entry_date'], $row['hours'],
                $row['project_id'], $row['project_stage_id'], $row['project_activity_id'],
                $payload['title'] ?? ''
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return array(
            'created'      => $entryId !== null ? 1 : 0,
            'timesheet_id' => $timesheetId,
            'entry_id'     => $entryId,
        );
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    /**
     * Write every pending member row in one transaction (all-or-nothing).
     *
     * @param array[] $rows
     * @return int rows actually created (0 on an already-timed retrigger)
     */
    private function writeAll(array $rows): int
    {
        $this->db->beginTransaction();
        try {
            $created = 0;
            foreach ($rows as $row) {
                $timesheetId = $this->getOrCreateTimesheetRow($row['employee_id'], $row['week_start']);
                $entryId = $this->addEventEntry(
                    $timesheetId, $row['employee_id'], $row['event_id'],
                    $row['entry_date'], $row['hours'],
                    $row['project_id'], $row['project_stage_id'], $row['project_activity_id'],
                    $row['description']
                );
                if ($entryId !== null) {
                    $created++;
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $created;
    }

    /**
     * Build one pending row per member email (bulk, skipping unresolved).
     *
     * @param array  $payload   normalized DTO array
     * @param int    $eventId
     * @param string[] $members member emails
     * @param bool   $selfOnly  resolve only the acting employee
     * @return array[]
     */
    private function buildRows(array $payload, int $eventId, array $members, bool $selfOnly): array
    {
        $rows = array();
        foreach (array_unique($members) as $email) {
            $employeeId = $selfOnly ? $this->actingUserId : $this->resolveEmployeeByEmail($email);
            if ($employeeId <= 0) {
                continue;
            }
            if ($this->alreadyTimed($eventId, $employeeId)) {
                continue;
            }
            $rows[] = $this->buildEntryInfo(
                $payload, $employeeId, $eventId,
                array(
                    'project_id'          => $this->intOrNull($payload['project_id']),
                    'project_stage_id'    => $this->intOrNull($payload['task_id']),
                    'project_activity_id' => null,
                )
            );
        }
        return $rows;
    }

    /**
     * Deterministic ARCH-007 aggregation (same rule set as the Calendar
     * package's AttendeeClassifier, kept self-contained for decoupling).
     *
     * @param array<string,mixed> $classification ['member' string[], 'external' string[]]
     * @param string[]            $attendees      all invitee emails
     * @return array{member: string[], external: string[]}
     */
    private function aggregate(array $classification, array $attendees): array
    {
        $member = $this->unique($classification['member'] ?? array());
        $external = $this->unique($classification['external'] ?? array());
        $attendees = $this->unique($attendees);

        // Rule 1: nobody recognised any linkage -> everyone is a member.
        if (empty($member) && empty($external)) {
            return array('member' => $attendees, 'external' => array());
        }

        // Rule 2: member wins over external.
        $external = array_values(array_diff($external, $member));

        // Rule 3: unclassified is external (never auto-timed).
        $classified = array_merge($member, $external);
        $unclassified = array_values(array_diff($attendees, $classified));

        return array(
            'member'   => $member,
            'external' => array_values(array_merge($external, $unclassified)),
        );
    }

    /**
     * @param array $classification
     * @param string $email
     * @return bool true when the email is classified external
     */
    private function isExternal(string $email, array $classification): bool
    {
        $external = $this->unique($classification['external'] ?? array());
        return $email !== '' && in_array($email, $external, true);
    }

    /**
     * Load an existing 0_timesheets draft for (employee, week) or create it.
     *
     * No eligibility gate — the bulk path is privileged (the close action),
     * the self path is the employee themselves.
     *
     * @param int    $employeeId
     * @param string $weekStart YYYY-MM-DD
     * @return int timesheet id
     */
    private function getOrCreateTimesheetRow(int $employeeId, string $weekStart): int
    {
        $sql = "SELECT id FROM {$this->prefix}timesheets
                WHERE employee_id = ? AND period_start = ?";
        $row = $this->db->fetchAssoc($sql, array($employeeId, $weekStart));

        if ($row && !empty($row['id'])) {
            return (int) $row['id'];
        }

        $periodEnd = $this->weekEnd($weekStart);
        $insert = "INSERT INTO {$this->prefix}timesheets
                   (employee_id, period_start, period_end, status, submitted_by, created_by)
                   VALUES (?, ?, ?, 'draft', ?, ?)";
        $this->db->executeUpdate($insert, array(
            $employeeId, $weekStart, $periodEnd, $this->actingUserId, $this->actingUserId,
        ));

        return (int) $this->db->lastInsertId();
    }

    /**
     * Insert one 0_time_entries row carrying the (event_id, employee_id)
     * guard and fire the existing time_entry_added emitter.
     *
     * @return int|null entry id created, or null when the row already exists
     */
    private function addEventEntry(
        int $timesheetId,
        int $employeeId,
        int $eventId,
        string $entryDate,
        float $hours,
        ?int $projectId,
        ?int $projectStageId,
        ?int $projectActivityId,
        string $description
    ): ?int {
        if ($this->alreadyTimed($eventId, $employeeId)) {
            return null;
        }

        $sql = "INSERT INTO {$this->prefix}time_entries
                (timesheet_id, entry_date, hours, hour_type, project_id, project_stage_id,
                 project_activity_id, description, event_id, event_employee_id,
                 billing_rule, billing_rate, is_billable, status, created_by, updated_by)
                VALUES (?, ?, ?, 'regular', ?, ?, ?, ?, ?, ?, 'cost', NULL, 1, 'draft', ?, ?)";
        $this->db->executeUpdate($sql, array(
            $timesheetId, $entryDate, $hours,
            $projectId, $projectStageId, $projectActivityId,
            $description, $eventId, $employeeId,
            $this->actingUserId, $this->actingUserId,
        ));

        $entryId = (int) $this->db->lastInsertId();

        $emitData = array(
            'timesheet_id' => $timesheetId,
            'entry_id'     => $entryId,
            'project_id'   => $projectId,
            'activity_id'  => $projectActivityId,
            'hours'        => $hours,
            'date'         => $entryDate,
            'event_id'     => $eventId,
            'employee_id'  => $employeeId,
        );
        $invoker = $this->hookInvoker;
        $invoker('time_entry_added', $emitData, array());

        return $entryId;
    }

    /**
     * @param int $eventId
     * @param int $employeeId
     * @return bool whether a row for (event, employee) already exists
     */
    private function alreadyTimed(int $eventId, int $employeeId): bool
    {
        $sql = "SELECT id FROM {$this->prefix}time_entries
                WHERE event_id = ? AND event_employee_id = ? LIMIT 1";
        $row = $this->db->fetchAssoc($sql, array($eventId, $employeeId));
        return (bool) $row;
    }

    /**
     * Resolve an attendee email to an FA employee id (0_users.email).
     *
     * @param string $email
     * @return int
     */
    private function resolveEmployeeByEmail(string $email): int
    {
        $sql = "SELECT id FROM {$this->prefix}users WHERE LOWER(email) = LOWER(?) LIMIT 1";
        $row = $this->db->fetchAssoc($sql, array($email));
        return $row && !empty($row['id']) ? (int) $row['id'] : 0;
    }

    /**
     * Build a fully-resolved row description for one (event, employee).
     *
     * @param array $payload normalized DTO
     * @param int   $employeeId
     * @param int   $eventId
     * @param array $track     ['project_id','project_stage_id','project_activity_id']
     * @return array
     */
    private function buildEntryInfo(array $payload, int $employeeId, int $eventId, array $track): array
    {
        $hours = $this->eventHours($payload);
        $entryDate = $this->eventDate($payload);

        return array(
            'employee_id'         => $employeeId,
            'event_id'            => $eventId,
            'entry_date'          => $entryDate,
            'week_start'          => $this->weekStart($entryDate),
            'hours'               => $hours,
            'project_id'          => $track['project_id'] ?? null,
            'project_stage_id'    => $track['project_stage_id'] ?? null,
            'project_activity_id' => $track['project_activity_id'] ?? null,
            'description'         => 'Auto-created from closed event #' . $eventId
                                      . (isset($payload['title']) && $payload['title'] !== ''
                                          ? ' (' . $payload['title'] . ')'
                                          : ''),
        );
    }

    /**
     * @param array $payload  normalized DTO
     * @return float event duration in hours, clamped to 0.01–24
     */
    private function eventHours(array $payload): float
    {
        $from = strtotime((string) ($payload['started_at'] ?? ''));
        $to = strtotime((string) ($payload['closed_at'] ?? ''));
        if ($from === false || $to === false || $to <= $from) {
            return 0.01;
        }
        $hours = round(($to - $from) / 3600, 2);
        if ($hours <= 0) {
            return 0.01;
        }
        return min($hours, 24.0);
    }

    /**
     * @param array $payload normalized DTO
     * @return string YYYY-MM-DD entry date (closure date)
     */
    private function eventDate(array $payload): string
    {
        $closed = strtotime((string) ($payload['closed_at'] ?? ''));
        if ($closed === false) {
            return date('Y-m-d');
        }
        return date('Y-m-d', $closed);
    }

    /**
     * @param string $date YYYY-MM-DD
     * @return string Monday of that week, YYYY-MM-DD
     */
    private function weekStart(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        $day = (int) date('N', $ts); // 1 = Monday
        return date('Y-m-d', $ts - ($day - 1) * 86400);
    }

    /**
     * @param string $start YYYY-MM-DD
     * @return string Sunday of that week, YYYY-MM-DD
     */
    private function weekEnd(string $start): string
    {
        return date('Y-m-d', strtotime($start . ' + 6 days'));
    }

    /**
     * Validate the external attendee's own track override.
     *
     * @param array|null $overrideTrack
     * @return array track
     * @throws \LogicException when no track was provided
     */
    private function overrideTrack(?array $overrideTrack): array
    {
        if ($overrideTrack === null) {
            throw new \LogicException(
                'EventCloseTimesheetService: external attendee must provide their own track'
            );
        }
        return array(
            'project_id'          => $this->intOrNull($overrideTrack['project_id'] ?? null),
            'project_stage_id'    => $this->intOrNull($overrideTrack['project_stage_id'] ?? null),
            'project_activity_id' => $this->intOrNull($overrideTrack['project_activity_id'] ?? null),
        );
    }

    /**
     * Normalise the DTO to a plain array.
     *
     * @param object|array $dto
     * @return array
     */
    private function normalizeDto($dto): array
    {
        if (is_array($dto)) {
            return $dto;
        }
        if (is_object($dto) && method_exists($dto, 'toArray')) {
            $arr = $dto->toArray();
            return is_array($arr) ? $arr : array();
        }
        return array();
    }

    /**
     * @param object|array $dto
     * @param string       $key
     * @return mixed
     */
    private function dtoValue($dto, string $key)
    {
        $norm = $this->normalizeDto($dto);
        return $norm[$key] ?? $norm[strtolower($key)] ?? null;
    }

    /**
     * @param object|array $dto
     * @return string[]
     */
    private function dtoAttendees($dto): array
    {
        $emails = $this->dtoValue($dto, 'attendee_emails');
        return is_array($emails) ? $emails : array();
    }

    /**
     * @param object|array $dto
     * @return int
     */
    private function dtoEventId($dto): int
    {
        $id = $this->dtoValue($dto, 'event_id');
        if ($id === null && ($normalized = $this->normalizeDto($dto)) !== array()) {
            $id = $normalized['id'] ?? null;
        }
        return (int) $id;
    }

    /**
     * @param object|array $dto
     * @return bool
     */
    private function isClosed($dto): bool
    {
        $closedAt = $this->dtoValue($dto, 'closed_at');
        return !empty($closedAt);
    }

    /**
     * @param string[] $list
     * @return string[] lower-cased, de-duplicated
     */
    private function unique(array $list): array
    {
        $seen = array();
        foreach ($list as $email) {
            if (!is_scalar($email)) {
                continue;
            }
            $email = strtolower(trim((string) $email));
            if ($email === '') {
                continue;
            }
            $seen[$email] = true;
        }
        return array_keys($seen);
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    private function intOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int === 0 ? null : $int;
    }
}