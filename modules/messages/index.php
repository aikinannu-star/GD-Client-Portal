<?php
/**
 * Messages module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_module_messages_activate')) {
	function gd_client_portal_module_messages_activate()
	{
		update_option('gd_client_portal_module_messages_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_messages_deactivate')) {
	function gd_client_portal_module_messages_deactivate()
	{
		update_option('gd_client_portal_module_messages_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_messages_module')) {
	function gd_client_portal_register_messages_module()
	{
		add_shortcode('gd_client_portal_message', 'gd_client_portal_render_message');
		add_shortcode('gd_private_messages', 'gd_client_portal_render_message');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_messages_assets');
	}
}

if (!function_exists('gd_client_portal_register_messages_assets')) {
	function gd_client_portal_register_messages_assets()
	{
		wp_register_style('gd-client-portal-messages', GD_CLIENT_PORTAL_URL . 'modules/messages/assets/messages.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
	}
}

if (!function_exists('gd_client_portal_render_message')) {
	function gd_client_portal_render_message($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		if (!gd_client_portal_verify_tenant_access()) {
			return gd_client_portal_render_access_gate();
		}
		wp_enqueue_style('gd-client-portal-messages');
		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/messages/templates/detail.php';
		if (file_exists($tpl)) { ob_start(); include $tpl; return ob_get_clean(); }
		return '<p>' . esc_html__('Message view unavailable.', 'gd-client-portal') . '</p>';
	}
}

add_action('init', 'gd_client_portal_register_messages_module');

// Register messages module dashboard view
if (!function_exists('gd_client_portal_register_messages_dashboard_view')) {
	function gd_client_portal_register_messages_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('messages', 'gd_client_portal_render_message');
		}
	}
	add_action('init', 'gd_client_portal_register_messages_dashboard_view');
}
