<?php
/**
 * Invoices module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_module_invoices_activate')) {
	function gd_client_portal_module_invoices_activate()
	{
		update_option('gd_client_portal_module_invoices_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_invoices_deactivate')) {
	function gd_client_portal_module_invoices_deactivate()
	{
		update_option('gd_client_portal_module_invoices_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_invoices_module')) {
	function gd_client_portal_register_invoices_module()
	{
		add_shortcode('gd_client_portal_invoice', 'gd_client_portal_render_invoice');
		add_shortcode('gd_woo_invoices', 'gd_client_portal_render_invoice');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_invoices_assets');
	}
}

if (!function_exists('gd_client_portal_register_invoices_assets')) {
	function gd_client_portal_register_invoices_assets()
	{
		wp_register_style('gd-client-portal-invoices', GD_CLIENT_PORTAL_URL . 'modules/invoices/assets/invoices.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
	}
}

if (!function_exists('gd_client_portal_render_invoice')) {
	function gd_client_portal_render_invoice($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		if (!gd_client_portal_verify_tenant_access()) {
			return gd_client_portal_render_access_gate();
		}

		wp_enqueue_style('gd-client-portal-invoices');
		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/invoices/templates/detail.php';
		if (file_exists($tpl)) {
			ob_start();
			include $tpl;
			return ob_get_clean();
		}
		return '<p>' . esc_html__('Invoice not available.', 'gd-client-portal') . '</p>';
	}
}

add_action('init', 'gd_client_portal_register_invoices_module');

// Register invoices module dashboard view
if (!function_exists('gd_client_portal_register_invoices_dashboard_view')) {
	function gd_client_portal_register_invoices_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('invoices', 'gd_client_portal_render_invoice');
		}
	}
	add_action('init', 'gd_client_portal_register_invoices_dashboard_view');
}
