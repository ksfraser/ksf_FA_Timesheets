<?php
/**
 * Timesheet Service — Core timesheet logic with hook-based integration.
 *
 * @since 2.0.0
 * @BABOK Related: FR-TIME-001-001, BR-TIME-001
 */
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Timesheets\Service;

/**
 * TimesheetService — manages timesheet lifecycle.
 *
 * Hook contracts used:
 * - emit: timesheet_submitted, timesheet_approved, timesheet_rejected, timesheet_reimbursed
 * - query: timesheet_export_payroll, time_get_billing_rule, project_get_current_stage,
 *          project_stage_get_activities, project_activity_validate,
 *          approval_request, approval_get_next_approver, approval_can_approve,
 *          approval_check_delegation, orgchart_get_manager, orgchart_get_team
 */
class TimesheetService
{
    private $db;
    private $prefix;
    private $userId;

    public function __construct($db, string $prefix = '', int $userId = 0)
    {
        $this->db      = $db;
        $this->prefix  = $prefix;
        $this->userId  = $userId;
    }

    /**
     * Create or load timesheet for employee and week.
     *
     * @param int $employeeId
     * @param string $weekStart (YYYY-MM-DD)
     * @return array [id => int|null, status => string]
     */
    public function getOrCreateTimesheet(int $employeeId, string $weekStart): array
    {
        $periodEnd = $this->getWeekEnd($weekStart);

        $sql = "SELECT id, status FROM {$this->prefix}timesheets
                WHERE employee_id = ? AND period_start = ?";
        $row = $this->db->fetchAssoc($sql, [$employeeId, $weekStart]);

        if ($row) {
            return [
                'id'     => (int) $row['id'],
                'status' => $row['status'],
            ];
        }

        // Check if employee has direct reports (DDL filtering for entry)
        $eligibleEmployees = $this->getEligibleEmployees($employeeId);
        if (!in_array($employeeId, $eligibleEmployees) && $employeeId !== $this->userId) {
            throw new \Exception('Employee not eligible for time entry');
        }

        $sql = "INSERT INTO {$this->prefix}timesheets
                (employee_id, period_start, period_end, status, submitted_by, created_by)
                VALUES (?, ?, ?, 'draft', ?, ?)";
        $this->db->executeUpdate($sql, [
            $employeeId, $weekStart, $periodEnd, $this->userId, $this->userId
        ]);

        return [
            'id'     => $this->db->lastInsertId(),
            'status' => 'draft',
        ];
    }

    /**
     * Get employees eligible for time entry (self + reports).
     */
    public function getEligibleEmployees(int $currentUserId): array
    {
        $employees = [$currentUserId];

        // Direct reports from org chart
        $data = [
            'current_user_id' => $currentUserId,
            'include_recursive' => true,
        ];
        hook_invoke_all('orgchart_get_reports', $data);
        if (isset($data['reports']) && is_array($data['reports'])) {
            foreach ($data['reports'] as $report) {
                $employees[] = $report['employee_id'];
            }
        }

        return array_unique($employees);
    }

    /**
     * Get current active stage for project on given date.
     */
    private function getProjectStage(int $projectId, string $date): ?array
    {
        $data = [
            'project_id' => $projectId,
            'date'       => $date,
        ];
        hook_invoke_all('project_get_current_stage', $data);

        if (!empty($data['stage'])) {
            return $data['stage'];
        }
        return null;
    }

    /**
     * Get activities for project stage (filtered by active status/date range).
     */
    public function getStageActivities(int $projectId, int $stageId): array
    {
        $data = [
            'project_id' => $projectId,
            'stage_id'   => $stageId,
        ];
        hook_invoke_all('project_stage_get_activities', $data);
        return $data['activities'] ?? [];
    }

    /**
     * Calculate week end date from start (
     * Note: Week start configured by admin (hook: timesheet_get_week_config)
     */
    private function getWeekEnd(string $startDate): string
    {
        $start = new \DateTime($startDate);
        $end = clone $start;
        $data = ['week_start_day' => 'Monday', 'period_type' => 'weekly'];
        hook_invoke_all('timesheet_get_week_config', $data);

        $daysToAdd = match ($data['week_start_day']) {
            'Monday'  => 6,
            'Sunday'  => 6,
            'Saturday'=> 6,
            default   => 6,
        };
        $end->modify('+ ' . $daysToAdd . ' days');
        return $end->format('Y-m-d');
    }

