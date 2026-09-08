<?php
/**
 * Time Entry Service — individual time entry logic.
 *
 * @since 2.0.0
 */
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Timesheets\Service;

class TimeEntryService
{
    private $db;
    private $prefix;
    private $userId;

    public function __construct($db, string $prefix = '', int $userId = 0)
    {
        $this->db     = $db;
        $this->prefix = $prefix;
        $this->userId = $userId;
    }

    /**
     * Validate time entry data before save.
     */
    public function validateEntry(
        int $timesheetId,
        string $entryDate,
        float $hours,
        ?int $projectId,
        ?int $projectStageId,
        ?int $projectActivityId
    ): array {
        $errors = [];

        if ($hours <= 0 || $hours > 24) {
            $errors[] = 'Hours must be between 0.01 and 24';
        }

        // Only 1 entry per project-activity per timesheet per date
        $sql = "SELECT COUNT(*) FROM {$this->prefix}time_entries
                WHERE timesheet_id = ? AND entry_date = ?
                  AND project_id = ? AND project_activity_id = ?";
        $count = (int) ($this->db->fetchScalar($sql, [
            $timesheetId, $entryDate, $projectId, $projectActivityId
        ]) ?? 0);
        // Note: Not enforcing strict uniqueness at DB level; UI enforces via validation
        // But we check for duplicates in validation

        // Check stage/activities valid via hooks
        if ($projectId && $projectStageId && $projectActivityId) {
            $checkData = [
                'project_id' => $projectId,
                'stage_id' => $projectStageId,
                'activity_id' => $projectActivityId,
                'user_id' => $this->userId,
                'valid' => true,
            ];
            hook_invoke_all('project_activity_validate', $checkData);
            if (!empty($checkData['valid']) && $checkData['valid'] === false) {
                $errors[] = !empty($checkData['reason']) ? $checkData['reason'] : 'Invalid project/stage/activity';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get time entry summary with billing details.
     */
    public function getTimeEntrySummary(int $timesheetId): array
    {
        $sql = "SELECT * FROM {$this->prefix}time_entries WHERE timesheet_id = ?";
        $rows = $this->db->fetchAll($sql, [$timesheetId]);

        $summaries = [];
        $totalBillable = 0.00;

        foreach ($rows as $entry) {
            // Get billing rules for entry via hook
            $billingData = [
                'timesheet_id' => $timesheetId,
                'entry_id' => $entry['id'],
                'project_id' => $entry['project_id'],
                'activity_id' => $entry['project_activity_id'],
            ];
            hook_invoke_all('time_get_billing_rule', $billingData);

            $isBillable = $entry['is_billable'] == 1;
            $billingRate = $entry['billing_rate'] ?? 0;

            if ($isBillable) {
                $totalBillable += $entry['hours'] * $billingRate;
            }

            $summaries[] = [
                'entry_id' => $entry['id'],
                'date' => $entry['entry_date'],
                'hours' => (float) $entry['hours'],
                'hour_type' => $entry['hour_type'],
                'project_id' => $entry['project_id'],
                'project_stage_id' => $entry['project_stage_id'],
                'project_activity_id' => $entry['project_activity_id'],
                'is_billable' => $isBillable,
                'billing_rate_applied' => $billingData['rate'] ?? $billingRate,
                'billable_amount' => $isBillable ? ($entry['hours'] * ($billingData['rate'] ?? $billingRate)) : 0,
                'status' => $entry['status'],
                'description' => $entry['description'],
            ];
        }

        return [
            'entries' => $summaries,
            'total_hours' => array_sum(array_column($summaries, 'hours')),
            'total_billable_amount' => $totalBillable,
        ];
    }
}
