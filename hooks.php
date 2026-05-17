<?php
/**
 * KSF FrontAccounting Module Hooks
 * 
 * STANDARD PATTERNS:
 * 
 * 1. ADDING MODULE TABS
 *    Define a class extending 'application' in hooks.php.
 *    Return new instance from install_tabs().
 *    Include add_extensions() to load other modules' install_options.
 * 
 * 2. ADDING MENU ITEMS TO EXISTING APPS
 *    Use install_options() with switch($app->id).
 *    Use add_module() + add_lapp_function() for new menu section.
 * 
 * 3. DATABASE SCHEMA
 *    DO NOT create tables in PHP code.
 *    Use sql/install.sql with @TB_PREF@ placeholders.
 *    Call $this->update_databases() in activate_extension().
 * 
 * 4. SECURITY
 *    Define SS_<MODULE> constant (section << 8).
 *    Define SA_<MODULE>VIEW and SA_<MODULE>MANAGE in install_access().
 * 
 * @package KsfFA_ksf_FA_Timesheets
 * @version 2.4.3
 */

define('SS_ksf_FA_Timesheets', 138 << 8);

class hooks_ksf_FA_Timesheets extends hooks {
    var $module_name = 'ksf_FA_Timesheets';
    var $version = '1.0.0';

    /**
     * Add module tab
     * 
     * Return new application class instance to add a tab.
     * Omit or return nothing to skip tab addition.
     * 
     * @param application|null $app Ignored
     * @return application|null New tab application instance or nothing
     */
    function install_tabs($app) {
        // Override in modules that add apps
        // return new ksf_FA_Timesheets_app();
    }

    /**
     * Add menu items to existing FA applications
     * 
     * @param application $app FA application instance
     */
    function install_options($app) {
        // Override in modules that add menu items
    }

    /**
     * Define security areas
     * 
     * @return array [0] => $security_areas, [1] => $security_sections
     */
    function install_access() {
        $security_sections[SS_ksf_FA_Timesheets] = _("");
        $security_areas['SA_ksf_FA_TimesheetsVIEW'] = array(
            SS_ksf_FA_Timesheets | 1, 
            _("View ")
        );
        $security_areas['SA_ksf_FA_TimesheetsMANAGE'] = array(
            SS_ksf_FA_Timesheets | 2, 
            _("Manage ")
        );
        return array($security_areas, $security_sections);
    }

    /**
     * Activate extension
     * 
     * @param int $company Company number
     * @param bool $check_only Only check if activation possible
     * @return bool Success
     */
    function activate_extension($company, $check_only=true) {
        $this->ensure_composer_dependencies();
        
        // Apply sql/install.sql using update_databases()
        // This handles @TB_PREF@ replacement automatically
        if (file_exists(dirname(__FILE__) . '/sql/install.sql')) {
            $updates = array('install.sql' => array($this->module_name));
            return $this->update_databases($company, $updates, $check_only);
        }
        
        return true;
    }

    /**
     * Install composer dependencies if needed
     */
    private function ensure_composer_dependencies(): void {
        $module_dir = dirname(__FILE__);
        $autoload_path = $module_dir . '/vendor/autoload.php';
        
        if (file_exists($autoload_path)) {
            return;
        }
        
        $composer_path = $module_dir . '/composer.json';
        if (!file_exists($composer_path)) {
            return;
        }
        
        chdir($module_dir);
        $output = [];
        $return_code = 0;
        exec('composer install --no-interaction --prefer-dist 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            error_log('KSF Module: composer install failed: ' . implode("\n", $output));
        }
    }
}