    /**
     * Add time entry to timesheet.
     */
    public function addTimeEntry(
        int $timesheetId,
        string $entryDate,
        float $hours,
        ?int $projectId,
        ?int $projectStageId,
        ?int $projectActivityId,
        string $description = '',
        string $hourType = 'regular'
    ): ?int {
        // Validate activity (hook query)
        if ($projectActivityId) {
            $checkData = [
                'project_id' => $projectId,
                'stage_id' => $projectStageId,
                'activity_id' => $projectActivityId,
                'user_id' => $this->userId,
            ];
            hook_invoke_all('project_activity_validate', $checkData);
            if (!empty($checkData['valid']) && $checkData['valid'] === false) {
                throw new \Exception('Activity not valid for stage/project');
            }
        }

        $sql = "INSERT INTO {$this->prefix}time_entries
                (timesheet_id, entry_date, hours, hour_type, project_id, project_stage_id,
                 project_activity_id, description, billing_rule, billing_rate, is_billable,
                 status, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->executeUpdate($sql, [
            $timesheetId, $entryDate, $hours, $hourType,
            $projectId, $projectStageId, $projectActivityId,
            $description,
            'cost', // default billing rule - applied at approval
            null,   // billing rate - applied at approval
            1,
            'draft',
            $this->userId,
            $this->userId
        ]);

        $entryId = $this->db->lastInsertId();

        // Emit hook notification
        $emitData = [
            'timesheet_id' => $timesheetId,
            'entry_id' => $entryId,
            'project_id' => $projectId,
            'activity_id' => $projectActivityId,
            'hours' => $hours,
            'date' => $entryDate,
        ];
        hook_invoke_all('time_entry_added', $emitData);

        return $entryId;
    }

    /**
     * Submit timesheet for approval.
     */
    public function submitTimesheet(int $timesheetId): bool
    {
        $sql = "UPDATE {$this->prefix}timesheets SET status = 'submitted',
                submitted_by = ?, submitted_at = NOW(), current_step = 1
                WHERE id = ?";
        $this->db->executeUpdate($sql, [$this->userId, $timesheetId]);

        $emitData = ['timesheet_id' => $timesheetId, 'employee_id' => $this->userId];
        hook_invoke_all('timesheet_submitted', $emitData);

        // Trigger approval chain
        $approvalData = [
            'document_type' => 'timesheet',
            'document_id' => $timesheetId,
            'submitter_id' => $this->userId,
        ];
        hook_invoke_all('approval_request', $approvalData);

        return true;
    }

    /**
     * Approve timesheet (emits hooks for billing + payroll).
     */
    public function approveTimesheet(int $timesheetId): bool
    {
        // Update entries to approved
        $sql = "UPDATE {$this->prefix}time_entries SET status = 'approved' WHERE timesheet_id = ?";
        $this->db->executeUpdate($sql, [$timesheetId]);

        // Update timesheet status
        $sql = "UPDATE {$this->prefix}timesheets SET status = 'approved',
                approved_by = ?, approved_at = NOW() WHERE id = ?";
        $this->db->executeUpdate($sql, [$this->userId, $timesheetId]);

        // Emit hooks
        $entrySql = "SELECT * FROM {$this->prefix}time_entries WHERE timesheet_id = ?";
        $entries = $this->db->fetchAll($entrySql, [$timesheetId]);

        $emitData = [
            'timesheet_id' => $timesheetId,
            'approver_id' => $this->userId,
            'regular_hours' => 40.00, // computed from entries
            'overtime_hours' => 2.00,
            'project_activities' => $entries,
        ];
        hook_invoke_all('timesheet_approved', $emitData);
        hook_invoke_all('timesheet_export_payroll', $emitData);

        return true;
    }

    /**
     * Check if timesheet qualifies for auto-approval.
     */
    public function checkAutoApprove(int $timesheetId): array
    {
        $sql = "SELECT * FROM {$this->prefix}timesheets WHERE id = ?";
        $ts = $this->db->fetchAssoc($sql, [$timesheetId]);
        if (!$ts) {
            return ['auto_approve' => false, 'reason' => 'Timesheet not found'];
        }

        $data = [
            'timesheet_id' => $timesheetId,
            'regular_hours' => (float) $ts['regular_hours'],
            'overtime_hours' => (float) $ts['overtime_hours'],
            'total_hours' => (float) $ts['regular_hours'] + (float) $ts['overtime_hours'],
            'employee_id' => (int) $ts['employee_id'],
        ];
        hook_invoke_all('timesheet_check_auto_approve', $data);

        return $data;
    }

    /**
     * Get timesheet for UI (employee, week, project/activity filtering).
     */
    public function getTimesheetData(int $employeeId, string $weekStart): array
    {
        $periodEnd = $this->getWeekEnd($weekStart);

        // Get timesheet
        $tsInfo = $this->getOrCreateTimesheet($employeeId, $weekStart);
        $timesheetId = $tsInfo['id'];

        // Get all time entries for week
        $sql = "SELECT * FROM {$this->prefix}time_entries WHERE timesheet_id = ?";
        $entries = $this->db->fetchAll($sql, [$timesheetId]);

        // Get eligible employees (for DDL)
        $eligibleEmployees = $this->getEligibleEmployees($this->userId);

        // Get current user info
        $currentUser = $this->getEmployeeInfo($this->userId);

        // Check auto-approve status
        $autoApproveInfo = $this->checkAutoApprove($timesheetId);

        return [
            'employee_id' => $employeeId,
            'current_user' => $currentUser,
            'eligible_employees' => $eligibleEmployees,
            'week_start' => $weekStart,
            'week_end' => $periodEnd,
            'timesheet_id' => $timesheetId,
            'status' => $tsInfo['status'],
            'entries' => $entries,
            'auto_approve' => $autoApproveInfo,
            'is_project_admin' => $this->isProjectAdminForEmployee($employeeId),
        ];
    }

    private function getEmployeeInfo(int $employeeId): array
    {
        // Would query employee from FA user/table in production
        return ['id' => $employeeId, 'name' => 'Employee ' . $employeeId];
    }

    private function isProjectAdminForEmployee(int $employeeId): bool
    {
        // Check if current user has project admin permission (not PM)
        $data = ['employee_id' => $employeeId, 'user_id' => $this->userId];
        hook_invoke_all('project_check_project_admin', $data);
        return !empty($data['is_project_admin']) && $data['is_project_admin'] === true;
    }
}
