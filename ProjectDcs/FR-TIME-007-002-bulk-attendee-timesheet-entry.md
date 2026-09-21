# FR-TIME-007-002 — Bulk timesheet entry: create N member rows in one commit

@BABOK Related: anchors BR-007 (cross-module event-close workflow); feeds
          FR-TIME-007-003 (single-employee path). Membership scoping per
          FR-CAL-007-003 (classification protocol).
@UML   : Timesheets/subscriber -> on `ksf_event_closed`
Status: Approved — BABOK; implementation parks next stage.
Module: ksf_FA_Timesheets (owner of 0_timesheets writes; subscriber VIA hook).

## Need (BABOK What-not-How)
An admin/PM closing a meeting must be able to create the timesheet entry
for EVERY PROJECT TEAM MEMBER in one action — not by opening N forms.
Outsiders/contractors/support staff attending that meeting must NOT be
auto-timed against the closed task (they log elsewhere); they are excluded
from the bulk form.

## Requirement
On receiving `ksf_event_closed` from a user with PM-admin/project-admin
privilege:
1. The subscriber FIRST resolves membership via
   `hook_invoke_all('ksf_event_classify_attendees', $dto, $opts)` and applies
   the FR-CAL-007-003 aggregation rule.
2. Show ONE create-form listing each **member** attendee as a checkbox
   (defaulted checked), event duration pre-filled as the worked qty. Non-member
   (external) attendees are NOT offered a row for this task.
3. Untracked meetings (no responder classified anyone): fallback treats all
   attendees as members; the closer may uncheck outliers.
4. Submitting creates ONE `0_timesheets` row per checked attendee in a
   single transaction (all-or-nothing).
5. The entry's details (task_id, project, date-range) come from the DTO;
   the entry's OWNER is the module's own tables only.
6. A duplicate post (retrigger) inserts nothing new — idempotent by
   `(event_id, emp_id)` guard.

## Acceptance
- ARI: closing an event attended by 3 members + 2 contractors -> exactly 3
  member rows offered; exactly 3 rows created; one transaction; any failure
  rolls all back.
- BON: 0 members checked -> 0 rows, no error (no-op).
- BRO: untracked meeting, no responder -> all N attendees offered (fallback);
  closer unchecks 1 -> N-1 rows.
- CAN: retriggering the same event with all 3 already present -> 0 new rows.
