<?php
/**
 * FA_Timesheets Module Hooks for FrontAccounting
 */

define('SS_TIMESHEETS', 138 << 8);

// ---------------------------------------------------------------------------
// Ensure Composer autoloader is loaded before the class definition so that
// trait dependencies (HookQueryProviderTrait) are available at class-load time.
// ---------------------------------------------------------------------------
$moduleAutoload = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($moduleAutoload)) {
    require_once $moduleAutoload;
}

class hooks_fa_timesheets extends hooks {
    use \Ksfraser\Traits\HookQueryProviderTrait;

    private function ensure_composer_dependencies(): void {
        $module_dir = dirname(__FILE__);
        $autoload_path = $module_dir . '/vendor/autoload.php';

        if (!file_exists($autoload_path)) {
            $composer_path = $module_dir . '/composer.json';
            if (file_exists($composer_path)) {
                chdir($module_dir);
                $output = array();
                $return_code = 0;
                exec('composer install --no-interaction --prefer-dist 2>&1', $output, $return_code);
                if ($return_code !== 0) {
                    error_log('KSF Module: composer install failed: ' . implode("\n", $output));
                }
            }
        }
    }

    /**
     * Return all values this module advertises via the query hook system.
     *
     * @return array<string, mixed>
     */
    protected function _getAdvertisedValues(): array
    {
        return array(
            'timesheets.sec_section'   => SS_TIMESHEETS,
            'timesheets.version'       => $this->version,
            'timesheets.module_name'   => $this->module_name,
            'timesheets.hooks_version' => '2.0',
        );
    }

}
?>
