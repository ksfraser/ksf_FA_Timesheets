-- v2.4.3 — BR-007 event-close timesheets flow.
-- Adds the (event_id, event_employee_id) guard columns on 0_time_entries so
-- existing installs can be upgraded idempotently (fresh installs get the full
-- DDL from install.sql, including the UNIQUE uk_event_employee key).

ALTER TABLE `0_time_entries`
    ADD COLUMN IF NOT EXISTS `event_id` INT UNSIGNED DEFAULT NULL AFTER `description`;

ALTER TABLE `0_time_entries`
    ADD COLUMN IF NOT EXISTS `event_employee_id` INT UNSIGNED DEFAULT NULL AFTER `event_id`;