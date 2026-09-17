<?php
/**
 * Runtime Integration Contracts
 *
 * Framework-independent contract checks intended to complement a full
 * WordPress PHPUnit environment. These checks are non-destructive and can be
 * executed only from trusted development/CI tooling.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gd_client_portal_runtime_integration_contracts() {
    global $wpdb;

    $checks = array();

    $checks['wordpress_runtime'] = array(
        'ok' => function_exists('wp_get_current_user') && function_exists('current_user_can'),
        'detail' => 'WordPress authentication APIs are available.',
    );

    $checks['authorization_runtime'] = array(
        'ok' => function_exists('gd_client_portal_can_access_tenant')
            || function_exists('gd_client_portal_authorize_tenant')
            || function_exists('gd_client_portal_tenant_authorization'),
        'detail' => 'Tenant authorization contract is available.',
    );

    $checks['ajax_security'] = array(
        'ok' => function_exists('check_ajax_referer'),
        'detail' => 'Canonical WordPress AJAX nonce verification is available.',
    );

    $checks['uploads_runtime'] = array(
        'ok' => function_exists('wp_upload_dir'),
        'detail' => 'WordPress uploads runtime is available.',
    );

    $projects_table = $wpdb->prefix . 'gd_projects';
    $checks['project_persistence'] = array(
        'ok' => $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects_table)) === $projects_table,
        'detail' => 'Core projects persistence table is available.',
    );

    $checks['cron_runtime'] = array(
        'ok' => function_exists('wp_next_scheduled') && function_exists('wp_schedule_event'),
        'detail' => 'WordPress scheduling APIs are available.',
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
        'checks' => $checks,
        'all_passed' => $all_passed,
    );
}
