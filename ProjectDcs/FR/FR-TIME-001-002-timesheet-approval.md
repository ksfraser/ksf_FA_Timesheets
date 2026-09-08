# FR-TIME-001-002 - Timesheet Approval Workflow

## Functional Requirement

**Module**: Timesheets
**Priority**: P1 - High
**Status**: Proposed
**Integration**: Hook-based

### Description

Submit timesheets through approval chain with overtime calculation.

### Acceptance Criteria

| ID | Criteria | Hook |
|----|----------|------|
| AC-001 | Submit calculates overtime | - |
| AC-002 | Submit triggers approval_request | Emit: timesheet_submitted |
| AC-003 | Approved timesheet exported to payroll | Emit: timesheet_export_payroll |
| AC-004 | Rejected timesheet returned to employee | Emit: timesheet_rejected |
| AC-005 | GL entries for labor costs | Query: gl_entry_create |

### Hooks

```php
// Emit: Timesheet submitted
hook_invoke_all('timesheet_submitted', [
    'timesheet_id' => $timesheetId,
    'employee_id' => $userId,
    'period_start' => '2026-09-01',
    'period_end' => '2026-09-07',
    'total_hours' => 48.00,
    'regular_hours' => 40.00,
    'overtime_hours' => 8.00,
]);

// Emit: Timesheet approved and exported to payroll
hook_invoke_all('timesheet_export_payroll', [
    'timesheet_id' => $timesheetId,
    'employee_id' => $employeeId,
    'regular_hours' => 40.00,
    'regular_rate' => 50.00,
    'overtime_hours' => 8.00,
    'overtime_rate' => 75.00,
    'total_cost' => 2600.00,
    'currency' => 'USD',
]);
```

### Dependencies

- FR-APPROVAL-001-001: Approval chain engine
- FR-TIME-001-001: Timesheet entry
