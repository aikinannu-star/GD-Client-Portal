<?php
/**
 * Product Health & Architecture Summary.
 *
 * A consolidated administrator view for runtime, feature, and maintenance
 * signals. This intentionally summarizes existing systems instead of creating
 * another independent readiness framework.
 */

if (!defined('ABSPATH')) {
    exit;
}

function gd_client_portal_product_health_report() {
    $bootstrap_failures = get_option('gd_client_portal_bootstrap_failures', array());
    $bootstrap_failures = is_array($bootstrap_failures) ? $bootstrap_failures : array();

    $modules = array(
        'Client workflow' => array(
            'intake' => function_exists('gd_client_portal_render_service_intake'),
            'onboarding' => function_exists('gd_client_portal_render_onboarding'),
            'projects' => function_exists('gd_client_portal_render_service_projects'),
            'approvals' => function_exists('gd_client_portal_render_approvals'),
            'delivery' => function_exists('gd_client_portal_render_deliverables_workspace'),
        ),
        'Client services' => array(
            'documents' => function_exists('gd_client_portal_render_documents'),
            'billing' => function_exists('gd_client_portal_render_billing_center'),
            'support' => function_exists('gd_client_portal_render_support'),
            'communications' => function_exists('gd_client_portal_render_communication_hub'),
            'collaboration' => function_exists('gd_client_portal_render_collaboration'),
        ),
        'Operations' => array(
            'automation' => function_exists('gd_client_portal_render_automation_dashboard'),
            'reporting' => function_exists('gd_client_portal_render_reporting'),
            'audit' => function_exists('gd_client_portal_render_audit_center'),
            'observability' => function_exists('gd_client_portal_observability_health'),
        ),
    );

    $available = 0;
    $total = 0;
    foreach ($modules as $group => $items) {
        foreach ($items as $ok) {
            $total++;
            if ($ok) {
                $available++;
            }
        }
    }

    $runtime_ok = function_exists('gd_client_portal_platform_runtime_status_ok')
        ? (bool) gd_client_portal_platform_runtime_status_ok()
        : empty($bootstrap_failures);

    return array(
        'generated_at' => current_time('mysql'),
        'runtime_ok' => $runtime_ok,
        'bootstrap_failures' => count($bootstrap_failures),
        'features_available' => $available,
        'features_total' => $total,
        'modules' => $modules,
    );
}

function gd_client_portal_product_health_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    $report = gd_client_portal_product_health_report();
    $status = $report['runtime_ok'] ? 'HEALTHY' : 'ATTENTION REQUIRED';
    $color = $report['runtime_ok'] ? '#16803a' : '#b32d2e';

    echo '<div class="wrap">';
    echo '<h1>GD Client Portal — Product Health</h1>';
    echo '<p>This page summarizes the actual product surface and runtime signals to help administrators focus on client-service functionality.</p>';
    echo '<h2 style="color:' . esc_attr($color) . '">' . esc_html($status) . '</h2>';

    echo '<table class="widefat striped" style="max-width:900px"><tbody>';
    echo '<tr><th>Runtime</th><td>' . ($report['runtime_ok'] ? 'Healthy' : 'Requires attention') . '</td></tr>';
    echo '<tr><th>Available feature contracts</th><td>' . esc_html($report['features_available'] . ' / ' . $report['features_total']) . '</td></tr>';
    echo '<tr><th>Recorded bootstrap failures</th><td>' . esc_html((string) $report['bootstrap_failures']) . '</td></tr>';
    echo '</tbody></table>';

    foreach ($report['modules'] as $group => $items) {
        echo '<h2>' . esc_html($group) . '</h2>';
        echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Feature</th><th>Status</th></tr></thead><tbody>';
        foreach ($items as $name => $ok) {
            echo '<tr><td>' . esc_html(ucwords(str_replace('_', ' ', $name))) . '</td><td>';
            echo $ok ? '<strong style="color:#16803a">AVAILABLE</strong>' : '<strong style="color:#b32d2e">UNAVAILABLE</strong>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<h2>Recommended Client Workflow</h2>';
    echo '<p><strong>Intake → Onboarding → Projects → Collaboration → Approvals → Deliverables → Billing → Support → Completion</strong></p>';
    echo '<p>Advanced automation, intelligence, governance, release, and diagnostic tools should support this workflow rather than replace it.</p>';
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'gd-client-portal',
        'Product Health',
        'Product Health',
        'manage_options',
        'gd-client-portal-product-health',
        'gd_client_portal_product_health_page'
    );
}, 18);
