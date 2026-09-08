# UAT Plan - ksf_FA_Timesheets

## Document Information
- **Module**: ksf_FA_Timesheets (Timesheets for FrontAccounting)
- **Version**: 1.0.0
- **Date**: 2024-04-26
- **Status**: Planned
- **Author**: KSFII Development Team

## 1. Overview

### 1.1 Purpose
This document defines the User Acceptance Test (UAT) plan for the ksf_FA_Timesheets module.

### 1.2 Objectives
1. Validate that the module meets business requirements
2. Verify end-to-end workflows function correctly
3. Confirm user experience meets expectations
4. Ensure integration with FrontAccounting works

### 1.3 Scope
UAT covers:
- Time entry creation and management
- Approval workflow
- Employee and manager interfaces
- Permission enforcement

## 2. UAT Criteria

### 2.1 Success Criteria
- All test cases pass
- No critical or high priority defects
- User acceptance threshold met (95% pass rate)
- Sign-off from business stakeholders

### 2.2 Acceptance Threshold
- Critical defects: 0
- High priority defects: 0
- Medium priority defects: < 5
- Total pass rate: >= 95%

## 3. Test Scenarios

### 3.1 Employee - Create Time Entry

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-EMP-001 | Create draft entry | 1. Navigate to My Timesheet<br>2. Click Add Entry<br>3. Fill date, hours, activity<br>4. Click Save | Entry saved as Draft | |
| UAT-EMP-002 | Enter hours | 1. Enter 8.0 hours<br>2. Click Save | Hours stored correctly | |
| UAT-EMP-003 | Select activity | 1. Select G01 from dropdown<br>2. Verify isOvertime=false | Activity code stored | |
| UAT-EMP-004 | Select overtime | 1. Select O01 from dropdown<br>2. Verify isOvertime=true | Overtime marked | |
| UAT-EMP-005 | Link to project | 1. Select project from dropdown<br>2. Verify project shown | Project linked | |

### 3.2 Employee - Edit Time Entry

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-EMP-010 | Edit draft entry | 1. Open draft entry<br>2. Modify hours<br>3. Click Save | Entry updated | |
| UAT-EMP-011 | Cannot edit submitted | 1. Try to edit submitted entry<br>2. Verify edit not allowed | Error displayed | |
| UAT-EMP-012 | Cannot edit approved | 1. Try to edit approved entry<br>2. Verify edit not allowed | Error displayed | |

### 3.3 Employee - Submit Time Entry

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-EMP-020 | Submit draft entry | 1. Click Submit on draft entry<br>2. Confirm submission | Status changes to Submitted | |
| UAT-EMP-021 | Submit without required fields | 1. Try to submit incomplete<br>2. Verify validation error | Error displayed | |
| UAT-EMP-022 | View submitted entries | 1. Filter by Submitted status<br>2. Verify entries shown | Entries visible | |

### 3.4 Employee - View Timesheet

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-EMP-030 | View own entries | 1. Navigate to My Timesheet<br>2. View all entries | All own entries shown | |
| UAT-EMP-031 | Filter by date | 1. Set date range filter<br>2. Verify filtered | Filter works | |
| UAT-EMP-032 | Filter by status | 1. Set status filter<br>2. Verify filtered | Filter works | |

### 3.5 Manager - View Approvals

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-MGR-001 | View pending approvals | 1. Navigate to Approve Timesheets<br>2. View pending list | Submitted entries shown | |
| UAT-MGR-002 | View employee info | 1. Click on pending entry<br>2. Verify employee info | Employee ID shown | |
| UAT-MGR-003 | View entry details | 1. Expand entry details<br>2. Verify all fields | Details correct | |

### 3.6 Manager - Approve/Reject

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-MGR-010 | Approve entry | 1. Click Approve on entry<br>2. Confirm | Status changes to Approved | |
| UAT-MGR-011 | Approver info recorded | 1. Approve entry<br>2. Check approver_id | Approver stored | |
| UAT-MGR-012 | Date recorded | 1. Approve entry<br>2. Check approved_date | Timestamp stored | |
| UAT-MGR-013 | Reject entry | 1. Click Reject<br>2. Enter reason<br>3. Confirm | Status changes to Rejected | |
| UAT-MGR-014 | Reject reason stored | 1. Reject with reason<br>2. Check rejected_reason | Reason stored | |

### 3.7 Manager - Bulk Actions

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-MGR-020 | Bulk approve | 1. Select multiple entries<br>2. Click Bulk Approve<br>3. Confirm | All approved | |
| UAT-MGR-021 | Bulk reject | 1. Select multiple entries<br>2. Click Bulk Reject<br>3. Enter reason<br>4. Confirm | All rejected | |

