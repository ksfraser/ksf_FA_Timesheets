<?php
/**
 * FA_Timesheets Module Hooks for FrontAccounting
 */

define('SS_TIMESHEETS', 138 << 8);

class hooks_ksf_FA_Timesheets extends hooks {

    private function ensure_composer_dependencies(): void {
        $module_dir = dirname(__FILE__);
        $autoload_path = $module_dir . '/vendor/autoload.php';
        
        if (!file_exists($autoload_path)) {
            $composer_path = $module_dir . '/composer.json';
            if (file_exists($composer_path)) {
                chdir($module_dir);
                $output = [];
                $return_code = 0;
                exec('composer install --no-interaction --prefer-dist 2>&1', $output, $return_code);
                if ($return_code !== 0) {
                    error_log('KSF Module: composer install failed: ' . implode("\n", $output));
                }
            }
        }
    }

    function install_options($app) {
        global $path_to_root;

        switch($app->id) {
            case 'HR':
                $app->add_lapp_function(0, _("Timesheets"),
                    $path_to_root."/modules/".$this->module_name."/timesheets.php", 'SA_TIMESHEETVIEW', MENU_ENTRY);
                $app->add_lapp_function(1, _("Create Entry"),
                    $path_to_root."/modules/".$this->module_name."/create.php", 'SA_TIMESHEETCREATE', MENU_ENTRY);
                $app->add_lapp_function(2, _("Summaries"),
                    $path_to_root."/modules/".$this->module_name."/summaries.php", 'SA_TIMESHEETVIEW', MENU_INQUIRY);
                $app->add_rapp_function(3, _("Approve Timesheets"),
                    $path_to_root."/modules/".$this->module_name."/approve.php", 'SA_TIMESHEETAPPROVE', MENU_INQUIRY);
                break;
        }
    }

    function install_access() {
        $security_sections[SS_TIMESHEETS] = _("Timesheets Management");
        $security_areas['SA_TIMESHEETVIEW'] = array(SS_TIMESHEETS | 1, _("View Timesheets"));
        $security_areas['SA_TIMESHEETCREATE'] = array(SS_TIMESHEETS | 2, _("Create Time Entries"));
        $security_areas['SA_TIMESHEETAPPROVE'] = array(SS_TIMESHEETS | 3, _("Approve/Reject Timesheets"));
        $security_areas['SA_TIMESHEETREPORTS'] = array(SS_TIMESHEETS | 4, _("View Timesheet Reports"));
        return array($security_areas, $security_sections);
    }

    function install_extension($check_only=true) {
        return true;
    }

    function install_tabs($app) {
    }

    function activate_extension($company, $check_only=true) {
        $updates = array('sql/update.sql' => array($this->module_name));
        $ok = $this->update_databases($company, $updates, $check_only);
        if ($check_only || !$ok) {
            return $ok;
        }
        $this->ensure_timesheets_schema();
        return $ok;
    }

    private function table_exists($table) {
        $sql = "SHOW TABLES LIKE " . db_escape($table);
        $res = db_query($sql, 'Failed checking table existence');
        return db_num_rows($res) > 0;
    }

    private function ensure_timesheets_schema() {
        $tables = array(
            TB_PREF . "fa_timesheet_entries" => "
                CREATE TABLE IF NOT EXISTS `" . TB_PREF . "fa_timesheet_entries` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `employee_id` VARCHAR(100) NOT NULL,
                    `work_date` DATE NOT NULL,
                    `hours` DECIMAL(10,2) NOT NULL,
                    `project_id` VARCHAR(20) DEFAULT NULL,
                    `task_id` VARCHAR(20) DEFAULT NULL,
                    `description` TEXT,
                    `status` VARCHAR(20) DEFAULT 'Draft',
                    `approved_by` VARCHAR(100) DEFAULT NULL,
                    `approved_at` DATETIME DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_employee` (`employee_id`),
                    KEY `idx_date` (`work_date`),
                    KEY `idx_status` (`status`),
                    KEY `idx_project` (`project_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            TB_PREF . "fa_timesheet_summaries" => "
                CREATE TABLE IF NOT EXISTS `" . TB_PREF . "fa_timesheet_summaries` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `employee_id` VARCHAR(100) NOT NULL,
                    `period_start` DATE NOT NULL,
                    `period_end` DATE NOT NULL,
                    `total_hours` DECIMAL(10,2) DEFAULT 0,
                    `status` VARCHAR(20) DEFAULT 'Open',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_employee` (`employee_id`),
                    KEY `idx_period` (`period_start`, `period_end`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach ($tables as $table_name => $sql) {
            db_query($sql, "Could not create Timesheets table: $table_name");
        }
    }

    function db_prevoid($trans_type, $trans_no) {
        // Handle voiding if needed
    }
}
?>
