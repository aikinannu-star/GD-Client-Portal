<?php
/**
 * Example module scaffold for GD Client Portal
 * Copy this folder to create a new module at modules/<your-module>/
 */

// Optional module.json example
// {
//   "title": "Example Module",
//   "description": "A short description shown in the admin module manager.",
//   "version": "1.0.0"
// }

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_register_example_module')) {
    function gd_client_portal_register_example_module()
    {
        // Register shortcode
        add_shortcode('gd_client_portal_example', 'gd_client_portal_render_example_module');
    }
}

if (!function_exists('gd_client_portal_render_example_module')) {
    function gd_client_portal_render_example_module($atts = array())
    {
        // Protect content - only logged-in users
        if (!gd_client_portal_verify_request()) {
            return gd_client_portal_render_access_gate();
        }

        // Load view from templates/modules/example/list.php if present
        $html = '';
        $tpl = GD_CLIENT_PORTAL_PATH . 'modules/_template/templates/list.php';
        if (file_exists($tpl)) {
            ob_start();
            include $tpl;
            $html = ob_get_clean();
        } else {
            $html = '<p>' . esc_html__('Example module loaded.', 'gd-client-portal') . '</p>';
        }

        return $html;
    }
}

add_action('init', 'gd_client_portal_register_example_module');

// Sample activation/deactivation functions
// When creating a new module from this template, rename these functions to match your module slug:
// e.g. for module folder `projects` implement `gd_client_portal_module_projects_activate()` and `gd_client_portal_module_projects_deactivate()`
if (!function_exists('gd_client_portal_module__template_activate')) {
    function gd_client_portal_module__template_activate()
    {
        // Example: create DB tables, initialize options, schedule tasks, etc.
        // update_option('gd_client_portal__template_installed', current_time('mysql'));
    }
}

if (!function_exists('gd_client_portal_module__template_deactivate')) {
    function gd_client_portal_module__template_deactivate()
    {
        // Example: remove scheduled tasks, cleanup transient data, but avoid deleting user data.
        // delete_option('gd_client_portal__template_installed');
    }
}
