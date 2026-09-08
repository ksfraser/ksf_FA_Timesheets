# Functional Requirements - ksf_FA_Timesheets

## Document Information
- **Module**: ksf_FA_Timesheets (Timesheets for FrontAccounting)
- **Version**: 1.0.0
- **Date**: 2024-04-26
- **Status**: Planned
- **Author**: KSFII Development Team

## 1. Overview

### 1.1 Purpose
This document defines the functional requirements for the ksf_FA_Timesheets module, which integrates time tracking into FrontAccounting.

### 1.2 Scope
The Timesheets module provides:
- Time entry creation and management
- Activity code tracking
- Project/task association
- Approval workflow
- Employee self-service
- Manager approval interface

## 2. Time Entry Management

### 2.1 Create Time Entry (FR-TE-001)
**Requirement**: The system shall allow employees to create time entries.

**Fields**:
- `employee_id` - Reference to employee
- `date` - Work date
- `hours` - Hours worked (decimal)
- `activity_code` - Activity type identifier
- `project_id` - Optional project reference
- `task_id` - Optional task reference
- `description` - Work description

**Priority**: High

### 2.2 Edit Time Entry (FR-TE-002)
**Requirement**: The system shall allow employees to edit their own draft entries.

**Behavior**:
- Only entries with status = Draft can be edited
- Cannot edit submitted or approved entries
- Edit creates new audit record

**Priority**: High

### 2.3 Delete Time Entry (FR-TE-003)
**Requirement**: The system shall allow employees to delete draft entries.

**Behavior**:
- Only entries with status = Draft can be deleted
- Soft delete with audit trail

**Priority**: Medium

### 2.4 Submit Time Entry (FR-TE-004)
**Requirement**: The system shall allow employees to submit entries for approval.

**Behavior**:
- Validates required fields before submission
- Changes status from Draft to Submitted
- Sends notification to approver (future)

**Priority**: High

## 3. Activity Codes

### 3.1 Activity Code Assignment (FR-AC-001)
**Requirement**: The system shall allow assignment of activity codes to time entries.

**Default Activity Codes**:
| Code | Description |
|------|-------------|
| G01 | Regular working hours |
| O01 | Overtime |
| OT | Overtime (alternative) |
| S01 | Sick leave |
| V01 | Vacation |
| H01 | Holiday |
| T01 | Training |

**Priority**: High

### 3.2 Overtime Detection (FR-AC-002)
**Requirement**: The system shall identify overtime entries automatically.

**Behavior**:
- Activity codes O01, OT marked as overtime
- isOvertime() method returns true

**Priority**: High

## 4. Project & Task Association

### 4.1 Link to Project (FR-PT-001)
**Requirement**: The system shall allow linking time entries to projects.

**Features**:
- Optional project association
- FA project integration (future)
- Project validation

**Priority**: Medium

### 4.2 Link to Task (FR-PT-002)
**Requirement**: The system shall allow linking entries to specific tasks within projects.

**Features**:
- Optional task within project
- Task validation

**Priority**: Medium

## 5. Approval Workflow

### 5.1 Submit for Approval (FR-AW-001)
**Requirement**: The system shall allow submission of entries for manager approval.

**Workflow**:
1. Employee completes time entry
2. Employee clicks Submit
3. Status changes to Submitted
4. Approver receives notification

**Priority**: High

### 5.2 Approve Time Entry (FR-AW-002)
**Requirement**: The system shall allow managers to approve submitted entries.

**Behavior**:
- Sets status to Approved
- Records approver_id
- Records approved_date timestamp

**Priority**: High

### 5.3 Reject Time Entry (FR-AW-003)
**Requirement**: The system shall allow managers to reject entries with reason.

**Behavior**:
- Sets status to Rejected
- Records approver_id
- Includes rejection reason
- Employee can edit and resubmit

**Priority**: High

### 5.4 View Pending Approvals (FR-AW-004)
**Requirement**: The system shall display entries pending approval for each manager.

**Features**:
- List of submitted entries awaiting approval
- Filter by date range
- Filter by employee

**Priority**: High

## 6. Employee Self-Service

### 6.1 My Timesheet (FR-ES-001)
**Requirement**: The system shall allow employees to view their own timesheets.

**Features**:
- Calendar view of entries
- List view of entries
- Filter by date range
- Filter by status

