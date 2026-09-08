# ARCH-TIME-UI-001 - Timesheet Entry UI Design

## Overview

This document addresses the UI design for timesheet entry described by the user.

### Key Requirements (from user input)

- **Employee DDL**: Default = current user. DDL shows direct reports + recursive reports.
- **Exception**: Project admin (not PM) can enter time for project team members.
- **Week DDL**: Admin configures week start day.
- **Summary Table**: Entries for selected employee + selected week, with 7 day columns.
- **Row Operations**: Only most recent added row editable until Add clicked. Edit loads row to editable line. Remove deletes row. Save stays draft. Submit sends to approvers.
- **Status Tracking**: Draft → Submitted → Approved/Denied/Rejected → Returned (for editing) with comments.
- **Global Activities**: Training, Admin available to all.
- **Project/Activity Filtering**: Filtered by employee assignment.

## Component Design

### 1. Employee Selection DDL

```php
// Query hook: Get eligible employees for entry
$result = hook_invoke_first('timesheet_get_eligible_employees', [
    'current_user_id' => $currentUserId,
    'project_id' => $projectId,
]);
// Returns: array of [id, name, relationship] including:
// - Current user (default)
// - Direct reports (from org chart)
// - Recursive reports (all subordinates)
// - Project team members (if current user is project admin, not PM)
```

**DDL Constraint Logic**:
```php
// 1. Always include current user
// 2. If current user has direct reports (from orgchart_get_team): include those
// 3. If current user has recursive reports (via team leader chain): include all
// 4. If current user is project admin (not PM - separate permission): include project members
// 5. Filter: only show employees assigned to the project (via project_activity_assignments)
```

### 2. Week Selection DDL

```php
// Config from admin
hook_invoke_first('timesheet_get_week_config', [
    'user_id' => $currentUserId,
]);
// Returns: ['week_start_day' => 'Monday', 'period_type' => 'weekly']
```

**DDL Options**: All valid weeks based on period type. Default = current week starting from configured start day.

### 3. Summary Table Layout

```
+--------+--------+--------+--------+--------+--------+--------+--------+--------+----------+
| Row #  | Date   | Date   | Date   | ... (7 days)                              | Remove   |
|        | (Mon)  | (Tue)  | (Wed)  | ...                                      | Button   |
+--------+--------+--------+--------+--------+--------+--------+--------+--------+----------+
| 1      | 8.0    | 8.5    | 8.0    | 0.0    | 8.0    | 0.0    | 0.0    |         | [Remove] |
| 2      | 0.0    | 4.0    | 0.0    | 8.0    | 0.0    | 0.0    | 0.0    | [Edit]  | [Remove] |
+--------+--------+--------+--------+--------+--------+--------+--------+--------+----------+
         ^ Edit loads row 2 (4.0 at Tue) into entry line below
```

**Column Structure**:
- **Leftmost**: Row label (Project - Activity name, truncated if needed)
- **Middle 7 columns**: One column per day (date number + DoW label in header cell, e.g., "7 Mon")
- **2nd Last Column**: Edit button (loads that row into entry form below table; only available for most recently added row until Add clicked)
- **Rightmost**: Remove button (deletes the row from the timesheet)

**Row States**:
- **Most recent added row**: Editable fields (date/day columns editable) until Add clicked
- **Older rows**: Read-only until Edit clicked on that specific row (loads into entry form)
- **Edit state**: When Edit clicked on a row, that row's data is loaded below the summary table into an entry form
- **Add state**: After Add clicked, a new empty row appears at bottom; the previous "most recent" row becomes read-only

### 4. Entry Line (Below Summary Table)

```
[Project DDL] [Stage DDL] [Activity DDL] [Hours] [Save Button] [Submit Button]
```

**Form Flow**:
1. User selects Week DDL → table shows existing entries for employee + week
2. User selects Project DDL → constrained by employee's assigned projects (`project_activity_validate` query hook)
3. User selects Stage DDL → constrained by active stages (`project_stage_access_check`)
4. User selects Activity DDL → constrained by stage activities (`project_stage_get_activities`)
5. User enters hours for each day (editable fields for 7 days in that row)
6. User clicks Save → saves in draft status (DB: status = 'draft')
7. User clicks Submit → sends to `approval_request` hook (DB: status = 'submitted')

### 5. Status Tracking with Comments

```sql
CREATE TABLE `0_approval_step_comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `approval_chain_id` INT UNSIGNED NOT NULL,
  `step` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status_change` ENUM('draft','submitted','approved','rejected','denied','returned') NOT NULL,
  `comment` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_chain_step` (`approval_chain_id`, `step`)
);
```

**Comment Logging**:
```php
// Every status change logs a comment
hook_invoke_all('approval_comment_log', [
    'approval_chain_id' => $chainId,
    'step' => $step,
    'user_id' => $userId,
    'status_change' => 'submitted',
    'comment' => 'Timesheet submitted for week of 2026-09-01',
]);
```

### 6. Auto-Approve Workflow

```php
// Query hook for auto-approval
$result = hook_invoke_first('timesheet_check_auto_approve', [
    'timesheet_id' => $timesheetId,
    'regular_hours' => 40.00,
    'overtime_hours' => 2.00,
    'total_hours' => 42.00,
    'employee_id' => $employeeId,
]);
// Returns: ['auto_approve' => true, 'reason' => 'Within normal range: 42h <= 45h max']

// Small expense auto-approve rules
$result = hook_invoke_first('expense_check_auto_approve', [
    'expense_report_id' => $reportId,
    'total_amount' => 45.50,
    'category' => 'meals',
    'project_id' => $projectId,
]);
// Returns: ['auto_approve' => true, 'reason' => 'Meal expense under $50 threshold']
```

### 7. Time-Expense Correlation (+/- 1 Day)

```php
// In entry creation (both time and expense)
$result = hook_invoke_first('expense_check_time_correlation', [
    'expense_report_id' => $reportId,
    'expense_date' => '2026-09-12',
    'project_id' => $projectId,
    'activity_id' => $activityId,
]);
// Returns: ['related_time_entries' => [entry1, entry2], 'date_range' => '2026-09-11 to 2026-09-13']
```

### Integration Points

**Employee Assignment Filtering**:
```php
// Project → Employee assignment link
hook_invoke_first('project_employee_assignments', [
    'project_id' => $projectId,
    'employee_id' => $currentUserId,
]);
// Returns: ['assigned' => true/false, 'assignment_type' => 'direct|indirect']
```

**Global Activities**:
```php
// Activity is global (available regardless of stage)
$activity = new Activity([
    'code' => 'TRAIN',
    'name' => 'Employee Training',
    'is_global' => true,
    'is_active' => true,
]);
// Global activities shown in all stage activity dropdowns regardless of stage constraints
```
