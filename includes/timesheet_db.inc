<?php
/**
 * Timesheets Database Functions
 */

function create_time_entry(int $employee_id, string $date, float $hours, int $project_id = null, int $task_id = null, string $description = ''): int
{
    global $db;
    $sql = "INSERT INTO " . TB_PREF . "timesheet_entries 
        (employee_id, date, hours, project_id, task_id, description, created_by)
        VALUES (" . $employee_id . ", " . $db->escape($date) . ", " . $hours . ", " . ($project_id ? $project_id : 'NULL') . ", " . ($task_id ? $task_id : 'NULL') . ", " . $db->escape($description) . ", " . user_id() . ")";
    $db->query($sql);
    return $db->insert_id;
}

function get_time_entry(int $id): ?array
{
    global $db;
    $sql = "SELECT * FROM " . TB_PREF . "timesheet_entries WHERE id = " . $id;
    $result = $db->query($sql);
    if ($result && $db->num_rows($result) > 0) {
        return $db->fetch($result);
    }
    return null;
}

function update_time_entry(int $id, float $hours, int $project_id = null, int $task_id = null, string $description = ''): bool
{
    global $db;
    $sql = "UPDATE " . TB_PREF . "timesheet_entries SET hours = " . $hours . ", project_id = " . ($project_id ? $project_id : 'NULL') . ", task_id = " . ($task_id ? $task_id : 'NULL') . ", description = " . $db->escape($description) . " WHERE id = " . $id;
    return $db->query($sql);
}

function delete_time_entry(int $id): bool
{
    global $db;
    $sql = "DELETE FROM " . TB_PREF . "timesheet_entries WHERE id = " . $id;
    return $db->query($sql);
}

function get_time_entries(array $filters = []): ?object
{
    global $db;
    $sql = "SELECT t.*, e.name as employee_name, p.name as project_name FROM " . TB_PREF . "timesheet_entries t
        LEFT JOIN " . TB_PREF . "hr_employees e ON t.employee_id = e.id
        LEFT JOIN " . TB_PREF . "projects p ON t.project_id = p.id WHERE 1=1";
    
    if (!empty($filters['employee_id'])) {
        $sql .= " AND t.employee_id = " . $filters['employee_id'];
    }
    if (!empty($filters['date'])) {
        $sql .= " AND t.date = " . $db->escape($filters['date']);
    }
    if (!empty($filters['start_date'])) {
        $sql .= " AND t.date >= " . $db->escape($filters['start_date']);
    }
    if (!empty($filters['end_date'])) {
        $sql .= " AND t.date <= " . $db->escape($filters['end_date']);
    }
    if (!empty($filters['project_id'])) {
        $sql .= " AND t.project_id = " . $filters['project_id'];
    }
    if (!empty($filters['status'])) {
        $sql .= " AND t.status = " . $db->escape($filters['status']);
    }
    
    $sql .= " ORDER BY t.date DESC";
    return $db->query($sql);
}

function get_pending_time_entries(): ?object
{
    global $db;
    $sql = "SELECT t.*, e.name as employee_name FROM " . TB_PREF . "timesheet_entries t
        LEFT JOIN " . TB_PREF . "hr_employees e ON t.employee_id = e.id
        WHERE t.status = 'Pending' ORDER BY t.date ASC";
    return $db->query($sql);
}

function approve_time_entry(int $id, int $approver_id): bool
{
    global $db;
    $sql = "UPDATE " . TB_PREF . "timesheet_entries SET status = 'Approved', approved_by = " . $approver_id . ", approved_at = NOW() WHERE id = " . $id;
    return $db->query($sql);
}

function reject_time_entry(int $id, int $approver_id): bool
{
    global $db;
    $sql = "UPDATE " . TB_PREF . "timesheet_entries SET status = 'Rejected', approved_by = " . $approver_id . ", approved_at = NOW() WHERE id = " . $id;
    return $db->query($sql);
}

function get_employee_hours(int $employee_id, string $start_date, string $end_date): float
{
    global $db;
    $sql = "SELECT COALESCE(SUM(hours), 0) as total FROM " . TB_PREF . "timesheet_entries 
        WHERE employee_id = " . $employee_id . " AND date >= " . $db->escape($start_date) . " AND date <= " . $db->escape($end_date);
    $result = $db->query($sql);
    if ($result && $db->num_rows($result) > 0) {
        $row = $db->fetch($result);
        return (float)$row['total'];
    }
    return 0;
}

function get_timesheet_summary(int $employee_id, string $period_start, string $period_end): ?array
{
    global $db;
    $sql = "SELECT * FROM " . TB_PREF . "timesheet_summaries WHERE employee_id = " . $employee_id . " AND period_start = " . $db->escape($period_start);
    $result = $db->query($sql);
    if ($result && $db->num_rows($result) > 0) {
        return $db->fetch($result);
    }
    return null;
}

function create_timesheet_summary(int $employee_id, string $period_start, string $period_end): bool
{
    global $db;
    $total_hours = get_employee_hours($employee_id, $period_start, $period_end);
    $sql = "INSERT INTO " . TB_PREF . "timesheet_summaries (employee_id, period_start, period_end, total_hours, submitted_at)
        VALUES (" . $employee_id . ", " . $db->escape($period_start) . ", " . $db->escape($period_end) . ", " . $total_hours . ", NOW())
        ON DUPLICATE KEY UPDATE total_hours = " . $total_hours . ", submitted_at = NOW()";
    return $db->query($sql);
}