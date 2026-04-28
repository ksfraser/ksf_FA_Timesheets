# ksf_FA_Timesheets - FrontAccounting Timesheets Module

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0+-777bb6)
![FA](https://img.shields.io/badge/FrontAccounting-2.4.x-green)
![License](https://img.shields.io/badge/license-GPL--3.0-orange)

## Overview

ksf_FA_Timesheets is a FrontAccounting module that integrates time tracking functionality into the FA system. It bridges the ksf_Timesheets core library with FrontAccounting, enabling employees to submit timesheets and managers to approve time entries.

### Features

- **Time Entry Management** - Create, edit, and submit time entries
- **Activity Codes** - Track time by activity type (regular, overtime, sick, vacation)
- **Project/Task Association** - Link time to projects and tasks
- **Approval Workflow** - Submit entries for manager approval
- **Status Tracking** - Draft, Submitted, Approved, Rejected states
- **Employee Self-Service** - Employees manage their own timesheets
- **Manager Approval** - Supervisors approve/reject entries

### Status

**PLANNED** - In Development

- TimeEntry entity with PHP 8.0+ strict typing
- FA module wrapper architecture
- Integration with ksf_Timesheets library
- Approval workflow pending implementation

## Quick Start

### Installation

1. **Install dependencies**:
```bash
composer require ksfraser/ksf_timesheets
```

2. **Install module**:
- Copy ksf_FA_Timesheets to FA modules directory
- Go to Administrator > Modules > Install Modules
- Find ksf_FA_Timesheets and click Install

3. **Database tables** are created automatically on install

4. **Assign permissions** to users via Administrator > User Roles

### Using the Module

Access via the Timesheets menu after installation:

- **My Timesheet**: Submit personal time entries
- **Time Entries**: View and manage entries
- **Approve Timesheets**: Manager approval (requires permission)
- **Reports**: Time reporting

## Database Tables

### Core Tables

| Table | Description |
|-------|-------------|
| `fa_timesheet_entries` | Time entry records |

### Entry Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | INT | Primary key |
| `employee_id` | INT | Employee reference |
| `date` | DATE | Work date |
| `hours` | DECIMAL | Hours worked |
| `activity_code` | VARCHAR | Activity type (G01, O01, OT) |
| `project_id` | INT | Project reference (nullable) |
| `task_id` | INT | Task reference (nullable) |
| `description` | TEXT | Entry description |
| `status` | VARCHAR | Draft/Submitted/Approved/Rejected |
| `approver_id` | INT | Approver user ID |
| `approved_date` | DATETIME | Approval timestamp |

### Activity Codes

| Code | Description |
|------|-------------|
| G01 | Regular working hours |
| O01 | Overtime |
| OT | Overtime (alt) |
| S01 | Sick leave |
| V01 | Vacation |

### Entry Status

| Status | Description |
|--------|-------------|
| Draft | Not yet submitted |
| Submitted | Awaiting approval |
| Approved | Manager approved |
| Rejected | Manager rejected |

## Permissions

| Permission | Description |
|------------|-------------|
| TIMESHEET_VIEW | View time entries |
| TIMESHEET_MANAGE | Submit own entries |
| TIMESHEET_APPROVE | Approve team entries |
| TIMESHEET_ADMIN | Full administrative access |

## API Reference

### TimeEntry Entity

```php
use Ksfraser\Timesheets\Entity\TimeEntry;

// Create time entry
$entry = new TimeEntry();
$entry->setEmployeeId($employeeId);
$entry->setDate('2024-01-15');
$entry->setHours(8.0);
$entry->setActivityCode('G01');
$entry->setProjectId(1);
$entry->setTaskId(5);
$entry->setDescription('Development work');
$entry->setStatus(TimeEntry::STATUS_DRAFT);

// Check status
$entry->isOvertime();    // true for O01, OT
$entry->isApproved();    // true if approved
$entry->isSubmitted();  // true if submitted
```

### Database Functions

```php
// Time entry operations
add_time_entry($entry_data);
update_time_entry($entry_id, $data);
delete_time_entry($entry_id);
get_employee_time_entries($employee_id);
get_pending_approvals($approver_id);

// Approve/reject
approve_time_entry($entry_id, $approver_id);
reject_time_entry($entry_id, $approver_id, $reason);
```

### Status Constants

```php
TimeEntry::STATUS_DRAFT;     // 'Draft'
TimeEntry::STATUS_SUBMITTED; // 'Submitted'
TimeEntry::STATUS_APPROVED; // 'Approved'
TimeEntry::STATUS_REJECTED; // 'Rejected'
```

## Requirements

- FrontAccounting 2.4.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.0+
- ksf_Timesheets library

## License

GPL-3.0 - See LICENSE file

## Support

For issues and questions, contact the KSF Development Team.

---
*Module Version: 1.0.0*
*Last Updated: 2024-04-26*
