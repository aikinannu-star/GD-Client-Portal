<?php
/**
 * Asset registration for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_enqueue_assets')) {
    function gd_client_portal_enqueue_assets()
    {
        wp_enqueue_style('gd-client-portal', GD_CLIENT_PORTAL_URL . 'assets/css/gd-client-portal.css', array(), GD_CLIENT_PORTAL_VERSION);
        wp_enqueue_script('jquery'); // Ensure jQuery is loaded
        wp_enqueue_script('gd-client-portal', GD_CLIENT_PORTAL_URL . 'assets/js/gd-client-portal.js', array('jquery'), GD_CLIENT_PORTAL_VERSION, true);

        // Tenant-specific visual theme variables. Unassigned/platform users keep the global defaults.
        if (function_exists('gd_client_portal_get_current_tenant_branding')) {
            $branding = gd_client_portal_get_current_tenant_branding();
            $primary = sanitize_hex_color($branding['primary_color'] ?? '') ?: '#2563eb';
            $accent = sanitize_hex_color($branding['accent_color'] ?? '') ?: '#0f172a';
            $inline = ':root{--gdcp-primary:' . esc_html($primary) . ';--gdcp-accent:' . esc_html($accent) . ';}';
            wp_add_inline_style('gd-client-portal', $inline);
        }

        // Localize AJAX and nonces for the auth script
        $ajax_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'login_nonce' => wp_create_nonce('gd_client_portal_login'),
            'register_nonce' => wp_create_nonce('gd_client_portal_register'),
        );
        wp_localize_script('gd-client-portal', 'GDClientPortal', $ajax_data);
    }
}

add_action('wp_enqueue_scripts', 'gd_client_portal_enqueue_assets');


if (!function_exists('gd_client_portal_enqueue_workspace_assets')) {
    function gd_client_portal_enqueue_workspace_assets()
    {
        if (is_singular()) {
            wp_enqueue_style('gd-client-portal-workspace', GD_CLIENT_PORTAL_URL . 'assets/project-workspace.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
            wp_enqueue_script('gd-client-portal-workspace', GD_CLIENT_PORTAL_URL . 'assets/project-workspace.js', array('jquery'), GD_CLIENT_PORTAL_VERSION, true);
            wp_localize_script('gd-client-portal-workspace', 'GDProjectWorkspace', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'stage_nonce' => wp_create_nonce('gd_client_portal_project_modal'),
                'messages' => array(
                    'saving' => __('Saving…', 'gd-client-portal'),
                    'saved' => __('Saved.', 'gd-client-portal'),
                    'error' => __('Something went wrong. Please try again.', 'gd-client-portal'),
                ),
            ));
        }
    }
    add_action('wp_enqueue_scripts', 'gd_client_portal_enqueue_workspace_assets', 30);
}
add_action('wp_enqueue_scripts',function(){if(function_exists('is_page')&&is_page()){wp_enqueue_script('gd-client-portal-requests',GD_CLIENT_PORTAL_URL.'assets/requests.js',array(),GD_CLIENT_PORTAL_VERSION,true);wp_localize_script('gd-client-portal-requests','gdClientPortalRequest',array('ajaxurl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('gd_client_portal_request')));}} ,40);

add_action('wp_enqueue_scripts', function(){
    if(!is_user_logged_in()) return;
    wp_enqueue_script('gd-client-portal-payments', GD_CLIENT_PORTAL_URL.'assets/payments.js', array('jquery'), GD_CLIENT_PORTAL_VERSION, true);
    wp_localize_script('gd-client-portal-payments','GDCPPayments',array('ajax_url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('gd_client_portal_payment'),'admin_nonce'=>wp_create_nonce('gd_client_portal_payment_admin'),'billing_nonce'=>wp_create_nonce('gd_client_portal_billing_admin')));
}, 45);

add_action('wp_enqueue_scripts',function(){
    wp_register_style('gd-client-portal-automation',GD_CLIENT_PORTAL_URL.'assets/automation.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION);
    if(is_user_logged_in()) wp_enqueue_style('gd-client-portal-automation');
});
add_action('admin_enqueue_scripts',function($hook){wp_enqueue_style('gd-client-portal-automation',GD_CLIENT_PORTAL_URL.'assets/automation.css',array(),GD_CLIENT_PORTAL_VERSION);if(isset($_GET['page'])&&sanitize_key($_GET['page'])==='gd-client-portal-automation')wp_enqueue_script('gd-client-portal-automation',GD_CLIENT_PORTAL_URL.'assets/automation.js',array(),GD_CLIENT_PORTAL_VERSION,true);});
