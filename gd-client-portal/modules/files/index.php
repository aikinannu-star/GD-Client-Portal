<?php
/**
 * Files module compatibility bridge.
 * GD Client Portal v3.5 routes legacy file shortcodes to the Document Center.
 */
if (!defined('ABSPATH')) exit;
if (!function_exists('gd_client_portal_register_files_module')) {
    function gd_client_portal_register_files_module(){
        if (function_exists('gd_client_portal_render_documents')) {
            add_shortcode('gd_client_portal_files','gd_client_portal_render_documents');
            add_shortcode('gd_customer_files','gd_client_portal_render_documents');
            add_shortcode('gd_client_files','gd_client_portal_render_documents');
        }
    }
}
add_action('init','gd_client_portal_register_files_module');
if (!function_exists('gd_client_portal_module_files_activate')) { function gd_client_portal_module_files_activate(){update_option('gd_client_portal_module_files_installed',current_time('mysql'));} }
if (!function_exists('gd_client_portal_module_files_deactivate')) { function gd_client_portal_module_files_deactivate(){update_option('gd_client_portal_module_files_deactivated',current_time('mysql'));} }
