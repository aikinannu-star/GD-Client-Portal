<?php
/**
 * Workflow module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_module_workflow_activate')) {
	function gd_client_portal_module_workflow_activate()
	{
		update_option('gd_client_portal_module_workflow_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_workflow_deactivate')) {
	function gd_client_portal_module_workflow_deactivate()
	{
		update_option('gd_client_portal_module_workflow_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_workflow_module')) {
	function gd_client_portal_register_workflow_module()
	{
		add_shortcode('gd_client_portal_workflow', 'gd_client_portal_render_workflow');
		add_shortcode('gd_workflow_ui', 'gd_client_portal_render_workflow');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_workflow_assets');
	}
}

if (!function_exists('gd_client_portal_register_workflow_assets')) {
	function gd_client_portal_register_workflow_assets()
	{
		wp_register_style('gd-client-portal-workflow', GD_CLIENT_PORTAL_URL . 'modules/workflow/assets/workflow.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
	}
}

if (!function_exists('gd_client_portal_render_workflow')) {
	function gd_client_portal_render_workflow($atts = array())
	{
		if (!gd_client_portal_verify_request()) { return gd_client_portal_render_access_gate(); }
		wp_enqueue_style('gd-client-portal-workflow');
		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/workflow/templates/detail.php';
		if (file_exists($tpl)) { ob_start(); include $tpl; return ob_get_clean(); }
		return '<p>' . esc_html__('Workflow item unavailable.', 'gd-client-portal') . '</p>';
	}
}

add_action('init', 'gd_client_portal_register_workflow_module');

// Register workflow module dashboard view
if (!function_exists('gd_client_portal_register_workflow_dashboard_view')) {
	function gd_client_portal_register_workflow_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('workflow', 'gd_client_portal_render_workflow');
		}
	}
	add_action('init', 'gd_client_portal_register_workflow_dashboard_view');
}