### 3.8 Permission Tests

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-PERM-001 | No permission denied | 1. Login without TIMESHEET_VIEW<br>2. Try to access module | Access denied | |
| UAT-PERM-002 | View only permission | 1. Login with TIMESHEET_VIEW only<br>2. Try to create entry | Create denied | |
| UAT-PERM-003 | Manage permission | 1. Login with TIMESHEET_MANAGE<br>2. Create entry | Success | |
| UAT-PERM-004 | Approve permission | 1. Login with TIMESHEET_APPROVE<br>2. Approve entry | Success | |

### 3.9 Integration Tests

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-INT-001 | FA user integration | 1. Login as FA user<br>2. Verify employee data | FA user used | |
| UAT-INT-002 | FA permission integration | 1. Check FA role permissions<br>2. Verify FA permissions applied | Permissions work | |
| UAT-INT-003 | FA menu integration | 1. Check FA menu<br>2. Click Timesheets menu | Menu works | |

### 3.10 Negative Test Cases

| Scenario ID | Scenario | Test Steps | Expected Result | Pass/Fail |
|-------------|----------|------------|-----------------|-----------|
| UAT-NEG-001 | Invalid hours (negative) | 1. Enter -1 hours<br>2. Try to save | Validation error | |
| UAT-NEG-002 | Invalid hours (>24) | 1. Enter 25 hours<br>2. Try to save | Validation error | |
| UAT-NEG-003 | Empty date | 1. Leave date empty<br>2. Try to save | Validation error | |
| UAT-NEG-004 | Empty employee | 1. Submit without employee<br>2. Try to save | Validation error | |
| UAT-NEG-005 | Invalid activity code | 1. Enter invalid code<br>2. Try to save | Validation error | |

## 4. Test Data Requirements

### 4.1 Test Users
| User | Role | Permissions | Description |
|------|------|-------------|-------------|
| TestEmployee1 | Employee | TIMESHEET_MANAGE | Regular employee |
| TestEmployee2 | Employee | TIMESHEET_MANAGE | Second employee |
| TestManager1 | Manager | TIMESHEET_APPROVE | Approver for employees |
| TestAdmin1 | Admin | TIMESHEET_ADMIN | System admin |

### 4.2 Test Projects
| Project ID | Name | Description |
|------------|------|-------------|
| PRJ001 | Test Project 1 | Active project |
| PRJ002 | Test Project 2 | Active project |

### 4.3 Test Time Entries
| Entry | Employee | Date | Hours | Activity | Project | Status |
|-------|----------|------|-------|----------|---------|--------|
| TE001 | TestEmployee1 | Today | 8.0 | G01 | - | Draft |
| TE002 | TestEmployee1 | Yesterday | 8.0 | G01 | PRJ001 | Submitted |
| TE003 | TestEmployee1 | 2 days ago | 2.0 | O01 | - | Submitted |
| TE004 | TestEmployee2 | Yesterday | 8.0 | G01 | - | Submitted |
| TE005 | TestEmployee1 | 3 days ago | 8.0 | G01 | PRJ001 | Approved |

## 5. Test Environment

### 5.1 Environment Requirements
- FrontAccounting 2.4.x installed and configured
- ksf_FA_Timesheets module installed
- Test users configured in FA
- Test data loaded
- Browser: Chrome/Firefox latest

### 5.2 Access
- Test environment URL
- Test user credentials (provided separately)
- Admin access for setup

## 6. Execution Plan

### 6.1 Phase 1: Employee Scenarios
- UAT-EMP-001 through UAT-EMP-032
- Executed by: Test Employee
- Duration: 30 minutes

### 6.2 Phase 2: Manager Scenarios
- UAT-MGR-001 through UAT-MGR-021
- Executed by: Test Manager
- Duration: 20 minutes

### 6.3 Phase 3: Permission Scenarios
- UAT-PERM-001 through UAT-PERM-004
- Executed by: Test Admin
- Duration: 15 minutes

### 6.4 Phase 4: Integration Scenarios
- UAT-INT-001 through UAT-INT-003
- Executed by: Test Admin
- Duration: 10 minutes

### 6.5 Phase 5: Negative Test Cases
- UAT-NEG-001 through UAT-NEG-005
- Executed by: Test Employee
- Duration: 10 minutes

## 7. Defect Reporting

### 7.1 Defect Categories
- Critical: System crash, data loss
- High: Major feature not working
- Medium: Feature working with issues
- Low: Cosmetic or minor issue

### 7.2 Defect Report Format
- Defect ID
- Scenario ID
- Description
- Steps to reproduce
- Expected result
- Actual result
- Priority
- Screenshot (if applicable)

## 8. Sign-off

### 8.1 Approval Requirements
- All critical and high defects resolved
- 95% test pass rate achieved
- Business stakeholder sign-off

### 8.2 Sign-off Signatures
| Role | Name | Date | Signature |
|------|------|------|-----------|
| Test Lead | | | |
| Business Sponsor | | | |
| Project Manager | | | |

---
*Document Version: 1.0.0*
*Last Updated: 2024-04-26*
