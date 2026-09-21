# UC-TIME-007-001 — Admin bulk-closes time for a project team meeting

@BABOK Related: BR-007; FR-TIME-007-002 (bulk member rows); FR-CAL-007-003
(classification); UC-CAL-007-001 (anchor flow this responds to).
Status: Approved — BABOK; implementation parks next stage.
Module: ksf_FA_Timesheets (subscriber VIA `ksf_event_closed`).

## Preconditions
- A task-bearing event with 3 members + 2 contractors just closed (broadcast
  received). Actor = PM-admin/project-admin.

## Main flow
1. Subscriber receives `ksf_event_closed`.
2. It issues the classification query; PM responder returns the 3 member emails.
3. Actor sees ONE form: 3 members pre-checked (event duration as qty);
   contractors not shown.
4. Actor unchecks 1 member, submits.
5. Exactly 2 `0_timesheets` rows created in ONE transaction; duplicates for any
   attendee already having a row for this event are skipped (guard).
6. Subscriber emits `time_entry_added` per row; done.

## Alternate flows
- **2a. Untracked meeting:** classification empty -> fallback all attendees
  shown; actor unchecks outsiders.
- **5a. Transaction failure:** all rows roll back; nothing partial persists.

## Postconditions
- One row per checked member; outsiders untimed on this task; idempotent.

## Acceptance
- ARI: 3 members -> uncheck 1 -> exactly 2 rows; one transaction.
- BON: re-delivery of the same broadcast -> 0 new rows.