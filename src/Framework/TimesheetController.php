<?php
/**
 * Timesheet Entry UI Controller — Implements ARCH-TIME-UI-001 design.
 *
 * Features:
 * - Employee DDL (current user + direct/reports + recursive + project members)
 * - Week DDL (admin-configured start day)
 * - Project/Stage/Activity DDLs (filtered by assignment, stage constraints)
 * - 7-day summary table with editable fields, Edit/Remove buttons
 * - Only most recent row editable until Add clicked
 * - Save (draft) / Submit (approval) buttons
 * - Auto-approve rules (hook-based)
 * - Status tracking (draft/submitted/approved/denied/rejected/returned/reimbursed)
 * - Comments for status changes
 *
 * @since 2.0.0
 */
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Timesheets\Controller;

class TimesheetEntryController
{
    private $service;
    private $hooks;
    private $db;
    private $userId;

    public function __construct($db, int $userId)
    {
        $this->db     = $db;
        $this->userId = $userId;
        $this->service = new \ksfraser\FrontAccounting\Timesheets\Service\TimesheetService(
            $db, TB_PREF, $userId
        );
        $this->hooks  = new \ksfraser\FrontAccounting\Timesheets\Hooks\TimesheetHooks(
            $db, TB_PREF, $userId
        );
    }

    /**
     * Main entry: render timesheet UI.
     */
    public function render(int $employeeId = 0): array
    {
        $employeeId = $employeeId ?: $this->userId;
        $weekStart  = $_GET['week_start'] ?? date('Y-m-d', strtotime('this week'));

        // Verify user can view/edit for this employee
        $canEdit = $this->checkEditPermission($employeeId, $this->userId);
        $isProjectAdmin = $this->isProjectAdminForEmployee($employeeId);

        // Get timesheet data
        $data = $this->service->getTimesheetData($employeeId, $weekStart);

        // Build DDL options
        $employees = $this->service->getEligibleEmployees($this->userId);
        $currentUser = ['id' => $this->userId, 'name' => 'Self'];
        $employeeOptions = $this->buildEmployeeDDL($currentUser, $employees);

        // Build week options (from config)
        $weekOptions = $this->buildWeekDDL();

        // Project/stage/activity DDL filtering handled in view layer

        return [
            'employee_id'         => $employeeId,
            'current_user_id'     => $this->userId,
            'can_edit'            => $canEdit,
            'is_project_admin'    => $isProjectAdmin,
            'employee_options'    => $employeeOptions,
            'week_options'        => $weekOptions,
            'current_week'        => $weekStart,
            'current_week_end'    => $data['week_end'],
            'timesheet_id'        => $data['timesheet_id'],
            'status'              => $data['status'],
            'timesheet_entries'   => $data['entries'],
            'is_draft'            => $data['status'] === 'draft',
            'is_submitted'        => $data['status'] === 'submitted',
            'is_approved'         => $data['status'] === 'approved',
            'is_returned'         => $data['status'] === 'returned',
            'is_rejected'         => $data['status'] === 'rejected',
            'is_denied'           => $data['status'] === 'denied',
            'is_reimbursed'       => $data['status'] === 'reimbursed',
            'auto_approve'        => $data['auto_approve'],
        ];
    }

    /**
     * Check if user can edit timesheet for employee.
     * - Current user can edit self
     * - User with reports can edit direct/reports
     * - Project admin (not PM) can edit project team members
     */
    private function checkEditPermission(int $targetEmployee, int $currentUser): bool
    {
        if ($currentUser === $targetEmployee) {
            return true;
        }

        // Direct/reports check via org chart
        $data = [
            'current_user_id' => $currentUser,
            'include_recursive' => true,
        ];
        hook_invoke_all('orgchart_get_reports', $data);
        $eligibleEmployees = array_column($data['reports'] ?? [], 'employee_id');
        if (in_array($targetEmployee, $eligibleEmployees)) {
            return true;
        }

        // Project admin check
        $adminCheck = ['user_id' => $currentUser, 'is_project_admin' => false];
        hook_invoke_all('project_check_project_admin', $adminCheck);
        return $adminCheck['is_project_admin'] === true;
    }

    /**
     * Check if user is project admin for employee.
     */
    private function isProjectAdminForEmployee(int $employeeId): bool
    {
        $data = [
            'user_id' => $this->userId,
            'employee_id' => $employeeId,
            'is_project_admin' => false,
        ];
        hook_invoke_all('project_check_project_admin', $data);
        return $data['is_project_admin'] === true;
    }

    private function buildEmployeeDDL(array $currentUser, array $eligibleEmployees): array
    {
        $options = [
            ['value' => $currentUser['id'], 'label' => 'Self (' . $currentUser['name'] . ')', 'default' => true],
        ];

        // Direct/reports + recursive reports (via hook)
        $allEmployees = $eligibleEmployees;
        $allEmployees[] = $this->userId;
        $allEmployees = array_unique($allEmployees);

        foreach ($allEmployees as $empId) {
            if ($empId === $currentUser['id']) {
                continue; // Already added as default
            }
            // In production: query employee info from FA user table
            $label = 'Employee ' . $empId;
            $options[] = ['value' => $empId, 'label' => $label, 'default' => false];
        }

        return $options;
    }

    private function buildWeekDDL(): array
    {
        $data = ['week_start_day' => 'Monday', 'period_type' => 'weekly'];
        hook_invoke_all('timesheet_get_week_config', $data);

        $startDay = $data['week_start_day'];
        $periodType = $data['period_type'];

        // Build options for current month
        $today = new \DateTime();
        $currentMonth = $today->format('Y-m');
        $weeks = [];

        for ($i = 0; $i < 4; $i++) {
            $start = clone $today;
            if ($startDay === 'Monday') {
                $start->modify('last Monday');
            } elseif ($startDay === 'Sunday') {
                $start->modify('last Sunday');
            }
            $start->modify('+ ' . ($i * 7) . ' days');
            $weeks[] = $start->format('Y-m-d');
        }

        return array_map(function ($w) {
            return [
                'value' => $w,
                'label' => 'Week of ' . $w,
                'default' => false,
            ];
        }, $weeks);
    }
}
