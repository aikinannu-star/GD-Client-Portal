<?php
/**
 * Meetings module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_module_meetings_activate')) {
	function gd_client_portal_module_meetings_activate()
	{
		update_option('gd_client_portal_module_meetings_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_meetings_deactivate')) {
	function gd_client_portal_module_meetings_deactivate()
	{
		update_option('gd_client_portal_module_meetings_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_meetings_module')) {
	function gd_client_portal_register_meetings_module()
	{
		add_shortcode('gd_client_portal_meeting', 'gd_client_portal_render_meeting');
		add_shortcode('gd_booking_calendar', 'gd_client_portal_render_meeting');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_meetings_assets');
	}
}

if (!function_exists('gd_client_portal_register_meetings_assets')) {
	function gd_client_portal_register_meetings_assets()
	{
		wp_register_style('gd-client-portal-meetings', GD_CLIENT_PORTAL_URL . 'modules/meetings/assets/meetings.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
	}
}

if (!function_exists('gd_client_portal_render_meeting')) {
	function gd_client_portal_render_meeting($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		if (!gd_client_portal_verify_tenant_access()) {
			return gd_client_portal_render_access_gate();
		}

		wp_enqueue_style('gd-client-portal-meetings');
		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/meetings/templates/detail.php';
		if (file_exists($tpl)) { ob_start(); include $tpl; return ob_get_clean(); }
		return '<p>' . esc_html__('Meeting details unavailable.', 'gd-client-portal') . '</p>';
	}
}

add_action('init', 'gd_client_portal_register_meetings_module');

// Register meetings module dashboard view
if (!function_exists('gd_client_portal_register_meetings_dashboard_view')) {
	function gd_client_portal_register_meetings_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('meetings', 'gd_client_portal_render_meeting');
		}
	}
	add_action('init', 'gd_client_portal_register_meetings_dashboard_view');
}
