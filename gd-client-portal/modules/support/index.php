<?php
/**
 * Support module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_module_support_activate')) {
	function gd_client_portal_module_support_activate()
	{
		update_option('gd_client_portal_module_support_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_support_deactivate')) {
	function gd_client_portal_module_support_deactivate()
	{
		update_option('gd_client_portal_module_support_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_support_module')) {
	function gd_client_portal_register_support_module()
	{
		add_shortcode('gd_client_portal_support', 'gd_client_portal_render_support');
		add_shortcode('gd_support_tickets', 'gd_client_portal_render_support');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_support_assets');
	}
}

if (!function_exists('gd_client_portal_register_support_assets')) {
	function gd_client_portal_register_support_assets()
	{
		wp_register_style('gd-client-portal-support', GD_CLIENT_PORTAL_URL . 'modules/support/assets/support.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
	}
}

if (!function_exists('gd_client_portal_render_support')) {
	function gd_client_portal_render_support($atts = array())
	{
		if (!gd_client_portal_verify_request()) { return gd_client_portal_render_access_gate(); }

		if (!gd_client_portal_verify_tenant_access()) { return gd_client_portal_render_access_gate(); }
		wp_enqueue_style('gd-client-portal-support');
		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/support/templates/detail.php';
		if (file_exists($tpl)) { ob_start(); include $tpl; return ob_get_clean(); }
		return '<p>' . esc_html__('Support ticket unavailable.', 'gd-client-portal') . '</p>';
	}
}

add_action('init', 'gd_client_portal_register_support_module');

// Register support module dashboard view
if (!function_exists('gd_client_portal_register_support_dashboard_view')) {
	function gd_client_portal_register_support_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('support', 'gd_client_portal_render_support');
		}
	}
	add_action('init', 'gd_client_portal_register_support_dashboard_view');
}
