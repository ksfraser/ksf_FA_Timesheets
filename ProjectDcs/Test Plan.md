# Test Plan - ksf_FA_Timesheets

## Document Information
- **Module**: ksf_FA_Timesheets (Timesheets for FrontAccounting)
- **Version**: 1.0.0
- **Date**: 2024-04-26
- **Status**: Planned
- **Author**: KSFII Development Team

## 1. Overview

### 1.1 Purpose
This document defines the test strategy and approach for the ksf_FA_Timesheets module.

### 1.2 Scope
Testing covers:
- Time entry creation and management
- Activity code assignment
- Approval workflow
- Permission enforcement
- Integration with ksf_Timesheets

## 2. Test Strategy

### 2.1 Testing Approach
- **Unit Testing**: Individual component tests
- **Integration Testing**: FA module integration
- **Workflow Testing**: End-to-end workflows
- **Acceptance Testing**: User acceptance criteria

### 2.2 Testing Levels
1. Unit Tests - Component-level validation
2. Integration Tests - System integration
3. System Tests - Full module testing
4. Acceptance Tests - User validation

## 3. Test Categories

### 3.1 Unit Tests

#### TimeEntry Entity Tests (UT-TE-001)
| Test ID | Test Case | Expected Result |
|---------|----------|---------------|
| UT-TE-001 | Create TimeEntry with valid data | Entry created with default status |
| UT-TE-002 | Set employee ID | ID stored correctly |
| UT-TE-003 | Set date | Date stored correctly |
| UT-TE-004 | Set hours | Hours stored correctly |
| UT-TE-005 | Set activity code G01 | isOvertime() returns false |
| UT-TE-006 | Set activity code O01 | isOvertime() returns true |
| UT-TE-007 | Set status Draft | isSubmitted() returns false |
| UT-TE-008 | Set status Submitted | isSubmitted() returns true |
| UT-TE-009 | Set status Approved | isApproved() returns true |
| UT-TE-010 | Null project/task | Null values stored |

#### Status Transition Tests (UT-ST-001)
| Test ID | Test Case | Expected Result |
|---------|----------|---------------|
| UT-ST-001 | Draft to Submitted | Valid transition |
| UT-ST-002 | Submitted to Approved | Valid transition |
| UT-ST-003 | Submitted to Rejected | Valid transition |
| UT-ST-004 | Approved to Draft | Invalid - not allowed |
| UT-ST-005 | Draft to Approved | Invalid - must be submitted |

### 3.2 Integration Tests

#### Database Integration Tests (IT-DB-001)
| Test ID | Test Case | Expected Result |
|---------|----------|---------------|
| IT-DB-001 | Insert time entry | Entry saved to database |
| IT-DB-002 | Update time entry | Entry updated in database |
| IT-DB-003 | Delete time entry | Entry deleted from database |
| IT-DB-004 | Select entries by employee | Correct entries returned |
| IT-DB-005 | Select entries by date range | Correct entries returned |
| IT-DB-006 | Select pending approvals | Correct entries returned |
| IT-DB-007 | Update approver info | Approver ID stored |

#### FA Integration Tests (IT-FA-001)
| Test ID | Test Case | Expected Result |
|---------|----------|---------------|
| IT-FA-001 | Module install | Tables created |
| IT-FA-002 | Module activate | Module activated |
| IT-FA-003 | Module deactivate | Module deactivated |
| IT-FA-004 | Menu registration | Menu items visible |
| IT-FA-005 | Permission registration | Permissions available |

### 3.3 Workflow Tests

#### Employee Workflow Tests (WT-EM-001)
| Test ID | Test Case | Expected Result |
|---------|----------|---------------|
| WT-EM-001 | Create new draft entry | Entry created in Draft status |
| WT-EM-002 | Edit draft entry | Entry updated successfully |
| WT-EM-003 | Submit draft entry | Status changes to Submitted |
| WT-EM-004 | Edit submitted entry | Error - not allowed |
| WT-EM-005 | Delete draft entry | Entry deleted |
| WT-EM-006 | Delete submitted entry | Error - not allowed |

#### Manager Workflow Tests (WT-MG-001)
| Test ID | Test Case | Expected Result |
|---------|----------|---------------|
| WT-MG-001 | View pending approvals | Correct entries shown |
| WT-MG-002 | Approve submitted entry | Status changes to Approved |
| WT-MG-003 | Reject submitted entry | Status changes to Rejected |
| WT-MG-004 | View team timesheets | All team entries shown |
| WT-MG-005 | Bulk approve entries | All selected approved |

### 3.4 Permission Tests

#### Access Control Tests (PT-AC-001)
| Test ID | Test Case | Expected Result |
|---------|----------|---------------|
| PT-AC-001 | User with TIMESHEET_VIEW | Can view entries |
| PT-AC-002 | User with TIMESHEET_MANAGE | Can create/edit entries |
| PT-AC-003 | User with TIMESHEET_APPROVE | Can approve entries |
| PT-AC-004 | User without permissions | Cannot access module |

### 3.5 Edge Case Tests

#### Validation Tests (ET-VAL-001)
| Test ID | Test Case | Expected Result |
|---------|----------|---------------|
| ET-VAL-001 | Empty employee ID | Validation error |
| ET-VAL-002 | Empty date | Validation error |
| ET-VAL-003 | Zero hours | Validation error |
| ET-VAL-004 | Negative hours | Validation error |
| ET-VAL-005 | Hours > 24 | Validation error |
| ET-VAL-006 | Invalid activity code | Validation error |
| ET-VAL-007 | Future date | Allowed (allow) |
| ET-VAL-008 | Very long description | Truncated to max |

## 4. Test Data

### 4.1 Test Users
| User ID | Role | Permission Level |
|---------|------|-------------------|
| TEST_EMP_001 | Employee | TIMESHEET_MANAGE |
| TEST_MGR_001 | Manager | TIMESHEET_APPROVE |
| TEST_ADMIN_001 | Admin | TIMESHEET_ADMIN |

### 4.2 Test Time Entries
| Entry ID | Employee | Date | Hours | Activity | Status |
|----------|----------|------|-------|----------|--------|
| TE001 | TEST_EMP_001 | 2024-01-15 | 8.0 | G01 | Draft |
| TE002 | TEST_EMP_001 | 2024-01-16 | 8.0 | G01 | Submitted |
| TE003 | TEST_EMP_001 | 2024-01-17 | 2.0 | O01 | Submitted |
| TE004 | TEST_EMP_001 | 2024-01-18 | 8.0 | G01 | Approved |

## 5. Test Environment

### 5.1 Requirements
- FrontAccounting 2.4.x installed
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.0+
- ksf_Timesheets library installed

### 5.2 Configuration
- Test database separate from production
- Test users created for testing
- Debug mode enabled for logging

## 6. Test Execution

### 6.1 Execution Order
1. Unit Tests (isolated)
2. Integration Tests (requires FA)
3. Workflow Tests (requires FA + data)
4. Acceptance Tests (requires full environment)

### 6.2 Success Criteria
- All unit tests pass
- All integration tests pass
- All workflow tests pass
- All acceptance tests pass

## 7. Reporting

### 7.1 Test Metrics
- Test execution time
- Pass/fail rate by category
- Defect density

### 7.2 Test Report Contents
- Summary of test results
- Failed tests with details
- Coverage analysis
- Defect summary

---
*Document Version: 1.0.0*
*Last Updated: 2024-04-26*
