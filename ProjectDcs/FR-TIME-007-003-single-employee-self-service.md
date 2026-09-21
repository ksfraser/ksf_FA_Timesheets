# FR-TIME-007-003 — Single-employee self-service path on an already-closed event

@BABOK Related: BR-007; consumed by FR-TIME-007-002 (the bulk path it complements);
          FR-CAL-007-003 (classification, to detect the attendee's own track).
@UML : Timesheets/subscriber -> per-attendee on `ksf_event_closed` (no close privilege)
Status: Approved — BABOK; implementation parks next stage.
Module: ksf_FA_Timesheets (owner of 0_timesheets writes).

## Need (BABOK What-not-How)
A regular attendee does NOT have close privilege and is never bulk-timed
(FR-TIME-007-002 requires PM-admin). They still worked the event. They must be
able to file their OWN single time entry against their own track without an
admin, and without pushing the close through the bulk flow.

## Requirement
1. When an attendance/work event is CLOSED, each attendee WITHOUT close
   privilege gets a self-service entry point ("log my time from this event").
2. The action creates exactly ONE `0_timesheets` row for the CURRENT user
   (emp resolved from the session/`user_id`), qty pre-filled from the event
   duration, `(event_id, emp_id)` idempotency guard — a second attempt creates
   nothing.
3. If the attendee is `external` to the event's track (FR-CAL-007-003), the
   self-service form lets them pick their OWN project/task for the row instead
   of the event's `task_id` (supporting role logs against their own track).
4. Member or external, the row is written to Timesheets' OWN tables only, in a
   single transaction, and fires the existing `time_entry_added` emitter.
5. This path is available ONLY after the event is closed (no self-timing on
   open events).

## Acceptance
- ARI: external attendee on a closed task meeting logs their own time against
  their own project — exactly 1 row, correct project/task, idempotent guard
  active.
- BON: member attendee uses the pre-set task; second attempt -> 0 new rows.
- CAN: attempting the action on an OPEN event is refused with no write.