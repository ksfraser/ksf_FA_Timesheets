<?php
/**
 * Timesheets Module for FrontAccounting
 */

$module_id = 'Timesheets';
$module_version = '1.0.0';
$module_name = 'Timesheets';
$module_description = 'Employee time tracking and timesheet management';

$module_tables = [
    'fa_timesheet_entries',
    'fa_timesheet_summaries',
];

$module_capabilities = [
    'SA_TIMESHEETVIEW' => 'View Timesheets',
    'SA_TIMESHEETCREATE' => 'Create Time Entries',
    'SA_TIMESHEETAPPROVE' => 'Approve/Reject Timesheets',
    'SA_TIMESHEETREPORTS' => 'View Timesheet Reports',
];

function timesheets_install(): bool
{
    global $db, $db_multi_sql;
    $sql_file = dirname(__FILE__) . '/../sql/install.sql';
    if (!file_exists($sql_file)) return false;
    $sql = file_get_contents($sql_file);
    return $db_multi_sql($sql);
}

function timesheets_enable(): bool
{
    global $db;
    return $db->query("UPDATE " . TB_PREF . "modules SET enabled = 1 WHERE name = 'Timesheets'");
}

function timesheets_disable(): bool
{
    global $db;
    return $db->query("UPDATE " . TB_PREF . "modules SET enabled = 0 WHERE name = 'Timesheets'");
}

function timesheets_remove(): bool
{
    global $db, $db_multi_sql;
    $sql = "DROP TABLE IF EXISTS " . TB_PREF . "timesheet_summaries;
           DROP TABLE IF EXISTS " . TB_PREF . "timesheet_entries;
           DELETE FROM " . TB_PREF . "modules WHERE name = 'Timesheets';";
    return $db_multi_sql($sql);
}

add_module($module_name, $module_version, $module_description);