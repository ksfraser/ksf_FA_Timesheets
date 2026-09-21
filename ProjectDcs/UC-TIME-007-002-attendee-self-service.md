# UC-TIME-007-002 — Regular attendee files their own time from a closed event

@BABOK Related: BR-007; FR-TIME-007-003 (self-service path); FR-CAL-007-003
(classification, for choosing the attendee's own track).
Status: Approved — BABOK; implementation parks next stage.
Module: ksf_FA_Timesheets.

## Preconditions
- An attendance event is closed (broadcast acked). Actor = a regular attendee
  WITHOUT close privilege; member of the team OR an external/contractor in a
  supporting role.

## Main flow
1. Actor opens the closed event's "log my time" action.
2. Subscriber checks classification: if actor is `external`, the form shows a
   project/task picker for THEIR OWN track; if `member`, the event's task_id is
   pre-set.
3. Actor confirms the pre-filled qty (event duration).
4. Exactly ONE `0_timesheets` row is created for the current user; second
   attempt -> 0 new rows (`(event_id, emp_id)` guard).
5. `time_entry_added` fires.

## Alternate flows
- **2a. Open event:** action refused, no write.
- **4a. Duplicate:** user already has a row for this event -> form reports
  "already logged", nothing created.

## Postconditions
- One self row on the attendee's own track; no admin needed; idempotent.

## Acceptance
- ARI: external attendee logs 1 row vs their own project; retry -> 0 new.