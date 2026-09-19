<?php
/**
 * Projects module for GD Client Portal
 * Provides a simple shortcode to list projects for the current logged-in user.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_register_projects_module')) {
	function gd_client_portal_register_projects_module()
	{
		add_shortcode('gd_client_portal_projects', 'gd_client_portal_render_projects');
		// The canonical [gd_service_projects] shortcode is owned by includes/service-projects.php.
		// Do not overwrite it with the legacy demo-post renderer.
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_projects_assets');
	}
}

if (!function_exists('gd_client_portal_register_projects_assets')) {
	function gd_client_portal_register_projects_assets()
	{
		wp_register_style('gd-client-portal-projects', GD_CLIENT_PORTAL_URL . 'modules/projects/assets/projects.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
		wp_register_script('gd-client-portal-projects', GD_CLIENT_PORTAL_URL . 'modules/projects/assets/projects.js', array('gd-client-portal', 'jquery'), GD_CLIENT_PORTAL_VERSION, true);
	}
}

if (!function_exists('gd_client_portal_render_projects')) {
	function gd_client_portal_render_projects($atts = array())
	{
		if (function_exists('gd_client_portal_render_service_projects')) {
			return gd_client_portal_render_service_projects();
		}
		return gd_client_portal_render_placeholder_content(__('Projects module is unavailable.', 'gd-client-portal'));
	}
}

add_action('init', 'gd_client_portal_register_projects_module');

// Register the projects module dashboard view if the registry is available.
if (!function_exists('gd_client_portal_register_projects_dashboard_view')) {
	function gd_client_portal_register_projects_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('projects', 'gd_client_portal_render_projects');
		}
	}
	add_action('init', 'gd_client_portal_register_projects_dashboard_view');
}

/**
 * Module activation: create necessary DB table and initialize options.
 */
if (!function_exists('gd_client_portal_module_projects_activate')) {
	function gd_client_portal_module_projects_activate()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'gd_client_portal_projects';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		update_option('gd_client_portal_module_projects_installed', current_time('mysql'));

		// Do not seed demo/sample projects. The portal must reflect real client data only.
	}
}

/**
 * Module deactivation: mark module as deactivated (do not drop table by default).
 */
if (!function_exists('gd_client_portal_module_projects_deactivate')) {
	function gd_client_portal_module_projects_deactivate()
	{
		update_option('gd_client_portal_module_projects_deactivated', current_time('mysql'));
	}
}
