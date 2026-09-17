<?php
/**
 * Cohesive administration navigation for GD Client Portal.
 */
if (!defined('ABSPATH')) exit;

function gdcp_admin_navigation_groups() {
    return array(
        'Core Product' => array('Clients','Projects','Workflow','Documents','Billing','Support'),
        'Advanced Operations' => array('Automation','Analytics','Reporting','Intelligence','Operations'),
        'Administration' => array('Product Health','Platform Health','Security','Governance','Deployment','Diagnostics'),
    );
}

function gdcp_is_plugin_admin_screen() {
    if (!is_admin()) return false;
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return $page === 'gd-client-portal' || strpos($page, 'gd-client-portal') === 0;
}

function gdcp_admin_body_class($classes) {
    if (gdcp_is_plugin_admin_screen()) {
        $classes .= ' gdcp-admin-screen';
    }
    return $classes;
}
add_filter('admin_body_class','gdcp_admin_body_class');

function gdcp_render_workspace_navigation() {
    if (!current_user_can('gd_client_portal_access_admin') || !gdcp_is_plugin_admin_screen()) return;
    echo '<div class="gdcp-workspace-notice"><div><strong>GD Client Portal Workspace</strong><p>Intake → Onboarding → Projects → Collaboration → Approvals → Deliverables → Billing → Support → Completion</p></div></div>';
}
add_action('admin_notices','gdcp_render_workspace_navigation');
