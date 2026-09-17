<?php
/**
 * Notifications module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_module_notifications_activate')) {
	function gd_client_portal_module_notifications_activate()
	{
		update_option('gd_client_portal_module_notifications_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_notifications_deactivate')) {
	function gd_client_portal_module_notifications_deactivate()
	{
		update_option('gd_client_portal_module_notifications_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_notifications_module')) {
	function gd_client_portal_register_notifications_module()
	{
		add_shortcode('gd_client_portal_notification', 'gd_client_portal_render_notification');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_notifications_assets');
	}
}

if (!function_exists('gd_client_portal_register_notifications_assets')) {
	function gd_client_portal_register_notifications_assets()
	{
		wp_register_style('gd-client-portal-notifications', GD_CLIENT_PORTAL_URL . 'modules/notifications/assets/notifications.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
	}
}

if (!function_exists('gd_client_portal_render_notification')) {
	function gd_client_portal_render_notification($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		if (!gd_client_portal_verify_tenant_access()) {
			return gd_client_portal_render_access_gate();
		}
		wp_enqueue_style('gd-client-portal-notifications');
		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/notifications/templates/detail.php';
		if (file_exists($tpl)) { ob_start(); include $tpl; return ob_get_clean(); }
		return '<p>' . esc_html__('Notification unavailable.', 'gd-client-portal') . '</p>';
	}
}

add_action('init', 'gd_client_portal_register_notifications_module');

// Register notifications module dashboard view
if (!function_exists('gd_client_portal_register_notifications_dashboard_view')) {
	function gd_client_portal_register_notifications_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('notifications', 'gd_client_portal_render_notification');
		}
	}
	add_action('init', 'gd_client_portal_register_notifications_dashboard_view');
}
