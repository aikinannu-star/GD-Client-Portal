<?php
/**
 * Product Getting Started hub.
 */

if (!defined('ABSPATH')) {
    exit;
}

function gdcp_render_getting_started_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    gdcp_admin_page_header(
        __('Getting Started', 'gd-client-portal'),
        __('Follow the primary client-service workflow to configure and begin using GD Client Portal.', 'gd-client-portal')
    );

    gdcp_admin_section(__('Start with the core workflow', 'gd-client-portal'));

    $steps = array(
        __('1. Configure your service and client settings.', 'gd-client-portal'),
        __('2. Capture a new client request through Service Intake.', 'gd-client-portal'),
        __('3. Complete client onboarding and establish the project workspace.', 'gd-client-portal'),
        __('4. Manage collaboration, approvals, and deliverables.', 'gd-client-portal'),
        __('5. Complete billing, support, and project closure.', 'gd-client-portal'),
    );

    echo '<ol class="gdcp-getting-started-steps">';
    foreach ($steps as $step) {
        echo '<li>' . esc_html($step) . '</li>';
    }
    echo '</ol>';

    gdcp_admin_section_end();

    gdcp_admin_section(__('Recommended navigation', 'gd-client-portal'));
    echo '<p><strong>';
    echo esc_html__('Intake → Onboarding → Projects → Collaboration → Approvals → Deliverables → Billing → Support → Completion', 'gd-client-portal');
    echo '</strong></p>';
    gdcp_admin_section_end();

    gdcp_admin_page_footer();
}

/**
 * Register the page using the same capability as the GD Client Portal
 * workspace. The previous implementation used manage_options, which could
 * make the item visible/route inconsistently on sites with custom admin
 * capability mapping.
 */
function gdcp_register_getting_started_menu() {
    add_submenu_page(
        'gd-client-portal',
        __('Getting Started', 'gd-client-portal'),
        __('Getting Started', 'gd-client-portal'),
        'gd_client_portal_access_admin',
        'gd-client-portal-getting-started',
        'gdcp_render_getting_started_page'
    );
}
add_action('admin_menu', 'gdcp_register_getting_started_menu', 11);

/**
 * Safety-net registration for environments that reorder/strip plugin menus.
 * WordPress normally handles the 11-priority registration above; this fallback
 * only runs if the submenu is genuinely missing.
 */
function gdcp_ensure_getting_started_menu() {
    global $submenu;
    $parent = 'gd-client-portal';
    $slug   = 'gd-client-portal-getting-started';
    if (!empty($submenu[$parent])) {
        foreach ($submenu[$parent] as $item) {
            if (isset($item[2]) && $item[2] === $slug) {
                return;
            }
        }
    }
    if (current_user_can('gd_client_portal_access_admin')) {
        add_submenu_page(
            $parent,
            __('Getting Started', 'gd-client-portal'),
            __('Getting Started', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            $slug,
            'gdcp_render_getting_started_page'
        );
    }
}
add_action('admin_menu', 'gdcp_ensure_getting_started_menu', 999);

/**
 * Some installations/themes have retained an old front-end /getting-started/
 * link. Redirect authenticated administrators from that legacy public route
 * to the real WordPress admin screen instead of showing the site's 404 page.
 */
function gdcp_redirect_legacy_getting_started_route() {
    if (!is_user_logged_in() || !current_user_can('gd_client_portal_access_admin') || is_admin()) {
        return;
    }

    $path = wp_parse_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '', PHP_URL_PATH);
    $path = trim((string) $path, '/');
    $base = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($base !== '' && strpos($path, $base . '/') === 0) {
        $path = substr($path, strlen($base) + 1);
    }

    if ($path === 'getting-started' || $path === 'get-started') {
        wp_safe_redirect(admin_url('admin.php?page=gd-client-portal-getting-started'));
        exit;
    }
}
add_action('template_redirect', 'gdcp_redirect_legacy_getting_started_route', 1);

/** Add a canonical admin link on the main portal dashboard as a cache-resistant fallback. */
function gdcp_getting_started_dashboard_notice() {
    if (!is_admin() || !current_user_can('gd_client_portal_access_admin')) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page !== 'gd-client-portal') {
        return;
    }
    echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__('Getting Started:', 'gd-client-portal') . '</strong> <a href="' . esc_url(admin_url('admin.php?page=gd-client-portal-getting-started')) . '">' . esc_html__('Open the setup guide', 'gd-client-portal') . '</a>.</p></div>';
}
add_action('admin_notices', 'gdcp_getting_started_dashboard_notice', 20);
