# UAT-TIME-001 - Time Tracking UAT Plan

## User Acceptance Testing

**Module**: Timesheets
**BR**: BR-TIME-001
**Tester**: Employee / Manager

---

## Test Scenarios

### UAT-TIME-001-TC01: Create Timesheet with Project Activity

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Timesheets → New | Timesheet form displays |
| 2 | Select period: Sep 1-7, 2026 | Period set |
| 3 | Add entry: Project "PRJ-001", Stage "Development", Activity "Coding", 8h | Entry added |
| 4 | Verify billing rule | cost_plus, $150/hr |
| 5 | Add entry: Project "PRJ-001", Stage "Testing", Activity "Unit Testing", 4h | Entry rejected (stage not active) |

**Pass Criteria**: Time entry constrained by active stage

---

### UAT-TIME-001-TC02: Overtime Calculation

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add entries: Mon 8h, Tue 10h, Wed 8h, Thu 8h, Fri 8h | - |
| 2 | Submit timesheet | - |
| 3 | Verify regular hours | 40h |
| 4 | Verify overtime hours | 2h (OT 1.5 rate) |

**Pass Criteria**: Overtime calculated correctly

---

### UAT-TIME-001-TC03: Timesheet Approval Flow

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Submit timesheet | Status = pending_approval |
| 2 | Manager approves | Status = approved |
| 3 | Verify payroll export | Hook: timesheet_export_payroll emitted |
| 4 | Check GL entries | Labor costs posted |

**Pass Criteria**: Timesheet approved and exported

---

### UAT-TIME-001-TC04: Delegation

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Manager delegates to backup | Delegation created |
| 2 | Submit timesheet | - |
| 3 | Backup receives approval request | Delegation honored |
| 4 | Backup approves | Approval recorded |

**Pass Criteria**: Delegation works correctly

---

## Sign-Off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Employee | | | |
| Manager | | | |
| Backup Manager | | | |
