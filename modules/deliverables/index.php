<?php
/**
 * Deliverables module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_module_deliverables_activate')) {
	function gd_client_portal_module_deliverables_activate()
	{
		update_option('gd_client_portal_module_deliverables_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_deliverables_deactivate')) {
	function gd_client_portal_module_deliverables_deactivate()
	{
		update_option('gd_client_portal_module_deliverables_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_deliverables_module')) {
	function gd_client_portal_register_deliverables_module()
	{
		add_shortcode('gd_client_portal_deliverable', 'gd_client_portal_render_deliverable');
		add_shortcode('gd_project_deliverables', 'gd_client_portal_render_deliverable');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_deliverables_assets');
	}
}

if (!function_exists('gd_client_portal_register_deliverables_assets')) {
	function gd_client_portal_register_deliverables_assets()
	{
		wp_register_style('gd-client-portal-deliverables', GD_CLIENT_PORTAL_URL . 'modules/deliverables/assets/deliverables.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
		wp_register_script('gd-client-portal-deliverables', GD_CLIENT_PORTAL_URL . 'modules/deliverables/assets/deliverables.js', array('gd-client-portal', 'jquery'), GD_CLIENT_PORTAL_VERSION, true);
	}
}

if (!function_exists('gd_client_portal_render_deliverable')) {
	function gd_client_portal_render_deliverable($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		if (!gd_client_portal_verify_tenant_access()) {
			return gd_client_portal_render_access_gate();
		}

		wp_enqueue_style('gd-client-portal-deliverables');

		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/deliverables/templates/detail.php';
		if (file_exists($tpl)) {
			ob_start();
			include $tpl;
			return ob_get_clean();
		}

		return '<p>' . esc_html__('Deliverable details unavailable.', 'gd-client-portal') . '</p>';
	}
}

add_action('init', 'gd_client_portal_register_deliverables_module');

// Register deliverables dashboard view
if (!function_exists('gd_client_portal_register_deliverables_dashboard_view')) {
	function gd_client_portal_register_deliverables_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('deliverables', 'gd_client_portal_render_deliverable');
		}
	}
	add_action('init', 'gd_client_portal_register_deliverables_dashboard_view');
}
