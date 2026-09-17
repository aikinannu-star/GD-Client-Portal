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
