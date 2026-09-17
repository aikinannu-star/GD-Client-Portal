<?php
/**
 * Integration Testing Readiness.
 *
 * Provides an administrator-visible view of integration contracts. It does
 * not create users, modify tenant data, or execute payment transactions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gd_client_portal_integration_test_readiness_report() {
    $checks = array(
        'Runtime integration contracts' => array(
            'ok' => file_exists(GD_CLIENT_PORTAL_PATH . 'tests/class-runtime-integration-contracts.php'),
            'detail' => 'Non-destructive runtime contract suite is packaged.',
        ),
        'WordPress test framework compatibility' => array(
            'ok' => defined('WP_TESTS_DIR') || defined('WP_ENVIRONMENT_TYPE'),
            'detail' => defined('WP_TESTS_DIR')
                ? 'WordPress test framework constants detected.'
                : 'Use a dedicated WordPress test environment for full PHPUnit execution.',
        ),
        'Automated regression layer' => array(
            'ok' => function_exists('gd_client_portal_automated_regression_checks')
                || function_exists('gd_client_portal_run_automated_regression'),
            'detail' => 'Regression verification runtime is available.',
        ),
        'Controlled scenario evidence' => array(
            'ok' => function_exists('gd_client_portal_e2e_scenario_report'),
            'detail' => 'Controlled scenario execution evidence is available.',
        ),
        'Production observability' => array(
            'ok' => function_exists('gd_client_portal_observability_health'),
            'detail' => 'Operational health monitoring is available.',
        ),
    );

    $passed = 0;
    foreach ($checks as $check) {
        if (!empty($check['ok'])) {
            $passed++;
        }
    }

    return array(
        'generated_at' => current_time('mysql'),
        'checks' => $checks,
        'passed' => $passed,
        'total' => count($checks),
        'all_passed' => $passed === count($checks),
    );
}

function gd_client_portal_integration_test_readiness_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    $report = gd_client_portal_integration_test_readiness_report();

    echo '<div class="wrap"><h1>GD Client Portal — Integration Testing</h1>';
    echo '<p>This phase prepares the plugin for repeatable testing in a dedicated WordPress test environment. Production data is not modified.</p>';

    echo '<table class="widefat striped"><thead><tr><th>Integration requirement</th><th>Status</th><th>Evidence</th></tr></thead><tbody>';
    foreach ($report['checks'] as $label => $check) {
        $status = !empty($check['ok'])
            ? '<strong style="color:#16803a">PASS</strong>'
            : '<strong style="color:#b32d2e">SETUP REQUIRED</strong>';
        echo '<tr><td>' . esc_html($label) . '</td><td>' . $status . '</td><td>' . esc_html($check['detail']) . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h2 style="margin-top:28px">Integration Coverage Roadmap</h2>';
    echo '<ol>';
    echo '<li>Tenant A and Tenant B isolation using dedicated test accounts.</li>';
    echo '<li>Project ownership and unauthorized mutation denial.</li>';
    echo '<li>Protected upload and download authorization.</li>';
    echo '<li>AJAX authorization and nonce failure cases.</li>';
    echo '<li>Database persistence and migration verification.</li>';
    echo '<li>Scheduler/automation execution contracts.</li>';
    echo '</ol>';

    echo '<p><strong>Readiness:</strong> ' . esc_html((string)$report['passed']) . ' / ' . esc_html((string)$report['total']) . ' integration prerequisites passing.</p>';
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'gd-client-portal',
        'Integration Testing',
        'Integration Testing',
        'manage_options',
        'gd-client-portal-integration-testing',
        'gd_client_portal_integration_test_readiness_page'
    );
}, 36);
