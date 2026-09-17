<?php
/**
 * GD Client Portal Release Candidate Gate
 *
 * Final read-only deployment gate. It does not mutate application data and
 * deliberately reports live-environment requirements separately from checks
 * that can be proven from the current WordPress runtime.
 */
if (!defined('ABSPATH')) { exit; }

if (!function_exists('gd_client_portal_release_candidate_checks')) {
    function gd_client_portal_release_candidate_checks() {
        global $wpdb;
        $checks = array();

        $version = defined('GD_CLIENT_PORTAL_VERSION') ? (string) GD_CLIENT_PORTAL_VERSION : '';
        $checks['Plugin version'] = array('ok' => $version !== '', 'detail' => $version ?: 'Unavailable.');

        $runtime_ok = function_exists('gd_client_portal_platform_runtime_status_ok')
            ? (bool) gd_client_portal_platform_runtime_status_ok() : false;
        $checks['Runtime diagnostics'] = array(
            'ok' => $runtime_ok,
            'detail' => $runtime_ok ? 'No recorded bootstrap or activation failures.' : 'Runtime failures are recorded or diagnostics are unavailable.'
        );

        $projects = $wpdb->prefix . 'gd_projects';
        $projects_ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects)) === $projects;
        $checks['Core project table'] = array('ok' => $projects_ok, 'detail' => $projects_ok ? 'Present.' : 'Missing.');

        $uploads = wp_upload_dir();
        $uploads_ok = empty($uploads['error']) && !empty($uploads['basedir']) && is_dir($uploads['basedir']) && is_writable($uploads['basedir']);
        $checks['Writable uploads storage'] = array('ok' => $uploads_ok, 'detail' => $uploads_ok ? 'Writable.' : 'Unavailable or not writable.');

        $cron_ok = function_exists('wp_next_scheduled') && function_exists('wp_schedule_event');
        $checks['Cron API'] = array('ok' => $cron_ok, 'detail' => $cron_ok ? 'WordPress cron API available.' : 'Required cron API unavailable.');

        $required_hooks = array('gd_client_portal_automation_daily','gd_client_portal_sla_daily','gd_client_portal_payment_daily','gd_client_portal_automation_reliability_tick');
        $missing_hooks = array();
        if ($cron_ok) {
            foreach ($required_hooks as $hook) {
                if (!wp_next_scheduled($hook)) { $missing_hooks[] = $hook; }
            }
        } else { $missing_hooks = $required_hooks; }
        $checks['Background schedules'] = array(
            'ok' => empty($missing_hooks),
            'detail' => empty($missing_hooks) ? 'All required recurring hooks are scheduled.' : 'Missing: ' . implode(', ', $missing_hooks) . '.'
        );

        $db_errors = get_option('gd_client_portal_platform_db_errors', array());
        $db_errors = is_array($db_errors) ? $db_errors : array();
        $checks['Recorded database migration errors'] = array(
            'ok' => empty($db_errors),
            'detail' => empty($db_errors) ? 'None recorded.' : count($db_errors) . ' recorded error(s); review before release.'
        );

        $automation_failures = get_option('gd_client_portal_activation_failures', array());
        $automation_failures = is_array($automation_failures) ? $automation_failures : array();
        $checks['Activation failures'] = array(
            'ok' => empty($automation_failures),
            'detail' => empty($automation_failures) ? 'None recorded.' : count($automation_failures) . ' recorded activation failure(s).'
        );

        $cert = get_option('gd_client_portal_last_production_certification', array());
        $cert_ok = is_array($cert) && isset($cert['status']) && $cert['status'] === 'certified';
        $checks['Production certification evidence'] = array(
            'ok' => $cert_ok,
            'detail' => $cert_ok ? 'Current certification evidence exists.' : 'Certification evidence is not approved.'
        );

        $all_passed = true;
        foreach ($checks as $check) { if (empty($check['ok'])) { $all_passed = false; break; } }
        return array('generated_at'=>current_time('mysql'),'plugin_version'=>$version,'checks'=>$checks,'all_passed'=>$all_passed);
    }
}

if (!function_exists('gd_client_portal_release_candidate_page')) {
    function gd_client_portal_release_candidate_page() {
        if (!current_user_can('manage_options')) { wp_die(__('Access denied.', 'gd-client-portal')); }
        $report = gd_client_portal_release_candidate_checks();
        echo '<div class="wrap"><h1>GD Client Portal — Release Candidate Gate</h1>';
        echo '<p>Final read-only gate for deployment. A blocked item should be resolved or explicitly accepted by the deployment owner before release.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Gate</th><th>Status</th><th>Evidence</th></tr></thead><tbody>';
        foreach ($report['checks'] as $label=>$check) {
            $status = !empty($check['ok']) ? '<strong style="color:#16803a">PASS</strong>' : '<strong style="color:#b32d2e">BLOCKED</strong>';
            echo '<tr><td>'.esc_html($label).'</td><td>'.$status.'</td><td>'.esc_html($check['detail']).'</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h2 style="margin-top:28px">Release Decision</h2>';
        if ($report['all_passed']) {
            echo '<div class="notice notice-success inline"><p><strong>RELEASE CANDIDATE READY</strong> — all runtime gates currently pass.</p></div>';
        } else {
            echo '<div class="notice notice-error inline"><p><strong>RELEASE CANDIDATE BLOCKED</strong> — resolve the blocked gates before deployment.</p></div>';
        }
        echo '<h2 style="margin-top:28px">Live-environment verification</h2><ul>';
        echo '<li>Test login, tenant isolation, project collaboration, approvals, delivery, billing, and support on staging.</li>';
        echo '<li>Verify scheduled jobs execute under the host cron configuration.</li>';
        echo '<li>Run the existing Automated Regression and controlled scenario suite.</li>';
        echo '<li>Confirm a current database backup and tested rollback package exist.</li>';
        echo '</ul>';
        echo '<p><strong>Report generated:</strong> '.esc_html($report['generated_at']).'</p></div>';
    }
}

add_action('admin_menu', function () {
    add_submenu_page('gd-client-portal','Release Candidate Gate','Release Candidate','manage_options','gd-client-portal-release-candidate','gd_client_portal_release_candidate_page');
}, 32);
