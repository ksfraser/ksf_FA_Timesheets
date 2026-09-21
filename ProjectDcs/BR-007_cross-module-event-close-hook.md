# BR-007 — Cross-module Event-Close → Timesheet + Expense Workflow

Status: Approved (BR-007, series after BR-006 cross-module DDL caching)
Scope : Cross-module — Calendar (anchor), Timesheets, TravelExpense,
        ProjectManagement, HRM, EmployeePay, Warehouse (subscriber)
Author: KSF (BABOK-v2 modeled)
Last  : 2026-09
@BABOK Related: anchors BR-006 (hook/cache ordering); consumed by the
FR docs that follow per module.

## Need (BABOK — What, not How)
When a Calendar event that tracks work/attendance is CLOSED, the attendees'
time and any money spent on that event must flow forward without the
employee being asked the same thing twice. The two consumer modules
(Timesheets, TravelExpense) must receive the SAME canonical payload
(that is, attacker-identical payload = same hook data) — via hooks + DTO —
so that:
  1. an attendee's time entry exists,
  2. their spend is attachable NEW-or-EXISTING (hotel/flights on an open
     report, then parking/coffee from this event appended),
and no module reaches into another's tables to satisfy either.

## In scope (this BR sets the boundary — each module changes its OWN data)
- Calendar   : fires the single close broadcast:
  `hook_invoke_all('ksf_event_closed', EventClosedDto)` — where the DTO
  carries `event_id, title, started/closed_at, location, project_id,
  task_id, attendee_emails[]`.
- Timesheets : subscriber via `hook_invoke('ksf_event_closed')` — ONE
  `0_timesheets` row per attendee with qty from the event duration; no
  sql in this BR, only the contract at which Timesheets acts.
- TravelExpense : subscriber — prompts NEW-OR-EXISTING expense report for
  the attendee; NEW starts a fresh header, EXISTING appends the event's
  spend lines onto the open report supplied by the user.
- PM         : subscriber — closes the linked task's logged time when the
  event is a task-bearing meeting (task_id non-null in DTO).
- HRM/EmployeePay : subscriber — sees nothing new except; pay-run may use
  the closes as "worked-window" evidence (read-only view, no writes here).
- Warehouse  : subscriber — receives the SAME DTO and MAY schedule nothing;
  it only registers `(event_id, warehouse_loc)` OPTIONALLY for pick/meeting
  hybrid events. Zero writes to calendar/timesheet tables.

## Membership partition (added — the close is member-scoped)
Closing must NOT auto-time outsiders/contractors/support staff against a
project task they are not on. When the admin/PM closes the entry, only project
TEAM MEMBERS are closed for time; outsiders/contractors/other teams attending in
a supporting role log time elsewhere (their own track) but MAY attach expenses.
Determination is a generic query, not hard-coded:

- **`ksf_event_classify_attendees`** (`hook_invoke_all`, read-only): any
  subscriber needing membership asks it; the authority for each track
  responds — PM (project task via `users.email → 0_fa_pm_assignments`), HRM
  (employee windows via `0_crm_persons.email → 0_hrm_contacts_employment`),
  CRM (known external contacts), Teams/Warehouse (optional).
- **Aggregation rule (deterministic):** no responder recognized ANY linkage ->
  fallback all attendees = member (generic meetings still prompt); otherwise
  `member` wins over `external`; UNCLASSIFIED = `external` (no auto-time on that
  track).
- Generic tracks: the DTO carries `linked_entities[]` +
  `event_type` so HRM trainings, org-team meetings, and pick hybrids reuse the
  SAME close/Klassification/subscriber shape — no Calendar-specific branching in
  any subscriber.

## Out of scope (guard rails — proven have-nots stay have-nots)
- No parallel "attendance" table — attendees come from Calendar's native
  0_fa_cal_invitees.
- No writing of timesheets/expense/leave tables from any module except the
  owning one.
- No duplicate of the hook payload: one DTO, hardlinked declarator, no
  per-module copies.
- BABOK FR docs: each module then writes its OWN FR-<MODULE>-<SEQ>-… doc
  (Timesheets bulk-entry, TravelExpense new-or-existing, Calendar close
  broadcast, PM task-closed) with @BABOK Related: BR-007 — BEFORE code.

## Acceptance criteria (cross-module)
1. Closing one event with N attendees creates exactly N timesheet rows
   (N <= 0 -> subscriber asserts no-op).
2. The SAME closed event offered to create expenses lets attendee choose
   NEW or APPEND to an existing open report the user supplies.
3. No module reads another's tables to satisfy (1) or (2).
4. Retriggering the close is idempotent: second broadcast creates nothing
   new beyond the first.
5. works across FA + standalone (bridge transports through the
   ksf-common-db DbConnectionInterface — same DTO, no FA global state).
6. Closing is member-scoped: only attendees classified as `member` by the
   classification hook receive auto-timed `0_timesheets` rows.
   Outsiders/contractors/support staff are NOT offered time against the
   closed task; they may attach expenses.
7. Untracked (generic) meetings without any responder fallback: all attendees
   treated as members (closer may uncheck outliers).
8. `linked_entities[]` in the DTO mirrors the entry's explicit FKs
   (`project_id`, `task_id`, `sales_order_id`) plus any module-supplied
   pairs so HRM training/org-team/pick-hybrid tracks reuse the same flow.
9. No module reads another module's tables to satisfy (1), (2), or (6)–(8).
