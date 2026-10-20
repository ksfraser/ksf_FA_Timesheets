-- Timesheets module - Full Project Services Integration Schema

CREATE TABLE IF NOT EXISTS `0_timesheets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNSIGNED NOT NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `status` ENUM('draft','submitted','pending_approval','approved','rejected','denied','returned') NOT NULL DEFAULT 'draft',
  `approval_chain_id` INT UNSIGNED,
  `regular_hours` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `overtime_hours` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `submitted_by` INT UNSIGNED,
  `submitted_at` DATETIME DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED,
  `updated_by` INT UNSIGNED,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `current_step` INT UNSIGNED DEFAULT 1,
  UNIQUE KEY `uk_employee_period` (`employee_id`, `period_start`),
  KEY `status` (`status`),
  KEY `approval_chain` (`approval_chain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_time_entries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `timesheet_id` INT UNSIGNED NOT NULL,
  `entry_date` DATE NOT NULL,
  `hours` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `hour_type` ENUM('regular','overtime','double_time') NOT NULL DEFAULT 'regular',
  `project_id` INT UNSIGNED DEFAULT NULL,
  `project_stage_id` INT UNSIGNED DEFAULT NULL,
  `project_activity_id` INT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(255),
  `event_id` INT UNSIGNED DEFAULT NULL,
  `event_employee_id` INT UNSIGNED DEFAULT NULL,
  `billing_rule` ENUM('cost','cost_plus','fixed_rate','not_billable') NOT NULL DEFAULT 'cost',
  `billing_rate` DECIMAL(15,2) DEFAULT NULL,
  `is_billable` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('draft','approved') NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED,
  `updated_by` INT UNSIGNED,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `timesheet` (`timesheet_id`),
  KEY `project` (`project_id`),
  KEY `date` (`entry_date`),
  KEY `event` (`event_id`),
  UNIQUE KEY `uk_event_employee` (`event_id`, `event_employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_approval_step_comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `approval_chain_id` INT UNSIGNED NOT NULL,
  `document_type` ENUM('timesheet','expense_report','purchase_order') NOT NULL,
  `document_id` INT UNSIGNED NOT NULL,
  `step` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status_change` ENUM('draft','submitted','pending_approval','approved','rejected','denied','returned','reimbursed') NOT NULL,
  `comment` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `chain_step` (`approval_chain_id`, `step`),
  KEY `document` (`document_type`, `document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_timesheet_config` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `week_start_day` ENUM('Monday','Sunday','Saturday') NOT NULL DEFAULT 'Monday',
  `period_type` ENUM('weekly','biweekly','monthly') NOT NULL DEFAULT 'weekly',
  `max_regular_hours` DECIMAL(6,2) NOT NULL DEFAULT 40,
  `max_overtime_hours` DECIMAL(6,2) NOT NULL DEFAULT 20,
  `auto_approve_small` TINYINT(1) NOT NULL DEFAULT 0,
  `auto_approve_threshold_amount` DECIMAL(15,2) DEFAULT 50.00,
  `auto_approve_small_expense_categories` VARCHAR(255) DEFAULT 'meals,hotel,travel',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_by` INT UNSIGNED,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `config_single` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `0_timesheet_config` (`id`, `week_start_day`, `period_type`) VALUES (1, 'Monday', 'weekly');

-- ---------------------------------------------------------------------------
-- Module-local employee directory.
--
-- WHY THIS TABLE EXISTS (and why it is not FA core):
--   FA core has no employee entity. In this estate an employee is an FA
--   person (0_crm_persons) plus a crm_categories row of type 'employee' plus
--   a satellite employment table - the satellite belongs to ksf_FA_HRM
--   (0_hrm_contacts_employment). Reaching into another module's table would
--   make this module depend on that module being activated, which
--   AGENTS_ARCH.md 12.2 forbids ("a shared seam must never be gated on
--   another module's activation"). The contact resolver in ksf-fa-common
--   deliberately does not cover the employee intent either.
--   So this module keeps its OWN employee directory, keyed by the same
--   employee_id its own records already carry. It is module-local by design.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `0_ksf_timesheets_employees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
