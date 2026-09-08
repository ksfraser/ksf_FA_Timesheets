<?php
/**
 * Timesheet Hooks — Integration with Project Services, Approval, Payroll.
 *
 * Implements hook contracts from ARCH-PROJECT-SERVICES-integration-contract.md.
 *
 * @since 2.0.0
 */
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Timesheets\Hooks;

use ksfraser\FrontAccounting\Timesheets\Service\TimesheetService;

class TimesheetHooks
{
    private $service = null;
    private $db = null;
    private $userId = 0;

    public function __construct($db = null, string $prefix = '', int $userId = 0)
    {
        $this->db     = $db;
        $this->prefix = $prefix;
        $this->userId = $userId;
    }

    public function initService(): void
    {
        if ($this->service === null) {
            $this->service = new TimesheetService($this->db, $this->prefix, $this->userId);
        }
    }

    /**
     * Emit timesheet submitted event.
     *
     * @param array $data
     */
    public function emitTimesheetSubmitted(array &$data): void
    {
        $this->initService();
        if (!empty($data['timesheet_id'])) {
            $data['status'] = 'submitted';
            hook_invoke_all('timesheet_submitted', $data);
        }
    }

    /**
     * Handle approval result for timesheet.
     */
    public function handleTimesheetApproval(int $timesheetId, string $status, int $approverId): void
    {
        $this->initService();
        $data = [
            'timesheet_id' => $timesheetId,
            'approver_id' => $approverId,
            'status' => $status,
        ];
        if ($status === 'approved') {
            hook_invoke_all('timesheet_approved', $data);
        } else {
            hook_invoke_all('timesheet_rejected', $data);
        }
    }

    /**
     * Handle timesheet export to payroll.
     */
    public function exportToPayroll(array &$data): void
    {
        $this->initService();
        if (!empty($data['timesheet_id'])) {
            hook_invoke_all('timesheet_export_payroll', $data);
        }
    }

    /**
     * Query billing rules for time entry.
     */
    public function getBillingRule(array &$data): array
    {
        $this->initService();
        // Billing rules come from contract/project via hook query
        hook_invoke_first('time_get_billing_rule', $data);
        return $data;
    }

    /**
     * Query project stage access check.
     */
    public function checkProjectAccess(array &$data): bool
    {
        hook_invoke_all('project_activity_validate', $data);
        return !empty($data['valid']) && $data['valid'] === true;
    }
}
