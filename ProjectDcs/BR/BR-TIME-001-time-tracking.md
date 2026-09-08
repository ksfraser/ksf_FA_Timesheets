# BR-TIME-001 - Time Tracking System

## Business Requirement

**Module**: ksf_FA_Timesheets
**Status**: Proposed (enhancement)
**Integration**: Hook-based (hook_invoke_all)

### Problem Statement

Current timesheet module lacks:
- Activity codes constrained by project stage
- Integration with project templates
- Activity-based billing rules
- Overtime calculation
- Integration with expense tracking

### Scope

#### In Scope
1. Timesheet entry with project/stage/activity
2. Activity selection constrained by project stage
3. Time entry billing rules (from contract)
4. Overtime rules (regular, OT1.5, OT2.0)
5. Approval workflow via hooks
6. Payroll export integration
7. Utilization reports

#### Out of Scope
1. Scheduling (use MRP)
2. Resource leveling
3. Mobile offline sync

### Hook Integration Points

```php
// Timesheet submitted
hook_invoke_all('timesheet_submitted', [
    'timesheet_id' => $timesheetId,
    'user_id' => $userId,
    'period_start' => $periodStart,
    'period_end' => $periodEnd,
    'total_hours' => $total,
]);

// Timesheet approved
hook_invoke_all('timesheet_approved', [
    'timesheet_id' => $timesheetId,
    'approver_id' => $approverId,
    'regular_hours' => $regularHours,
    'overtime_hours' => $overtimeHours,
]);

// Get activity billing rule
$result = hook_invoke_all('time_get_billing_rule', [
    'project_id' => $projectId,
    'activity_id' => $activityId,
]);
// Returns: ['billable' => true, 'rate' => 150.00, 'currency' => 'USD']

// Validate stage/activity access
$result = hook_invoke_all('project_activity_validate', [
    'project_id' => $projectId,
    'stage_id' => $stageId,
    'activity_id' => $activityId,
    'user_id' => $userId,
]);
// Returns: ['valid' => true] or ['valid' => false, 'reason' => 'Stage inactive']

// Export timesheet to payroll
hook_invoke_all('timesheet_export_payroll', [
    'timesheet_id' => $timesheetId,
    'employee_id' => $employeeId,
    'regular_hours' => $regularHours,
    'overtime_hours' => $overtimeHours,
    'hourly_rate' => $rate,
]);
```

### Timesheet Periods

| Period Type | Start Day | Submission Deadline |
|-------------|-----------|-------------------|
| Weekly | Monday | Following Monday 12:00 |
| Bi-weekly | Monday (odd weeks) | Monday 12:00 |
| Monthly | 1st | 5th of next month |

### Overtime Rules

| Type | Trigger | Multiplier |
|------|---------|------------|
| Regular | Up to daily/weekly limit | 1.0x |
| OT 1.5 | Daily > 8h, Weekly > 40h | 1.5x |
| OT 2.0 | Weekly > 60h | 2.0x |

### Integration with Project Templates

```php
// When time entry created for project:
// 1. Fetch project template stages
// 2. Constrain activity dropdown to current stage
// 3. Fetch billing rule for activity

hook_invoke_first('project_get_current_stage', [
    'project_id' => $projectId,
    'date' => $entryDate,
]);
// Returns: ['stage_id' => 3, 'stage_name' => 'Development']

hook_invoke_first('project_stage_get_activities', [
    'project_id' => $projectId,
    'stage_id' => $stageId,
]);
// Returns: ['activities' => [...], 'active' => true, 'date_range' => [...]]
```

### Dependencies

- ksf_FA_ProjectManagement (project/stage/activity)
- ksf_FA_TravelExpense (expense-time correlation)
- ksf_FA_Teams (approval chain)
- ksf_FA_RBAC (permission checks)
- ksf_FA_HRM (employee records, payroll export)
