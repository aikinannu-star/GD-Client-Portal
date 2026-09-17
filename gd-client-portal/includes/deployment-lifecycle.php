<?php
/**
 * Deployment Lifecycle Governance
 *
 * Records administrator-controlled deployment evidence and provides
 * pre-deployment and post-deployment health gates without performing
 * automatic upgrades, rollbacks, backups, or destructive operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gd_client_portal_deployment_lifecycle_precheck() {
    $upgrade = function_exists('gd_client_portal_upgrade_rollback_readiness_checks')
        ? gd_client_portal_upgrade_rollback_readiness_checks()
        : array('all_passed' => false, 'checks' => array());

    $release = function_exists('gd_client_portal_release_governance_checks')
        ? gd_client_portal_release_governance_checks()
        : array('all_passed' => false, 'checks' => array());

    $runtime_ok = function_exists('gd_client_portal_platform_runtime_status_ok')
        ? gd_client_portal_platform_runtime_status_ok()
        : false;

    return array(
        'generated_at' => current_time('mysql'),
        'checks' => array(
            'Release governance' => array(
                'ok' => !empty($release['all_passed']),
                'detail' => !empty($release['all_passed']) ? 'Release gate passed.' : 'Release governance is blocked.',
            ),
            'Upgrade readiness' => array(
                'ok' => !empty($upgrade['all_passed']),
                'detail' => !empty($upgrade['all_passed']) ? 'Upgrade gate passed.' : 'Upgrade readiness is blocked.',
            ),
            'Runtime diagnostics' => array(
                'ok' => $runtime_ok,
                'detail' => $runtime_ok ? 'Runtime diagnostics are healthy.' : 'Runtime diagnostics require attention.',
            ),
        ),
    );
}

function gd_client_portal_deployment_lifecycle_all_passed($report) {
    if (empty($report['checks']) || !is_array($report['checks'])) {
        return false;
    }

    foreach ($report['checks'] as $check) {
        if (empty($check['ok'])) {
            return false;
        }
    }

    return true;
}

function gd_client_portal_deployment_lifecycle_postcheck() {
    $functional = function_exists('gd_client_portal_functional_readiness_checks')
        ? gd_client_portal_functional_readiness_checks()
        : array();

    $workflow = function_exists('gd_client_portal_workflow_verification_checks')
        ? gd_client_portal_workflow_verification_checks()
        : array();

    $runtime_ok = function_exists('gd_client_portal_platform_runtime_status_ok')
        ? gd_client_portal_platform_runtime_status_ok()
        : false;

    $checks = array(
        'Runtime diagnostics' => array(
            'ok' => $runtime_ok,
            'detail' => $runtime_ok ? 'No recorded bootstrap or activation failures.' : 'Runtime failures are recorded.',
        ),
        'Functional readiness API' => array(
            'ok' => !empty($functional),
            'detail' => !empty($functional) ? 'Functional readiness is available for verification.' : 'Functional readiness module is unavailable.',
        ),
        'Workflow verification API' => array(
            'ok' => !empty($workflow),
            'detail' => !empty($workflow) ? 'Workflow verification is available for verification.' : 'Workflow verification module is unavailable.',
        ),
    );

    return array(
        'generated_at' => current_time('mysql'),
        'checks' => $checks,
    );
}

function gd_client_portal_deployment_lifecycle_history() {
    $history = get_option('gd_client_portal_deployment_history', array());
    return is_array($history) ? $history : array();
}

function gd_client_portal_deployment_lifecycle_record($event, $detail) {
    $history = gd_client_portal_deployment_lifecycle_history();

    array_unshift($history, array(
        'time' => current_time('mysql'),
        'event' => sanitize_key($event),
        'detail' => sanitize_text_field($detail),
        'plugin_version' => defined('GD_CLIENT_PORTAL_VERSION') ? GD_CLIENT_PORTAL_VERSION : '',
        'user_id' => gd_client_portal_cached_current_user_id(),
    ));

    $history = array_slice($history, 0, 50);
    update_option('gd_client_portal_deployment_history', $history, false);
}

function gd_client_portal_deployment_lifecycle_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gdcp_deployment_action'])) {
        check_admin_referer('gdcp_deployment_lifecycle');

        $action = sanitize_key(wp_unslash($_POST['gdcp_deployment_action']));

        if ($action === 'record_precheck') {
            $pre = gd_client_portal_deployment_lifecycle_precheck();
            $ok = gd_client_portal_deployment_lifecycle_all_passed($pre);
            gd_client_portal_deployment_lifecycle_record(
                $ok ? 'pre_deployment_pass' : 'pre_deployment_blocked',
                $ok ? 'All pre-deployment gates passed.' : 'One or more pre-deployment gates failed.'
            );
        }

        if ($action === 'record_postcheck') {
            $post = gd_client_portal_deployment_lifecycle_postcheck();
            $ok = gd_client_portal_deployment_lifecycle_all_passed($post);
            gd_client_portal_deployment_lifecycle_record(
                $ok ? 'post_deployment_pass' : 'post_deployment_blocked',
                $ok ? 'Post-deployment health checks passed.' : 'One or more post-deployment checks failed.'
            );
        }

        if ($action === 'record_rollback') {
            gd_client_portal_deployment_lifecycle_record(
                'rollback_recorded',
                'Administrator recorded a rollback event. Run runtime and regression verification.'
            );
        }

        echo '<div class="notice notice-success is-dismissible"><p>Deployment evidence recorded.</p></div>';
    }

    $pre = gd_client_portal_deployment_lifecycle_precheck();
    $post = gd_client_portal_deployment_lifecycle_postcheck();
    $history = gd_client_portal_deployment_lifecycle_history();

    echo '<div class="wrap"><h1>GD Client Portal — Deployment Lifecycle</h1>';
    echo '<p>Use this page to record deployment evidence. Actions are administrative records only and do not automatically install, upgrade, rollback, or delete plugin data.</p>';

    echo '<h2>Pre-Deployment Gate</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Requirement</th><th>Status</th><th>Evidence</th></tr></thead><tbody>';
    foreach ($pre['checks'] as $label => $check) {
        $status = !empty($check['ok']) ? '<strong style="color:#16803a">PASS</strong>' : '<strong style="color:#b32d2e">BLOCKED</strong>';
        echo '<tr><td>' . esc_html($label) . '</td><td>' . $status . '</td><td>' . esc_html($check['detail']) . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<form method="post" style="margin:14px 0">';
    wp_nonce_field('gdcp_deployment_lifecycle');
    echo '<input type="hidden" name="gdcp_deployment_action" value="record_precheck">';
    submit_button('Record Pre-Deployment Result', 'secondary', 'submit', false);
    echo '</form>';

    echo '<h2>Post-Deployment Health Gate</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Requirement</th><th>Status</th><th>Evidence</th></tr></thead><tbody>';
    foreach ($post['checks'] as $label => $check) {
        $status = !empty($check['ok']) ? '<strong style="color:#16803a">PASS</strong>' : '<strong style="color:#b32d2e">BLOCKED</strong>';
        echo '<tr><td>' . esc_html($label) . '</td><td>' . $status . '</td><td>' . esc_html($check['detail']) . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<form method="post" style="margin:14px 0">';
    wp_nonce_field('gdcp_deployment_lifecycle');
    echo '<input type="hidden" name="gdcp_deployment_action" value="record_postcheck">';
    submit_button('Record Post-Deployment Result', 'secondary', 'submit', false);
    echo '</form>';

    echo '<h2>Rollback Event</h2>';
    echo '<form method="post">';
    wp_nonce_field('gdcp_deployment_lifecycle');
    echo '<input type="hidden" name="gdcp_deployment_action" value="record_rollback">';
    submit_button('Record Rollback Event', 'secondary', 'submit', false);
    echo '</form>';

    echo '<h2>Deployment History</h2>';
    if (empty($history)) {
        echo '<p>No deployment events have been recorded yet.</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Event</th><th>Version</th><th>User</th><th>Detail</th></tr></thead><tbody>';
        foreach ($history as $item) {
            echo '<tr><td>' . esc_html($item['time']) . '</td><td>' . esc_html($item['event']) . '</td><td>' . esc_html($item['plugin_version']) . '</td><td>' . esc_html((string) $item['user_id']) . '</td><td>' . esc_html($item['detail']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'gd-client-portal',
        'Deployment Lifecycle',
        'Deployment Lifecycle',
        'manage_options',
        'gd-client-portal-deployment-lifecycle',
        'gd_client_portal_deployment_lifecycle_page'
    );
}, 34);
