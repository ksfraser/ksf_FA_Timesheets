# FR-TIME-001-001 - Timesheet Entry

## Functional Requirement

**Module**: Timesheets
**Priority**: P0 - Critical
**Status**: Proposed
**Integration**: Hook-based

### Description

Create and manage timesheets with project/stage/activity linkage.

### Acceptance Criteria

| ID | Criteria | Hook |
|----|----------|------|
| AC-001 | Create timesheet for period | - |
| AC-002 | Add time entries with project/stage/activity | Emit: time_entry_added |
| AC-003 | Validate activity available for stage | Query: project_activity_validate |
| AC-004 | Get billing rule for activity | Query: time_get_billing_rule |
| AC-005 | Auto-calculate overtime | - |
| AC-006 | Submit for approval | Emit: timesheet_submitted |

### Hooks

```php
// Query: Validate activity for project stage
$result = hook_invoke_first('project_activity_validate', [
    'project_id' => $projectId,
    'stage_id' => $stageId,
    'activity_id' => $activityId,
    'user_id' => $userId,
]);
// Returns: ['valid' => true] or ['valid' => false, 'reason' => 'Stage not active']

// Query: Get billing rule for time entry
$result = hook_invoke_first('time_get_billing_rule', [
    'project_id' => $projectId,
    'activity_id' => $activityId,
]);
// Returns: ['billable' => true, 'rate' => 150.00, 'rule' => 'cost_plus', 'currency' => 'USD']

// Emit: Timesheet submitted
hook_invoke_all('timesheet_submitted', [
    'timesheet_id' => $timesheetId,
    'employee_id' => $userId,
    'period_start' => '2026-09-01',
    'period_end' => '2026-09-07',
    'total_hours' => 40.00,
    'regular_hours' => 40.00,
    'overtime_hours' => 0.00,
]);
```

### Overtime Calculation

```php
// Rules:
// Daily OT: hours > 8 per day
// Weekly OT: hours > 40 per week
// Weekly OT2: hours > 60 per week (2x)

$dailyHours = [8, 9, 8, 8, 8, 0, 0]; // Mon-Sun
$regular = 0;
$ot1 = 0;
$ot2 = 0;

foreach ($dailyHours as $dayHours) {
    if ($dayHours <= 8) {
        $regular += $dayHours;
    } else {
        $regular += 8;
        $remaining = $dayHours - 8;
        if ($ot2 > 0) {
            $ot2 += $remaining;
        } elseif ($ot1 > 0 || $regular > 40) {
            $ot1 += $remaining;
            if ($regular + $ot1 > 60) {
                $excess = $regular + $ot1 - 60;
                $ot1 -= $excess;
                $ot2 += $excess;
            }
        } else {
            $ot1 += $remaining;
        }
    }
}
```