**Priority**: High

### 6.2 Create New Entry (FR-ES-002)
**Requirement**: The system shall provide interface to create new time entries.

**Features**:
- Date picker
- Hours input with validation
- Activity code dropdown
- Project/task selectors
- Description field

**Priority**: High

### 6.3 Edit Own Entries (FR-ES-003)
**Requirement**: The system shall allow employees to edit their draft entries.

**Behavior**:
- Only draft entries editable
- Pre-populated form

**Priority**: High

## 7. Manager Functions

### 7.1 Team Approval View (FR-MF-001)
**Requirement**: The system shall show pending approvals to managers.

**Features**:
- List of team submissions
- Employee information
- Entry details
- Approve/Reject buttons

**Priority**: High

### 7.2 Bulk Approval (FR-MF-002)
**Requirement**: The system shall allow bulk approval of multiple entries.

**Features**:
- Select multiple entries
- Approve all selected
- Reject all with reason

**Priority**: Medium

### 7.3 View Team Timesheets (FR-MF-003)
**Requirement**: The system shall allow managers to view team timesheets.

**Features**:
- Filter by employee
- Filter by date range
- Filter by status

**Priority**: Medium

## 8. Status Tracking

### 8.1 Status Constants (FR-ST-001)
**Requirement**: The system shall define time entry status constants.

**Status Values**:
- Draft (initial state)
- Submitted (awaiting approval)
- Approved (accepted)
- Rejected (not accepted)

**Priority**: High

### 8.2 Status Validation (FR-ST-002)
**Requirement**: The system shall enforce status transitions.

**Valid Transitions**:
- Draft -> Submitted
- Submitted -> Approved
- Submitted -> Rejected
- Rejected -> Draft (via edit/resubmit)

**Priority**: High

## 9. Reporting

### 9.1 Hours Summary (FR-RP-001)
**Requirement**: The system shall provide hours summary reports.

**Metrics**:
- Total hours by employee
- Hours by activity code
- Hours by project
- Hours by date range

**Priority**: Medium

### 9.2 Overtime Report (FR-RP-002)
**Requirement**: The system shall track overtime hours.

**Metrics**:
- Total overtime hours
- Overtime by employee
- Overtime by period

**Priority**: Medium

## 10. Integration

### 10.1 FA User Integration (FR-IN-001)
**Requirement**: The system shall integrate with FA user system.

**Features**:
- Use FA users as employees
- Use FA users as approvers
- Permission mapping

**Priority**: High

### 10.2 FA Project Integration (FR-IN-002)
**Requirement**: The system shall integrate with FA projects (future).

**Features**:
- Link to FA projects
- Pull project list
- Track project hours

**Priority**: Medium

## 11. Permissions

### 11.1 Access Control (FR-AC-001)
**Requirement**: The system shall enforce role-based access control.

**Permission Constants**:
- `TIMESHEET_VIEW` - View time entries
- `TIMESHEET_MANAGE` - Submit own entries
- `TIMESHEET_APPROVE` - Approve team entries
- `TIMESHEET_ADMIN` - Full administrative access

**Priority**: High

## 12. Non-Functional Requirements

### 12.1 Performance
- Page load time < 3 seconds
- Database queries optimized with indexes
- Efficient pagination for large datasets

### 12.2 Security
- SQL injection prevention via prepared statements
- XSS prevention via output escaping
- CSRF protection on forms
- Access control on all operations

### 12.3 Compatibility
- FrontAccounting 2.4.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.0+

### 12.4 Maintainability
- Modular code structure
- Clear separation of concerns
- Database abstraction layer
- Comprehensive comments

## 13. Appendix: Default Values

### Activity Codes
| Code | Description | Is Overtime |
|------|-------------|-------------|
| G01 | Regular working hours | No |
| O01 | Overtime | Yes |
| OT | Overtime (alt) | Yes |
| S01 | Sick leave | No |
| V01 | Vacation | No |
| H01 | Holiday | No |
| T01 | Training | No |

### Entry Status
| Status | Description |
|--------|-------------|
| Draft | Not yet submitted |
| Submitted | Awaiting approval |
| Approved | Manager approved |
| Rejected | Manager rejected |

---
*Document Version: 1.0.0*
*Last Updated: 2024-04-26*
