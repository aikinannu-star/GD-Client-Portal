<?php
/**
 * Files module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}
if (!function_exists('gd_client_portal_module_files_activate')) {
    function gd_client_portal_module_files_activate()
    {
        update_option('gd_client_portal_module_files_installed', current_time('mysql'));
    }
}

if (!function_exists('gd_client_portal_module_files_deactivate')) {
	function gd_client_portal_module_files_deactivate()
	{
		update_option('gd_client_portal_module_files_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_files_module')) {
	function gd_client_portal_register_files_module()
	{
		add_shortcode('gd_client_portal_files', 'gd_client_portal_render_files');
		add_shortcode('gd_customer_files', 'gd_client_portal_render_files');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_files_assets');
	}
}

if (!function_exists('gd_client_portal_register_files_assets')) {
	function gd_client_portal_register_files_assets()
	{
		wp_register_style('gd-client-portal-files', GD_CLIENT_PORTAL_URL . 'modules/files/assets/files.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
		wp_register_script('gd-client-portal-files', GD_CLIENT_PORTAL_URL . 'modules/files/assets/files.js', array('gd-client-portal', 'jquery'), GD_CLIENT_PORTAL_VERSION, true);
	}
}

if (!function_exists('gd_client_portal_render_files')) {
	function gd_client_portal_render_files($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		wp_enqueue_style('gd-client-portal-files');
		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/files/templates/detail.php';
		if (file_exists($tpl)) {
			ob_start();
			include $tpl;
			return ob_get_clean();
		}
		return '<p>' . esc_html__('Files unavailable.', 'gd-client-portal') . '</p>';
	}
}

add_action('init', 'gd_client_portal_register_files_module');

// Register files module dashboard view
if (!function_exists('gd_client_portal_register_files_dashboard_view')) {
	function gd_client_portal_register_files_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('files', 'gd_client_portal_render_files');
		}
	}
	add_action('init', 'gd_client_portal_register_files_dashboard_view');
}
