# Use Cases - ksf_FA_Timesheets

## Reference Use Cases
- Core UC: ksf_Timesheets/ProjectDcs/Use Case.md (UC-TS-001 through UC-TS-012)

---

## UC-FA-TS-001: Timesheet to GL
**Actor**: System

**FA-Specific Flow**:
1. Timesheet approved (ksf_Workflow)
2. ksf_FA_Timesheets creates:
   - Labor cost GL entries
   - Links to FA dimensions (project, department)
   - Overtime premium entries
3. Posted to FA general ledger

---

## UC-FA-TS-002: Project Labor Billing
**Actor**: Project Manager, Finance

**FA-Specific Flow**:
1. Project time tracked
2. ksf_FA_Timesheets:
   - Calculates billable amount
   - Generates invoice line items
3. ksf_FA_CRM creates customer invoice

*Document Version: 1.0.0*
*Last Updated: 2026-05-11*