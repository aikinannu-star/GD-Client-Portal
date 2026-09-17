<?php
/**
 * GD Client Portal Release Governance
 *
 * Read-only pre-release checks for version consistency, migration readiness,
 * runtime certification status, and release artifact hygiene.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gd_client_portal_release_governance_version() {
    $plugin_file = defined('GD_CLIENT_PORTAL_FILE') ? GD_CLIENT_PORTAL_FILE : dirname(__DIR__) . '/gd-client-portal.php';
    if ( function_exists('get_file_data') ) {
        $data = get_file_data($plugin_file, array('Version' => 'Version'), 'plugin');
        if ( ! empty($data['Version']) ) {
            return (string) $data['Version'];
        }
    }

    return defined('GD_CLIENT_PORTAL_VERSION') ? (string) GD_CLIENT_PORTAL_VERSION : '';
}

function gd_client_portal_release_governance_checks() {
    global $wpdb;

    $version = gd_client_portal_release_governance_version();

    $runtime_ok = function_exists('gd_client_portal_platform_runtime_status_ok')
        ? gd_client_portal_platform_runtime_status_ok()
        : false;

    $certification = get_option('gd_client_portal_last_production_certification', array());
    $certified = is_array($certification)
        && isset($certification['status'], $certification['plugin_version'])
        && $certification['status'] === 'certified'
        && (string) $certification['plugin_version'] === $version;

    $schema_version = get_option('gdcp_schema_version', '');
    $installed_version = get_option('gdcp_platform_version', '');

    $projects_table = $wpdb->prefix . 'gd_projects';
    $projects_table_ok = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $projects_table)
    ) === $projects_table;

    $uploads = wp_upload_dir();
    $uploads_ok = empty($uploads['error'])
        && ! empty($uploads['basedir'])
        && is_dir($uploads['basedir'])
        && is_writable($uploads['basedir']);

    $checks = array(
        'Plugin version available' => array(
            'ok' => $version !== '',
            'detail' => $version !== '' ? $version : 'No plugin version could be resolved.',
        ),
        'Runtime diagnostics' => array(
            'ok' => $runtime_ok,
            'detail' => $runtime_ok ? 'No recorded runtime failures.' : 'Runtime diagnostics are failing or unavailable.',
        ),
        'Production certification' => array(
            'ok' => $certified,
            'detail' => $certified ? 'Latest certification is approved.' : 'A current approved production certification is required.',
        ),
        'Migration/schema metadata' => array(
            'ok' => absint($schema_version) > 0 || $installed_version !== '',
            'detail' => ($schema_version !== '' || $installed_version !== '')
                ? 'Migration/version metadata is present.'
                : 'No migration/version metadata was found.',
        ),
        'Core project persistence' => array(
            'ok' => $projects_table_ok,
            'detail' => $projects_table_ok ? 'Projects table is available.' : 'Projects table is missing.',
        ),
        'Upload storage readiness' => array(
            'ok' => $uploads_ok,
            'detail' => $uploads_ok ? 'Uploads storage is writable.' : 'Uploads storage is unavailable or not writable.',
        ),
    );

    $all_passed = true;
    foreach ($checks as $check) {
        if (empty($check['ok'])) {
            $all_passed = false;
            break;
        }
    }

    return array(
        'generated_at' => current_time('mysql'),
        'plugin_version' => $version,
        'checks' => $checks,
        'all_passed' => $all_passed,
    );
}

function gd_client_portal_release_governance_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    $report = gd_client_portal_release_governance_checks();

    echo '<div class="wrap"><h1>GD Client Portal — Release Governance</h1>';
    echo '<p>Run these checks before creating or distributing a production release artifact.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Pre-release requirement</th><th>Status</th><th>Evidence</th></tr></thead><tbody>';

    foreach ($report['checks'] as $label => $check) {
        $status = !empty($check['ok'])
            ? '<span style="color:#16803a;font-weight:700">PASS</span>'
            : '<span style="color:#b32d2e;font-weight:700">BLOCKED</span>';

        echo '<tr><td>' . esc_html($label) . '</td><td>' . $status . '</td><td>' . esc_html($check['detail']) . '</td></tr>';
    }

    echo '</tbody></table><h2 style="margin-top:28px">Release Gate</h2>';

    if ($report['all_passed']) {
        echo '<div class="notice notice-success inline"><p><strong>RELEASE GATE PASSED</strong> — the current environment satisfies the configured pre-release requirements.</p></div>';
    } else {
        echo '<div class="notice notice-error inline"><p><strong>RELEASE BLOCKED</strong> — resolve every blocked requirement before creating a production release.</p></div>';
    }

    echo '<p><strong>Report generated:</strong> ' . esc_html($report['generated_at']) . '</p>';
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'gd-client-portal',
        'Release Governance',
        'Release Governance',
        'manage_options',
        'gd-client-portal-release-governance',
        'gd_client_portal_release_governance_page'
    );
}, 32);
