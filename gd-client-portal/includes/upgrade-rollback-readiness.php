<?php
/**
 * Upgrade & Rollback Readiness
 *
 * Read-only deployment lifecycle checks. This module does not run migrations,
 * alter database records, or create backups; it verifies that the environment
 * is prepared for an administrator-controlled upgrade and rollback process.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gd_client_portal_upgrade_rollback_readiness_checks() {
    global $wpdb;

    $plugin_version = defined('GD_CLIENT_PORTAL_VERSION') ? (string) GD_CLIENT_PORTAL_VERSION : '';
    $installed_version = (string) get_option('gdcp_platform_version', '');
    $schema_version = (string) get_option('gdcp_schema_version', '');

    $uploads = wp_upload_dir();
    $uploads_ok = empty($uploads['error']) && !empty($uploads['basedir']) && is_dir($uploads['basedir']) && is_writable($uploads['basedir']);

    $certification = get_option('gd_client_portal_last_production_certification', array());
    $certified = is_array($certification)
        && isset($certification['status'], $certification['plugin_version'])
        && $certification['status'] === 'certified'
        && (string) $certification['plugin_version'] === $plugin_version;

    $projects_table = $wpdb->prefix . 'gd_projects';
    $projects_ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects_table)) === $projects_table;

    $runtime_ok = function_exists('gd_client_portal_platform_runtime_status_ok')
        ? gd_client_portal_platform_runtime_status_ok()
        : false;

    $checks = array(
        'Current plugin version' => array(
            'ok' => $plugin_version !== '',
            'detail' => $plugin_version !== '' ? 'Runtime version: ' . $plugin_version : 'Plugin version is unavailable.',
        ),
        'Installed version metadata' => array(
            'ok' => $installed_version !== '' || $schema_version !== '',
            'detail' => ($installed_version !== '' || $schema_version !== '')
                ? 'Installed metadata found: version=' . ($installed_version ?: 'n/a') . ', schema=' . ($schema_version ?: 'n/a')
                : 'No installed version or schema metadata found.',
        ),
        'Runtime health before upgrade' => array(
            'ok' => $runtime_ok,
            'detail' => $runtime_ok ? 'No recorded bootstrap or activation failures.' : 'Resolve runtime diagnostics before upgrading.',
        ),
        'Core database availability' => array(
            'ok' => $projects_ok,
            'detail' => $projects_ok ? 'Core projects table is available.' : 'Core projects table is missing.',
        ),
        'Writable storage for upgrade operations' => array(
            'ok' => $uploads_ok,
            'detail' => $uploads_ok ? 'WordPress uploads storage is writable.' : 'Uploads storage is unavailable or not writable.',
        ),
        'Production certification evidence' => array(
            'ok' => $certified,
            'detail' => $certified ? 'Latest certification is approved.' : 'Production certification is not currently approved.',
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
        'plugin_version' => $plugin_version,
        'installed_version' => $installed_version,
        'schema_version' => $schema_version,
        'checks' => $checks,
        'all_passed' => $all_passed,
    );
}

function gd_client_portal_upgrade_rollback_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    $report = gd_client_portal_upgrade_rollback_readiness_checks();

    echo '<div class="wrap"><h1>GD Client Portal — Upgrade & Rollback Readiness</h1>';
    echo '<p>This page performs read-only checks before an administrator performs a plugin upgrade. It does not execute migrations, replace plugin files, or create backups automatically.</p>';

    echo '<table class="widefat striped"><thead><tr><th>Deployment requirement</th><th>Status</th><th>Evidence</th></tr></thead><tbody>';
    foreach ($report['checks'] as $label => $check) {
        $status = !empty($check['ok'])
            ? '<span style="color:#16803a;font-weight:700">PASS</span>'
            : '<span style="color:#b32d2e;font-weight:700">BLOCKED</span>';
        echo '<tr><td>' . esc_html($label) . '</td><td>' . $status . '</td><td>' . esc_html($check['detail']) . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h2 style="margin-top:28px">Upgrade Gate</h2>';
    if ($report['all_passed']) {
        echo '<div class="notice notice-success inline"><p><strong>UPGRADE READY</strong> — the configured pre-upgrade checks currently pass.</p></div>';
    } else {
        echo '<div class="notice notice-error inline"><p><strong>UPGRADE BLOCKED</strong> — resolve blocked checks before performing an upgrade.</p></div>';
    }

    echo '<h2 style="margin-top:28px">Rollback Checklist</h2>';
    echo '<ol>';
    echo '<li>Confirm a current WordPress database backup exists.</li>';
    echo '<li>Keep the previously working plugin ZIP available.</li>';
    echo '<li>Record the current plugin and schema versions shown above.</li>';
    echo '<li>After rollback, run Runtime Diagnostics and Automated Regression again.</li>';
    echo '<li>Do not delete plugin data unless the rollback plan explicitly requires it.</li>';
    echo '</ol>';

    echo '<h2>Post-Upgrade Verification</h2>';
    echo '<p>After upgrading, re-run: Functional Readiness → Workflow Verification → Automated Regression → Production Certification.</p>';
    echo '<p><strong>Report generated:</strong> ' . esc_html($report['generated_at']) . '</p>';
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'gd-client-portal',
        'Upgrade & Rollback',
        'Upgrade & Rollback',
        'manage_options',
        'gd-client-portal-upgrade-rollback',
        'gd_client_portal_upgrade_rollback_page'
    );
}, 33);
